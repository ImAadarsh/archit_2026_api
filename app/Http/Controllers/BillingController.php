<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\RazorpayService;

class BillingController extends Controller
{
    public function subscribe(Request $request, RazorpayService $rz)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|integer|exists:businessses,id',
            'plan_code' => 'required|string|exists:subscription_plans,code',
            'quantity' => 'sometimes|integer|min:1',
            'total_count' => 'nullable|integer|min:1'
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        if (empty($data['total_count'])) {
            $data['total_count'] = 36; // default to 12 billing cycles
        }

        $plan = DB::table('subscription_plans')->where('code', $data['plan_code'])->first();
        $business = DB::table('businessses')->where('id', $data['business_id'])->first();
        if (!$plan || !$business) {
            return response()->json(['status' => false, 'message' => 'Invalid business or plan'], 404);
        }

        DB::beginTransaction();
        try {
            $client = $rz->client();

            // Ensure Razorpay Customer
            $razorpayCustomerId = $business->razorpay_customer_id ?? null;
            if (!$razorpayCustomerId) {
                try {
                    $rc = $client->customer->create([
                        'name' => $business->owner_name ?? $business->business_name,
                        'email' => $business->email,
                        'contact' => $business->phone,
                        'notes' => [ 'business_id' => (string)$business->id ],
                    ]);
                    $razorpayCustomerId = $rc['id'];
                } catch (\Throwable $e) {
                    $msg = $e->getMessage();
                    if (stripos($msg, 'Customer already exists') !== false) {
                        // Try finding existing customer by email/contact
                        $existing = $client->customer->all([
                            'email' => $business->email,
                            'contact' => $business->phone,
                            'count' => 1,
                        ]);
                        if (!empty($existing['items'][0]['id'])) {
                            $razorpayCustomerId = $existing['items'][0]['id'];
                        } else {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }
                DB::table('businessses')->where('id', $business->id)
                    ->update(['razorpay_customer_id' => $razorpayCustomerId, 'updated_at' => now()]);
            }

            $payload = [
                'plan_id' => $plan->razorpay_plan_id,
                'customer_notify' => 1,
                'quantity' => $data['quantity'] ?? 1,
                'total_count' => (int)$data['total_count'],
                'customer_id' => $razorpayCustomerId,
                'notes' => [ 'business_id' => (string)$business->id, 'plan_code' => $plan->code ],
            ];
            // Razorpay Subscriptions do not accept a 'trial' parameter at create time.

            $rpSub = $client->subscription->create(array_filter($payload, fn($v) => $v !== null));

            $id = DB::table('subscriptions')->insertGetId([
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'quantity' => $data['quantity'] ?? 1,
                'status' => 'active',
                'current_period_start' => isset($rpSub['current_start']) ? date('Y-m-d H:i:s', $rpSub['current_start']) : null,
                'current_period_end' => isset($rpSub['current_end']) ? date('Y-m-d H:i:s', $rpSub['current_end']) : null,
                'next_charge_at' => isset($rpSub['charge_at']) ? date('Y-m-d H:i:s', $rpSub['charge_at']) : null,
                'cancel_at_period_end' => (int)($rpSub['cancel_at_cycle_end'] ?? 0),
                'trial_ends_at' => isset($rpSub['trial_end']) ? date('Y-m-d H:i:s', $rpSub['trial_end']) : null,
                'total_count' => $rpSub['total_count'] ?? null,
                'paid_count' => $rpSub['paid_count'] ?? 0,
                'razorpay_customer_id' => $razorpayCustomerId,
                'razorpay_subscription_id' => $rpSub['id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('businessses')->where('id', $business->id)
                ->update(['subscription_status' => 'active', 'updated_at' => now()]);

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Subscribed',
                'subscription_id' => $id,
                'razorpay_subscription_id' => $rpSub['id'] ?? null,
                'short_url' => $rpSub['short_url'] ?? null,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to subscribe', 'error' => $e->getMessage()], 500);
        }
    }

    public function list(Request $request)
    {
        $query = DB::table('subscriptions')
            ->leftJoin('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select('subscriptions.*', 'subscription_plans.name as plan_name', 'subscription_plans.code as plan_code');
        if ($request->business_id) $query->where('subscriptions.business_id', $request->business_id);
        if ($request->status) $query->where('subscriptions.status', $request->status);
        $query->orderByDesc('subscriptions.id');
        return response()->json(['status' => true, 'data' => $query->get()]);
    }

    public function payments(Request $request)
    {
        $query = DB::table('subscription_payments')
            ->leftJoin('subscriptions', 'subscription_payments.subscription_id', '=', 'subscriptions.id')
            ->leftJoin('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select(
                'subscription_payments.*',
                'subscription_plans.name as plan_name',
                'subscriptions.business_id'
            );
        if ($request->business_id) $query->where('subscription_payments.business_id', $request->business_id);
        if ($request->status) $query->where('subscription_payments.status', $request->status);
        if ($request->from) $query->whereDate('subscription_payments.created_at', '>=', $request->from);
        if ($request->to) $query->whereDate('subscription_payments.created_at', '<=', $request->to);
        $query->orderByDesc('subscription_payments.id');
        return response()->json(['status' => true, 'data' => $query->get()]);
    }

    public function cancel(Request $request, $id, RazorpayService $rz)
    {
        $sub = DB::table('subscriptions')->where('id', $id)->first();
        if (!$sub) return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        $atPeriodEnd = (bool)$request->boolean('at_period_end', true);

        DB::beginTransaction();
        try {
            $client = $rz->client();
            $client->subscription->fetch($sub->razorpay_subscription_id)->cancel(['cancel_at_cycle_end' => $atPeriodEnd]);

            DB::table('subscriptions')->where('id', $id)->update([
                'status' => $atPeriodEnd ? 'active' : 'canceled',
                'cancel_at_period_end' => $atPeriodEnd ? 1 : 0,
                'canceled_at' => $atPeriodEnd ? null : now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Cancellation processed']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to cancel', 'error' => $e->getMessage()], 500);
        }
    }

    public function pause(Request $request, $id, RazorpayService $rz)
    {
        $sub = DB::table('subscriptions')->where('id', $id)->first();
        if (!$sub) return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        $pauseAt = $request->input('pause_at', 'now'); // 'now' or 'end_of_cycle'
        DB::beginTransaction();
        try {
            $client = $rz->client();
            $client->subscription->fetch($sub->razorpay_subscription_id)->pause(['pause_at' => $pauseAt]);
            DB::table('subscriptions')->where('id', $id)->update([
                'status' => 'paused',
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Paused']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to pause', 'error' => $e->getMessage()], 500);
        }
    }

    public function resume(Request $request, $id, RazorpayService $rz)
    {
        $sub = DB::table('subscriptions')->where('id', $id)->first();
        if (!$sub) return response()->json(['status' => false, 'message' => 'Subscription not found'], 404);
        $resumeAt = $request->input('resume_at', 'now'); // 'now' or 'next_billing'
        DB::beginTransaction();
        try {
            $client = $rz->client();
            $client->subscription->fetch($sub->razorpay_subscription_id)->resume(['resume_at' => $resumeAt]);
            DB::table('subscriptions')->where('id', $id)->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);
            DB::commit();
            return response()->json(['status' => true, 'message' => 'Resumed']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to resume', 'error' => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');
        $secret = config('services.razorpay.webhook_secret');

        $eventId = optional(json_decode($payload))->payload->payment->entity->id ?? (optional(json_decode($payload))->id ?? null);

        // Store webhook idempotently
        try {
            DB::table('payment_webhooks')->insert([
                'gateway' => 'razorpay',
                'event_id' => (string)($eventId ?? uniqid('rp_', true)),
                'event_type' => json_decode($payload)->event ?? 'unknown',
                'payload' => $payload,
                'received_at' => now(),
                'process_status' => 'pending'
            ]);
        } catch (\Throwable $e) {
            // duplicate, continue
        }

        // Verify signature
        $computed = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($computed, $signature)) {
            return response()->json(['status' => false, 'message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $type = $event['event'] ?? '';

        // Minimal handling; expand as needed
        if ($type === 'subscription.charged') {
            $subId = $event['payload']['subscription']['entity']['id'] ?? null;
            $payment = $event['payload']['payment']['entity'] ?? [];
            if ($subId && $payment) {
                $sub = DB::table('subscriptions')->where('razorpay_subscription_id', $subId)->first();
                if ($sub) {
                    DB::table('subscription_payments')->insert([
                        'subscription_id' => $sub->id,
                        'business_id' => $sub->business_id,
                        'amount' => $payment['amount'] ?? 0,
                        'currency' => $payment['currency'] ?? 'INR',
                        'status' => $payment['status'] ?? 'created',
                        'payment_method_type' => $payment['method'] ?? null,
                        'razorpay_order_id' => $payment['order_id'] ?? null,
                        'razorpay_payment_id' => $payment['id'] ?? null,
                        'paid_at' => isset($payment['captured_at']) ? date('Y-m-d H:i:s', $payment['captured_at']) : now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('subscriptions')->where('id', $sub->id)->update([
                        'paid_count' => ($sub->paid_count ?? 0) + 1,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (str_starts_with($type, 'subscription.')) {
            $subId = $event['payload']['subscription']['entity']['id'] ?? null;
            $status = $event['payload']['subscription']['entity']['status'] ?? null;
            if ($subId && $status) {
                $map = [
                    'active' => 'active',
                    'authenticated' => 'active',
                    'pending' => 'past_due',
                    'halted' => 'paused',
                    'paused' => 'paused',
                    'cancelled' => 'canceled',
                    'completed' => 'completed',
                ];
                DB::table('subscriptions')->where('razorpay_subscription_id', $subId)->update([
                    'status' => $map[$status] ?? $status,
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('payment_webhooks')->where('event_id', $eventId)->update([
            'processed_at' => now(),
            'process_status' => 'processed',
        ]);

        return response()->json(['status' => true]);
    }
}

