<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SSLCommerzService
{
    private $storeId;
    private $storePassword;
    private $isSandbox;
    private $baseUrl;

    public function __construct()
    {
        $this->storeId = env('SSLCOMMERZ_STORE_ID');
        $this->storePassword = env('SSLCOMMERZ_STORE_PASSWORD');
        $this->isSandbox = env('SSLCOMMERZ_IS_SANDBOX', true);
        $this->baseUrl = $this->isSandbox 
            ? 'https://sandbox.sslcommerz.com' 
            : 'https://securepay.sslcommerz.com';
    }

    public function initiatePayment(array $data)
    {
        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'BDT',
            'tran_id' => $data['transaction_id'],
            'success_url' => $data['success_url'],
            'fail_url' => $data['fail_url'],
            'cancel_url' => $data['cancel_url'],
            'cus_name' => $data['customer_name'],
            'cus_email' => $data['customer_email'],
            'cus_phone' => $data['customer_phone'],
            'cus_add1' => '',
            'cus_city' => '',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => 'Travel Package Booking',
            'product_category' => 'Travel',
            'product_profile' => 'general',
        ];

        $response = Http::asForm()->post($this->baseUrl . '/gwprocess/v4/api.php', $postData);

        if ($response->successful() && isset($response->json()['GatewayPageURL'])) {
            return $response->json()['GatewayPageURL'];
        }

        throw new \Exception('Failed to initiate SSLCommerz payment');
    }

    public function verifyPayment($transactionId)
    {
        $postData = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'val_id' => $transactionId,
            'format' => 'json',
        ];

        $response = Http::asForm()->post($this->baseUrl . '/validator/api/validationserverAPI.php', $postData);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'status' => $data['status'] === 'VALID' ? 'VALID' : 'INVALID',
                'transaction_id' => $data['tran_id'] ?? null,
                'amount' => $data['amount'] ?? null,
                'currency' => $data['currency'] ?? null,
            ];
        }

        return ['status' => 'INVALID'];
    }
}

