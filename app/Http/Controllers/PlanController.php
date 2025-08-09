<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Services\RazorpayService;

class PlanController extends Controller
{
    public function publicIndex()
    {
        $plans = DB::table('subscription_plans')
            ->where('is_public', 1)
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $plans
        ]);
    }

    public function show($code)
    {
        $plan = DB::table('subscription_plans')
            ->where('code', $code)
            ->where('is_active', 1)
            ->first();

        if (!$plan) {
            return response()->json(['status' => false, 'message' => 'Plan not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $plan]);
    }

    public function store(Request $request, RazorpayService $rz)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'code' => 'required|string|alpha_dash|unique:subscription_plans,code',
            'amount' => 'required|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'interval_unit' => 'required|in:day,week,month,year',
            'interval_count' => 'required|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'is_public' => 'sometimes|boolean',
            'features_json' => 'nullable',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        // Normalize features_json from raw text if provided as multiline
        if (isset($data['features_json']) && is_string($data['features_json'])) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['features_json']))));
            $data['features_json'] = json_encode($lines);
        }

        // Map to Razorpay supported period values
        $periodMap = [
            'day' => 'daily',
            'week' => 'weekly',
            'month' => 'monthly',
            'year' => 'yearly',
        ];
        $razorpayPeriod = $periodMap[$data['interval_unit']];

        DB::beginTransaction();
        try {
            $client = $rz->client();
            $rpPlan = $client->plan->create([
                'period' => $razorpayPeriod,
                'interval' => $data['interval_count'],
                'item' => [
                    'name' => $data['name'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? 'INR',
                ],
                'notes' => [ 'code' => $data['code'], 'description' => $data['description'] ?? null ],
            ]);

            $id = DB::table('subscription_plans')->insertGetId([
                'name' => $data['name'],
                'code' => $data['code'],
                'description' => $data['description'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'amount' => $data['amount'],
                'interval_unit' => $data['interval_unit'],
                'interval_count' => $data['interval_count'],
                'trial_days' => $data['trial_days'] ?? null,
                'is_public' => (int)($data['is_public'] ?? 1),
                'features_json' => $data['features_json'] ?? null,
                'razorpay_plan_id' => $rpPlan['id'] ?? null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
            return response()->json(['status' => true, 'message' => 'Plan created', 'id' => $id], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['status' => false, 'message' => 'Failed to create plan', 'error' => $e->getMessage()], 500);
        }
    }
}

