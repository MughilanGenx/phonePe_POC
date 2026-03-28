<?php

namespace App\Http\Controllers\PhonePe;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PhonePeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhonepeController extends Controller
{
    public function __construct(private PhonePeService $phonePe) {}

    // Show Checkout Form
    public function index()
    {
        return view('payment.checkout');
    }

    // Show Transaction History for Admin
    public function history()
    {
        $payments = Payment::orderBy('created_at', 'desc')->get();

        return view('payment.history', compact('payments'));
    }

    // Download invoice (completed) or transaction receipt PDF (any status)
    public function downloadInvoice($merchantOrderId)
    {
        $payment = Payment::where('merchant_order_id', $merchantOrderId)->firstOrFail();

        $qrDataUri = $this->buildInvoiceQrDataUri($payment);

        $pdf = Pdf::loadView('payment.invoice', compact('payment', 'qrDataUri'));

        $prefix = $payment->status === 'COMPLETED' ? 'Invoice' : 'Receipt';

        return $pdf->download($prefix.'_'.$merchantOrderId.'.pdf');
    }

    /**
     * PNG data URI for “Scan to pay” block (order + txn text). Fails soft if HTTP blocked.
     */
    private function buildInvoiceQrDataUri(Payment $payment): ?string
    {
        $text = 'Order: '.$payment->merchant_order_id;
        if ($payment->transaction_id) {
            $text .= ' | Txn: '.$payment->transaction_id;
        }

        try {
            $response = Http::timeout(8)->get('https://api.qrserver.com/v1/create-qr-code/', [
                'size' => '180x180',
                'data' => $text,
            ]);

            if ($response->successful()) {
                return 'data:image/png;base64,'.base64_encode($response->body());
            }
        } catch (\Throwable $e) {
            Log::debug('Invoice QR generation skipped: '.$e->getMessage());
        }

        return null;
    }

