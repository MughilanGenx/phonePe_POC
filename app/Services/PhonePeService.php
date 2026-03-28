<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhonePeService
{
    private string $clientId;

    private string $clientSecret;

    private string $clientVersion;

    private string $baseUrl;

    public function __construct()
    {
        $this->clientId = env('PHONEPE_CLIENT_ID');
        $this->clientSecret = env('PHONEPE_CLIENT_SECRET');
        $this->clientVersion = env('PHONEPE_CLIENT_VERSION', '1');
        $this->baseUrl = env('PHONEPE_ENV') === 'production'
            ? 'https://api.phonepe.com/apis/pg'
            : 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    }

    /**
     * Get OAuth Access Token (cached until expiry)
     */
    public function getAccessToken(): string
    {
        return Cache::remember('phonepe_access_token', 600, function () {
            $authUrl = env('PHONEPE_ENV') === 'production'
                ? 'https://api.phonepe.com/apis/identity-manager/v1/oauth/token'
                : 'https://api-preprod.phonepe.com/apis/pg-sandbox/v1/oauth/token';

            $payload = [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'client_version' => $this->clientVersion,
                'grant_type' => 'client_credentials',
            ];

            Log::info('PhonePe Auth Request:', [
                'url' => $authUrl,
                'client_id' => $this->clientId,
                'client_version' => $this->clientVersion,
                'grant_type' => 'client_credentials',
            ]);

            $response = Http::asForm()
                ->withoutVerifying()  // Disable SSL verification for sandbox
                ->post($authUrl, $payload);

            Log::info('PhonePe Auth Response:', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if (! $response->successful()) {
                throw new \Exception('PhonePe Auth Failed: '.$response->body());
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Initiate Payment
     */
    public function initiatePayment(array $data): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'merchantOrderId' => $data['merchant_order_id'],
            'amount' => (int) ($data['amount'] * 100), // Convert to paise
            'expireAfter' => 1200, // 20 minutes
            'metaInfo' => [
                'udf1' => $data['name'],
                'udf2' => $data['email'],
                'udf3' => $data['phone'],
            ],
            'paymentFlow' => [
                'type' => 'PG_CHECKOUT',
                'message' => 'Payment for Order '.$data['merchant_order_id'],
                'merchantUrls' => [
                    'redirectUrl' => route('phonepe.callback', ['merchantOrderId' => $data['merchant_order_id']]),
                ],
            ],
        ];

        Log::info('PhonePe Initiate Payment Request:', [
            'url' => $this->baseUrl.'/checkout/v2/pay',
            'payload' => $payload,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'O-Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->withoutVerifying()  // Disable SSL verification for sandbox
            ->post($this->baseUrl.'/checkout/v2/pay', $payload);

        Log::info('PhonePe Initiate Payment Response:', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception('PhonePe Payment Initiation Failed: '.$response->body());
        }

        $json = $response->json();

        // PhonePe v2 API may return redirectUrl nested inside 'data' — normalize to root level
        if (! isset($json['redirectUrl']) && isset($json['data']['redirectUrl'])) {
            $json['redirectUrl'] = $json['data']['redirectUrl'];
        }

        return $json;
    }

    /**
     * Check Order Status
     *
     * @param  bool  $withDetails  When true, requests payment attempt details (metaInfo, instruments, payer hints).
     */
    public function checkStatus(string $merchantOrderId, bool $withDetails = true): array
    {
        $token = $this->getAccessToken();

        $url = $this->baseUrl.'/checkout/v2/order/'.$merchantOrderId.'/status';

        $query = $withDetails ? ['details' => 'true'] : [];

        Log::info('PhonePe Check Status Request:', ['url' => $url, 'query' => $query]);

        $response = Http::withHeaders([
            'Authorization' => 'O-Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->withoutVerifying()  // Disable SSL verification for sandbox
            ->get($url, $query);

        Log::info('PhonePe Check Status Response:', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception('PhonePe Status Check Failed: '.$response->body());
        }

        $body = (string) $response->body();
        if ($response->status() === 204 || trim($body) === '') {
            throw new \Exception(
                'PhonePe returned an empty order response (HTTP '.$response->status().'). '.
                'Check that PHONEPE_ENV matches where the payment was created (sandbox vs production), '.
                'and that the Merchant Order ID is exactly the one used when the payment link was issued.'
            );
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new \Exception('PhonePe Status Check: invalid JSON body');
        }

        return $this->normalizeOrderStatusResponse($json);
    }

    /**
     * PhonePe may return HTTP 200 with { "success": false, "code": "...", "message": "..." }.
     * Success payloads may be wrapped in { "success": true, "data": { ... } }.
     */
    public function normalizeOrderStatusResponse(array $json): array
    {
        if (array_key_exists('success', $json) && $json['success'] === false) {
            $msg = $json['message'] ?? $json['code'] ?? 'Unknown error';

            throw new \Exception('PhonePe Order Status: '.$msg);
        }

        if (($json['success'] ?? null) === true && isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return $json;
    }

    /**
     * Decode PhonePe callback/webhook body when wrapped in base64 "response".
     */
    public function decodeResponsePayload(array $raw): array
    {
        if (! isset($raw['response']) || ! is_string($raw['response'])) {
            return $raw;
        }

        $decodedJson = base64_decode($raw['response'], true);
        if ($decodedJson === false) {
            return $raw;
        }

        $decodedArray = json_decode($decodedJson, true);

        return is_array($decodedArray) ? $decodedArray : $raw;
    }

    /**
     * Map PhonePe order state to local status enum values.
     */
    public function mapStateToLocalStatus(?string $state): string
    {
        $s = strtoupper((string) $state);

        if (in_array($s, ['COMPLETED', 'SUCCESS', 'SUCCESSFUL', 'PAID'], true)) {
            return 'COMPLETED';
        }

        if (in_array($s, ['FAILED', 'FAILURE', 'ERROR', 'CANCELLED', 'CANCELED'], true)) {
            return 'FAILED';
        }

        return 'PENDING';
    }

    /**
     * Resolve merchant order id from nested PhonePe JSON.
     */
    public function extractMerchantOrderId(?array $payload): ?string
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        // Never use PhonePe "orderId" (OMO…) here — that is not the merchant order id for the status URL.
        $paths = [
            'merchantOrderId',
            'payload.merchantOrderId',
            'data.merchantOrderId',
            'order.merchantOrderId',
            'data.order.merchantOrderId',
            'originalMerchantOrderId',
            'data.originalMerchantOrderId',
            'merchant_order_id',
        ];

        foreach ($paths as $path) {
            $v = data_get($payload, $path);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * Amount in paise from webhook or status payload (nested paths).
     */
    public function extractAmountPaise(?array $payload): ?int
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $paths = [
            'amount',
            'payload.amount',
            'data.amount',
            'order.amount',
            'paymentFlow.amount',
            'data.orderAmount',
        ];

        foreach ($paths as $path) {
            $v = data_get($payload, $path);
            if ($v === null || $v === '') {
                continue;
            }
            if (is_numeric($v)) {
                return (int) $v;
            }
        }

        return null;
    }

    /**
     * Transaction / PhonePe reference id.
     */
    public function extractTransactionId(?array $payload): ?string
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $paths = [
            'transactionId',
            'payload.transactionId',
            'data.transactionId',
            'paymentDetails.transactionId',
            'data.paymentDetails.transactionId',
        ];

        foreach ($paths as $path) {
            $v = data_get($payload, $path);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        $attempts = data_get($payload, 'paymentDetails')
            ?? data_get($payload, 'data.paymentDetails')
            ?? [];
        if (is_array($attempts)) {
            foreach ($attempts as $attempt) {
                if (! is_array($attempt)) {
                    continue;
                }
                $st = strtoupper((string) ($attempt['state'] ?? ''));
                $tid = $attempt['transactionId'] ?? null;
                if ($st === 'COMPLETED' && is_string($tid) && $tid !== '') {
                    return $tid;
                }
            }
            foreach ($attempts as $attempt) {
                if (! is_array($attempt)) {
                    continue;
                }
                $tid = $attempt['transactionId'] ?? null;
                if (is_string($tid) && $tid !== '') {
                    return $tid;
                }
            }
        }

        return null;
    }

    public function extractState(?array $payload): ?string
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        $paths = [
            'state',
            'payload.state',
            'payload.payload.state',
            'data.state',
            'order.state',
            'data.order.state',
        ];

        foreach ($paths as $path) {
            $v = data_get($payload, $path);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    /**
     * Resolve local status (PENDING / COMPLETED / FAILED) from order payload + paymentDetails fallbacks.
     */
    public function resolveOrderState(array $payload): string
    {
        $raw = $this->extractState($payload);
        if (is_string($raw) && $raw !== '') {
            return $this->mapStateToLocalStatus($raw);
        }

        $attempts = data_get($payload, 'paymentDetails')
            ?? data_get($payload, 'data.paymentDetails')
            ?? [];

        if (! is_array($attempts) || $attempts === []) {
            return 'PENDING';
        }

        $n = 0;
        $completed = 0;
        $failed = 0;

        foreach ($attempts as $attempt) {
            if (! is_array($attempt)) {
                continue;
            }
            $n++;
            $st = strtoupper((string) ($attempt['state'] ?? ''));
            if ($st === 'COMPLETED') {
                $completed++;
            }
            if ($st === 'FAILED') {
                $failed++;
            }
        }

        if ($completed > 0) {
            return 'COMPLETED';
        }

        if ($n > 0 && $failed === $n) {
            return 'FAILED';
        }

        return 'PENDING';
    }

    /**
     * Customer hints from metaInfo / UDF (checkout flow) or webhook.
     */
    public function extractCustomerHints(?array $payload): array
    {
        if (! is_array($payload) || $payload === []) {
            return ['name' => null, 'email' => null, 'phone' => null];
        }

        $meta = data_get($payload, 'metaInfo')
            ?? data_get($payload, 'data.metaInfo')
            ?? data_get($payload, 'payload.metaInfo')
            ?? [];

        $name = is_array($meta) ? ($meta['udf1'] ?? $meta['customerName'] ?? null) : null;
        $email = is_array($meta) ? ($meta['udf2'] ?? $meta['customerEmail'] ?? null) : null;
        $phone = is_array($meta) ? ($meta['udf3'] ?? $meta['customerPhone'] ?? null) : null;

        $name = $name ?? data_get($payload, 'customerName') ?? data_get($payload, 'data.customerName');
        $email = $email ?? data_get($payload, 'customerEmail') ?? data_get($payload, 'data.customerEmail');
        $phone = $phone ?? data_get($payload, 'customerPhone') ?? data_get($payload, 'data.customerPhone');

        $hints = [
            'name' => is_string($name) && $name !== '' ? $name : null,
            'email' => is_string($email) && $email !== '' ? $email : null,
            'phone' => is_string($phone) && $phone !== '' ? $phone : null,
        ];

        return $this->enrichHintsFromPaymentDetails($payload, $hints);
    }

    /**
     * Pull payer / instrument fields from PhonePe paymentDetails (checkout + payment links).
     */
    public function enrichHintsFromPaymentDetails(array $payload, array $hints): array
    {
        $attempts = data_get($payload, 'paymentDetails')
            ?? data_get($payload, 'data.paymentDetails')
            ?? [];

        if (! is_array($attempts) || $attempts === []) {
            return $hints;
        }

        $best = null;
        foreach ($attempts as $attempt) {
            if (! is_array($attempt)) {
                continue;
            }
            $st = strtoupper((string) ($attempt['state'] ?? ''));
            if ($st === 'COMPLETED') {
                $best = $attempt;

                break;
            }
        }
        if ($best === null) {
            $last = end($attempts);
            $best = is_array($last) ? $last : null;
        }
        if ($best === null || ! is_array($best)) {
            return $hints;
        }

        $instrument = is_array($best['instrument'] ?? null) ? $best['instrument'] : [];

        $holder = $instrument['holderName']
            ?? $instrument['accountHolderName']
            ?? $instrument['name']
            ?? null;
        if ($hints['name'] === null && is_string($holder) && $holder !== '') {
            $hints['name'] = $holder;
        }

        $masked = $instrument['maskedAccountNumber']
            ?? $instrument['maskedAccountNo']
            ?? $instrument['maskedMobile']
            ?? null;
        if ($hints['phone'] === null && is_string($masked) && $masked !== '') {
            $digits = preg_replace('/\D+/', '', $masked);
            if (is_string($digits) && strlen($digits) >= 10) {
                $hints['phone'] = $masked;
            } elseif (is_string($masked) && strlen($masked) >= 8) {
                $hints['phone'] = $masked;
            }
        }

        $account = $instrument['account'] ?? $instrument['upiId'] ?? null;
        if (is_string($account) && $account !== '') {
            if (str_contains($account, '@')) {
                if ($hints['email'] === null && filter_var($account, FILTER_VALIDATE_EMAIL)) {
                    $hints['email'] = $account;
                } elseif ($hints['email'] === null) {
                    $hints['email'] = $account;
                }
            }
        }

        $mobile = data_get($best, 'rail.mobileNumber')
            ?? data_get($best, 'rail.payerPhone')
            ?? data_get($instrument, 'mobileNumber');
        if ($hints['phone'] === null && is_string($mobile) && $mobile !== '') {
            $hints['phone'] = $mobile;
        }

        return $hints;
    }

    /**
     * Create or update a Payment row using only the Order Status API (dashboard / payment-link orders).
     */
    public function upsertPaymentFromOrderStatus(string $merchantOrderId): Payment
    {
        $existing = Payment::where('merchant_order_id', $merchantOrderId)->first();
        $statusResponse = $this->checkStatus($merchantOrderId, true);

        $attrs = $this->mergeWebhookAndStatusForPayment(
            $existing,
            ['merchantOrderId' => $merchantOrderId],
            $statusResponse
        );

        if ($existing) {
            unset($attrs['source']);
        } else {
            $attrs['source'] = str_starts_with($merchantOrderId, 'ORD_') ? 'app' : 'phonepe_gateway';
        }

        return Payment::updateOrCreate(
            ['merchant_order_id' => $merchantOrderId],
            $attrs
        );
    }

    /**
     * Normalize a status API response into attributes for Payment (authoritative when present).
     */
    public function attributesFromStatusResponse(array $statusJson): array
    {
        $merchantOrderId = $this->extractMerchantOrderId($statusJson);
        $transactionId = $this->extractTransactionId($statusJson);
        $paise = $this->extractAmountPaise($statusJson);
        $hints = $this->extractCustomerHints($statusJson);

        $out = [
            'status' => $this->resolveOrderState($statusJson),
            'transaction_id' => $transactionId,
            'response_data' => $statusJson,
            'last_synced_at' => now(),
        ];

        if ($merchantOrderId) {
            $out['merchant_order_id'] = $merchantOrderId;
        }

        $phonePeOrderId = data_get($statusJson, 'orderId');
        if (is_string($phonePeOrderId) && $phonePeOrderId !== '') {
            $out['phonepe_order_id'] = $phonePeOrderId;
        }

        if ($paise !== null) {
            $out['amount'] = round($paise / 100, 2);
        }

        foreach (['name', 'email', 'phone'] as $k) {
            if ($hints[$k] !== null) {
                $out[$k] = $hints[$k];
            }
        }

        return $out;
    }

    /**
     * Merge webhook + optional status API: API wins for conflicting fields.
     */
    public function mergeWebhookAndStatusForPayment(
        ?Payment $existing,
        array $webhookPayload,
        ?array $statusResponse
    ): array {
        $merchantOrderId = $this->extractMerchantOrderId($webhookPayload)
            ?? $this->extractMerchantOrderId($statusResponse ?? [])
            ?? $existing?->merchant_order_id;

        if (! $merchantOrderId) {
            throw new \InvalidArgumentException('Cannot resolve merchant order id from webhook or status response.');
        }

        $fromWebhook = [];
        $fromWebhook['status'] = $this->resolveOrderState($webhookPayload);
        $fromWebhook['transaction_id'] = $this->extractTransactionId($webhookPayload);
        $wPaise = $this->extractAmountPaise($webhookPayload);
        if ($wPaise !== null) {
            $fromWebhook['amount'] = round($wPaise / 100, 2);
        }
        $wh = $this->extractCustomerHints($webhookPayload);
        foreach (['name', 'email', 'phone'] as $k) {
            if ($wh[$k] !== null) {
                $fromWebhook[$k] = $wh[$k];
            }
        }
        $fromWebhook['response_data'] = $webhookPayload;
        $fromWebhook['last_synced_at'] = now();

        if ($statusResponse === null || $statusResponse === []) {
            return array_merge(
                $this->defaultCustomerPlaceholders($existing, $fromWebhook),
                $fromWebhook,
                ['merchant_order_id' => $merchantOrderId]
            );
        }

        $fromApi = $this->attributesFromStatusResponse($statusResponse);
        unset($fromApi['merchant_order_id']);

        $fromApiFiltered = array_filter(
            $fromApi,
            fn ($v, $k) => $k === 'response_data'
                || $k === 'last_synced_at'
                || ($v !== null && $v !== ''),
            ARRAY_FILTER_USE_BOTH
        );

        $merged = array_merge(
            $this->defaultCustomerPlaceholders($existing, $fromWebhook),
            $fromWebhook,
            $fromApiFiltered,
            ['merchant_order_id' => $merchantOrderId, 'last_synced_at' => now()]
        );

        $merged['response_data'] = [
            'webhook' => $webhookPayload,
            'status_api' => $statusResponse,
        ];

        return $merged;
    }

    /**
     * Defaults for required columns when creating an external-platform row.
     */
    protected function defaultCustomerPlaceholders(?Payment $existing, array $incoming): array
    {
        $base = [
            'name' => $existing?->name ?? $incoming['name'] ?? 'External / PhonePe',
            'email' => $existing?->email ?? $incoming['email'] ?? 'N/A',
            'phone' => $existing?->phone ?? $incoming['phone'] ?? 'N/A',
            'amount' => $existing?->amount ?? $incoming['amount'] ?? 0,
        ];

        if (! isset($incoming['amount']) && $existing === null) {
            $base['amount'] = 0;
        }

        return $base;
    }

    /**
     * Fetch order status from PhonePe; returns null on failure (webhook should still succeed).
     */
    public function tryOrderStatus(string $merchantOrderId): ?array
    {
        try {
            return $this->checkStatus($merchantOrderId);
        } catch (\Throwable $e) {
            Log::warning('PhonePe tryOrderStatus failed', [
                'merchant_order_id' => $merchantOrderId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Re-fetch status for every local row that is not COMPLETED (used by UI + scheduled job).
     */
    public function syncPendingPayments(): int
    {
        $pendingPayments = Payment::where('status', '!=', 'COMPLETED')->get();
        $updatedCount = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $statusResponse = $this->checkStatus($payment->merchant_order_id);
                $attrs = $this->attributesFromStatusResponse($statusResponse);
                unset($attrs['merchant_order_id']);

                $before = [
                    'status' => $payment->status,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (string) $payment->amount,
                ];

                $payment->update($attrs);
                $payment->refresh();

                $after = [
                    'status' => $payment->status,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (string) $payment->amount,
                ];

                if ($before !== $after) {
                    $updatedCount++;
                }
            } catch (\Throwable $e) {
                Log::error('PhonePe syncPendingPayments failed for '.$payment->merchant_order_id.': '.$e->getMessage());
            }
        }

        return $updatedCount;
    }

    /**
     * Re-fetch status for every payment row (heavier — use after bulk CSV import or to fix stale rows).
     */
    public function syncAllPaymentsFromApi(): int
    {
        $updatedCount = 0;

        foreach (Payment::orderBy('id')->cursor() as $payment) {
            try {
                $statusResponse = $this->checkStatus($payment->merchant_order_id);
                $attrs = $this->attributesFromStatusResponse($statusResponse);
                unset($attrs['merchant_order_id']);

                $before = [
                    'status' => $payment->status,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (string) $payment->amount,
                ];

                $payment->update($attrs);
                $payment->refresh();

                $after = [
                    'status' => $payment->status,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => (string) $payment->amount,
                ];

                if ($before !== $after) {
                    $updatedCount++;
                }
            } catch (\Throwable $e) {
                Log::error('PhonePe syncAllPaymentsFromApi failed for '.$payment->merchant_order_id.': '.$e->getMessage());
            }
        }

        return $updatedCount;
    }

    /**
     * Upsert many orders by merchant order id (from CSV or a text list).
     *
     * @return array{ok: int, failed: int}
     */
    public function importOrdersByMerchantIds(array $ids): array
    {
        $ok = 0;
        $failed = 0;

        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            try {
                $this->upsertPaymentFromOrderStatus($id);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('PhonePe bulk import failed', [
                    'merchant_order_id' => $id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return ['ok' => $ok, 'failed' => $failed];
    }
}
