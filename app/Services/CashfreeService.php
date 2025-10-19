<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashfreeService
{
    private $clientId;
    private $clientSecret;
    private $baseUrl;
    private $apiVersion;

    public function __construct()
    {
        $this->clientId = config('services.cashfree.client_id');
        $this->clientSecret = config('services.cashfree.client_secret');
        $this->baseUrl = config('services.cashfree.base_url', 'https://api.cashfree.com/pg');
        $this->apiVersion = config('services.cashfree.api_version', '2023-08-01');
    }

    /**
     * Create a plan in Cashfree
     */
    public function createPlan($planData)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->post($this->baseUrl . '/plans', $planData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Cashfree plan creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Failed to create plan in Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree service error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get a plan from Cashfree
     */
    public function getPlan($planId)
    {
        try {
            $response = Http::withHeaders([
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->get($this->baseUrl . '/plans/' . $planId);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception('Failed to get plan from Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree get plan error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all plans from Cashfree
     */
    public function getAllPlans()
    {
        try {
            $response = Http::withHeaders([
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->get($this->baseUrl . '/plans');

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception('Failed to get plans from Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree get all plans error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create a subscription in Cashfree
     */
    public function createSubscription($subscriptionData)
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->post($this->baseUrl . '/subscriptions', $subscriptionData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Cashfree subscription creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Failed to create subscription in Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree subscription service error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Pause a subscription
     */
    public function pauseSubscription($subscriptionId)
    {
        try {
            $response = Http::withHeaders([
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->post($this->baseUrl . '/subscriptions/' . $subscriptionId . '/pause');

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception('Failed to pause subscription in Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree pause subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Resume a subscription
     */
    public function resumeSubscription($subscriptionId)
    {
        try {
            $response = Http::withHeaders([
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->post($this->baseUrl . '/subscriptions/' . $subscriptionId . '/resume');

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception('Failed to resume subscription in Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree resume subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription($subscriptionId, $cancelAtPeriodEnd = false)
    {
        try {
            $url = $this->baseUrl . '/subscriptions/' . $subscriptionId . '/cancel';
            if ($cancelAtPeriodEnd) {
                $url .= '?cancel_at_period_end=true';
            }

            $response = Http::withHeaders([
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
            ])->post($url);

            if ($response->successful()) {
                return $response->json();
            } else {
                throw new \Exception('Failed to cancel subscription in Cashfree: ' . $response->body());
            }
        } catch (\Exception $e) {
            Log::error('Cashfree cancel subscription error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