    // Generate Shareable Payment Link (AJAX) — only saves to DB, no PhonePe call yet
    public function generatePaymentLink(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email',
            'phone' => 'required|digits:10',
            'amount' => 'required|numeric|min:1',
        ]);

        $merchantOrderId = 'ORD_'.strtoupper(uniqid());

        // Save pending payment to DB — PhonePe API is NOT called here.
        // It will be called exactly once when the customer opens the link.
        Payment::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'amount' => $request->amount,
            'merchant_order_id' => $merchantOrderId,
            'status' => 'PENDING',
            'source' => 'app',
        ]);

        $localLink = url('/pay/'.$merchantOrderId);

        return response()->json([
            'success' => true,
            'message' => 'Payment link generated successfully',
            'local_link' => $localLink,
            'merchant_order_id' => $merchantOrderId,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'amount' => $request->amount,
        ]);
    }

    // Process Shared Payment Link (customer clicks the link)
    public function processSharedLink($merchantOrderId)
    {
        $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

        if (! $payment) {
            return redirect()->route('payment.failed')
                ->with('error', 'Payment link expired or invalid.');
        }

        if ($payment->status !== 'PENDING') {
            return redirect()->route('payment.failed')
                ->with('error', 'Payment already processed.');
        }

        // Use the stored PhonePe checkout link directly.
        // Do NOT call initiatePayment() again — PhonePe will reject a duplicate merchantOrderId.
        if ($payment->phonepe_link) {
            return redirect()->away($payment->phonepe_link);
        }

        // Fallback: this order was never sent to PhonePe — try now
        try {
            $response = $this->phonePe->initiatePayment([
                'merchant_order_id' => $merchantOrderId,
                'amount' => $payment->amount,
                'name' => $payment->name,
                'email' => $payment->email,
                'phone' => $payment->phone,
            ]);

            if (isset($response['redirectUrl'])) {
                $payment->update(['phonepe_link' => $response['redirectUrl']]);

                return redirect()->away($response['redirectUrl']);
            }

            return redirect()->route('payment.failed')
                ->with('error', 'Payment initiation failed. Please try again.');

        } catch (\Exception $e) {
            return redirect()->route('payment.failed')
                ->with('error', 'Error: '.$e->getMessage());
        }
    }

    // Handle PhonePe Redirect Callback (browser redirect after payment)
    public function callback(Request $request)
    {
        Log::info('PhonePe Callback Reached', [
            'method' => $request->method(),
            'payload' => $request->all(),
            'url' => $request->fullUrl(),
        ]);

        $payload = $this->phonePe->decodeResponsePayload($request->all());
        if ($payload !== $request->all()) {
            Log::info('Decoded PhonePe Callback Payload', ['payload' => $payload]);
        }

        $merchantOrderId = $this->phonePe->extractMerchantOrderId($payload)
            ?? $request->input('merchantOrderId')
            ?? $request->input('transactionId');

        if (! $merchantOrderId) {
            // Check if it's in the URL itself if PhonePe didn't append it correctly
            return redirect()->route('payment.failed', ['error' => 'Invalid callback. Order ID missing. Check logs for payload details.']);
        }

        try {
            $statusResponse = $this->phonePe->checkStatus($merchantOrderId);
            $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

            if (! $payment) {
                return redirect()->route('payment.failed', ['error' => 'Order not found in database: '.$merchantOrderId]);
            }

            $attrs = $this->phonePe->attributesFromStatusResponse($statusResponse);
            unset($attrs['merchant_order_id']);
            $payment->update($attrs);

            $newStatus = $payment->fresh()->status;

            if ($newStatus === 'COMPLETED') {
                return redirect()->route('payment.success', ['order' => $merchantOrderId])
                    ->with('payment', $payment->fresh());
            }

            if ($newStatus === 'PENDING') {
                return redirect()->route('payment.failed', [
                    'error' => 'Payment is still PENDING. Please refresh the history page in a few moments.',
                ]);
            }

            return redirect()->route('payment.failed', [
                'error' => 'Payment failed. State: '.$newStatus,
            ]);

        } catch (\Exception $e) {
            return redirect()->route('payment.failed', [
                'error' => 'Status check failed: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Pull a single order from PhonePe (payment link / dashboard) into the local DB by Merchant Order ID.
     */
    public function importPhonePeOrder(Request $request)
    {
        $validated = $request->validate([
            'merchant_order_id' => ['required', 'string', 'max:191'],
        ]);

        $id = trim($validated['merchant_order_id']);

        try {
            $payment = $this->phonePe->upsertPaymentFromOrderStatus($id);

            return redirect()->route('payment.history')->with(
                'success',
                'Saved or updated '.$payment->merchant_order_id.' — status '.$payment->status.'.'
            );
        } catch (\Throwable $e) {
            Log::error('PhonePe import failed', [
                'merchant_order_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('payment.history')->with(
                'error',
                'Could not load that order from PhonePe. Check the Merchant Order ID matches the gateway and that API credentials are correct.'
            );
        }
    }

    // PhonePe Server-to-Server Webhook (POST from PhonePe servers).
    // Configure URL: {APP_URL}/api/phonepe/webhook — syncs app + external (dashboard / other PG) orders.
    public function webhook(Request $request)
    {
        $rawBody = $request->all();
        if ($rawBody === [] && $request->getContent() !== '') {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded)) {
                $rawBody = $decoded;
            }
        }

        Log::info('PhonePe Webhook Received:', [
            'headers' => $request->headers->all(),
            'body' => $rawBody,
        ]);

        $payload = $this->phonePe->decodeResponsePayload($rawBody);

        if ((isset($payload['data']) && $payload['data'] === 'WEBHOOK_VALIDATION_SUCCESS')
            || (isset($payload['payload']) && is_string($payload['payload']) && $payload['payload'] === 'WEBHOOK_VALIDATION_SUCCESS')) {
            Log::info('PhonePe Webhook Validation Ping Received');

            return response()->json(['status' => 'success', 'message' => 'Validated']);
        }

        $merchantOrderId = $this->phonePe->extractMerchantOrderId($payload);
        if (! $merchantOrderId) {
            Log::warning('PhonePe Webhook: Missing merchantOrderId', ['payload' => $payload]);

            return response()->json(['status' => 'error', 'message' => 'Missing merchantOrderId'], 200);
        }

        $existing = Payment::where('merchant_order_id', $merchantOrderId)->first();
        $statusResponse = $this->phonePe->tryOrderStatus($merchantOrderId);

        try {
            $attrs = $this->phonePe->mergeWebhookAndStatusForPayment($existing, $payload, $statusResponse);
        } catch (\InvalidArgumentException $e) {
            Log::warning('PhonePe Webhook: '.$e->getMessage(), ['payload' => $payload]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }

        if ($existing) {
            unset($attrs['source']);
        } else {
            $attrs['source'] = str_starts_with($merchantOrderId, 'ORD_') ? 'app' : 'phonepe_gateway';
            Log::info('PhonePe Webhook: Upserting new row', [
                'merchant_order_id' => $merchantOrderId,
                'source' => $attrs['source'],
            ]);
        }

        Payment::updateOrCreate(
            ['merchant_order_id' => $merchantOrderId],
            $attrs
        );

        Log::info('PhonePe Webhook: Payment upserted', [
            'merchant_order_id' => $merchantOrderId,
            'status_api_used' => $statusResponse !== null,
        ]);

        return response()->json(['status' => 'success'], 200);
    }

    // Manually Refresh Statuses for PENDING payments
    public function refreshStatuses()
    {
        $updatedCount = $this->phonePe->syncPendingPayments();

        return redirect()->back()->with('success', "Status synchronization complete. {$updatedCount} records updated.");
    }

    // Payment Success Page
    public function success(Request $request)
    {
        $payment = null;
        if ($request->has('order')) {
            $payment = Payment::where('merchant_order_id', $request->query('order'))->first();
        }

        return view('payment.success', compact('payment'));
    }

    // Payment Failed Page
    public function failed(Request $request)
    {
        // $error is automatically picked up via request('error') inside the Blade view
        return view('payment.failed');
    }
}
