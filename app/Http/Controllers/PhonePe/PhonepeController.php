<?php

namespace App\Http\Controllers\PhonePe;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Services\PhonePeService;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

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

    // Download Invoice PDF
    public function downloadInvoice($merchantOrderId)
    {
        $payment = Payment::where('merchant_order_id', $merchantOrderId)->firstOrFail();
        
        $pdf = Pdf::loadView('payment.invoice', compact('payment'));
        
        return $pdf->download('Invoice_' . $merchantOrderId . '.pdf');
    }

    // Generate Shareable Payment Link (AJAX) — only saves to DB, no PhonePe call yet
    public function generatePaymentLink(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:100',
            'email'  => 'required|email',
            'phone'  => 'required|digits:10',
            'amount' => 'required|numeric|min:1',
        ]);

        $merchantOrderId = 'ORD_' . strtoupper(uniqid());

        // Save pending payment to DB — PhonePe API is NOT called here.
        // It will be called exactly once when the customer opens the link.
        Payment::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'amount'            => $request->amount,
            'merchant_order_id' => $merchantOrderId,
            'status'            => 'PENDING',
        ]);

        $localLink = url('/pay/' . $merchantOrderId);

        return response()->json([
            'success'           => true,
            'message'           => 'Payment link generated successfully',
            'local_link'        => $localLink,
            'merchant_order_id' => $merchantOrderId,
            'name'              => $request->name,
            'email'             => $request->email,
            'phone'             => $request->phone,
            'amount'            => $request->amount,
        ]);
    }


    // Process Shared Payment Link (customer clicks the link)
    public function processSharedLink($merchantOrderId)
    {
        $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

        if (!$payment) {
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
                'amount'            => $payment->amount,
                'name'              => $payment->name,
                'email'             => $payment->email,
                'phone'             => $payment->phone,
            ]);

            if (isset($response['redirectUrl'])) {
                $payment->update(['phonepe_link' => $response['redirectUrl']]);
                return redirect()->away($response['redirectUrl']);
            }

            return redirect()->route('payment.failed')
                ->with('error', 'Payment initiation failed. Please try again.');

        } catch (\Exception $e) {
            return redirect()->route('payment.failed')
                ->with('error', 'Error: ' . $e->getMessage());
        }
    }

    // Handle PhonePe Redirect Callback (browser redirect after payment)
    public function callback(Request $request)
    {
        Log::info('PhonePe Callback Reached', [
            'method'  => $request->method(),
            'payload' => $request->all(),
            'url'     => $request->fullUrl(),
        ]);

        $payload = $request->all();

        // PhonePe often sends the data base64 encoded inside a 'response' attribute in v2
        if (isset($payload['response'])) {
            $decodedJson = base64_decode($payload['response']);
            $decodedArray = json_decode($decodedJson, true);
            if (is_array($decodedArray)) {
                $payload = $decodedArray;
                Log::info('Decoded PhonePe Callback Payload', ['payload' => $payload]);
            }
        }

        // Try to extract merchantOrderId from multiple possible locations
        $merchantOrderId = $payload['merchantOrderId']
            ?? $payload['payload']['merchantOrderId'] ?? null
            ?? $payload['data']['merchantOrderId'] ?? null
            ?? $payload['transactionId'] ?? null
            ?? $payload['payload']['transactionId'] ?? null
            ?? $request->input('merchantOrderId')
            ?? $request->input('transactionId');

        if (!$merchantOrderId) {
            // Check if it's in the URL itself if PhonePe didn't append it correctly
            return redirect()->route('payment.failed', ['error' => 'Invalid callback. Order ID missing. Check logs for payload details.']);
        }

        try {
            $statusResponse = $this->phonePe->checkStatus($merchantOrderId);
            $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

            if (!$payment) {
                return redirect()->route('payment.failed', ['error' => 'Order not found in database: ' . $merchantOrderId]);
            }

            // Extract state and transaction ID robustly
            $state = $statusResponse['state'] 
                  ?? $statusResponse['data']['state'] 
                  ?? $statusResponse['payload']['state']
                  ?? 'PENDING'; // Default to PENDING if unknown

            $transactionId = $statusResponse['transactionId'] 
                           ?? $statusResponse['data']['transactionId'] 
                           ?? $statusResponse['payload']['transactionId']
                           ?? null;

            // Normalize state (PhonePe uses COMPLETED, PENDING, FAILED)
            $newStatus = $state; 
            if ($state === 'SUCCESS') $newStatus = 'COMPLETED'; // Support standard legacy SUCCESS if returned

            $payment->update([
                'status'         => $newStatus,
                'transaction_id' => $transactionId,
                'response_data'  => $statusResponse,
            ]);

            if ($newStatus === 'COMPLETED') {
                return redirect()->route('payment.success', ['order' => $merchantOrderId])
                    ->with('payment', $payment);
            }

            if ($newStatus === 'PENDING') {
                return redirect()->route('payment.failed', [
                    'error' => 'Payment is still PENDING. Please refresh the history page in a few moments.'
                ]);
            }

            return redirect()->route('payment.failed', [
                'error' => 'Payment failed. State: ' . $state
            ]);

        } catch (\Exception $e) {
            return redirect()->route('payment.failed', [
                'error' => 'Status check failed: ' . $e->getMessage()
            ]);
        }
    }

    // PhonePe Server-to-Server Webhook (POST from PhonePe servers)
    public function webhook(Request $request)
    {
        Log::info('PhonePe Webhook Received:', [
            'headers' => $request->headers->all(),
            'body'    => $request->all(),
        ]);

        $payload = $request->all();

        // PhonePe Webhooks send the data base64 encoded inside a 'response' attribute
        if (isset($payload['response'])) {
            $decodedJson = base64_decode($payload['response']);
            $decodedArray = json_decode($decodedJson, true);
            if (is_array($decodedArray)) {
                $payload = $decodedArray;
            }
        }

        // Handle PhonePe Dashboard "Test" Webhook Ping
        if ((isset($payload['data']) && $payload['data'] === 'WEBHOOK_VALIDATION_SUCCESS') || 
            (isset($payload['payload']) && is_string($payload['payload']) && $payload['payload'] === 'WEBHOOK_VALIDATION_SUCCESS')) {
            Log::info('PhonePe Webhook Validation Ping Received');
            return response()->json(['status' => 'success', 'message' => 'Validated']);
        }

        // Extract merchant order ID robustly from webhook payload
        $merchantOrderId = $payload['merchantOrderId']
            ?? $payload['payload']['merchantOrderId'] ?? null
            ?? $payload['data']['merchantOrderId'] ?? null
            ?? null;

        if (!$merchantOrderId) {
            Log::warning('PhonePe Webhook: Missing merchantOrderId', ['payload' => $payload]);
            return response()->json(['status' => 'error', 'message' => 'Missing merchantOrderId'], 200); // 200 to stop retry, but log error
        }

        $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();
 
        if (!$payment) {
            // Transaction was likely initiated from the PhonePe Dashboard directly
            // We create a new record so it shows up in our history
            Log::info('PhonePe Webhook: Creating new record for external transaction', ['merchantOrderId' => $merchantOrderId]);
            
            $rawAmount = $payload['amount'] 
                      ?? $payload['payload']['amount'] ?? 0
                      ?? $payload['data']['amount'] ?? 0;

            $payment = Payment::create([
                'merchant_order_id' => $merchantOrderId,
                'name'              => 'PhonePe Dashboard User',
                'email'             => 'N/A',
                'phone'             => 'N/A',
                'amount'            => $rawAmount / 100, // Convert paise to rupees
                'status'            => 'PENDING',
            ]);
        }

        // Extract state and transaction ID robustly
        $state = $payload['state']
            ?? $payload['payload']['state'] ?? null
            ?? $payload['data']['state'] ?? null
            ?? 'FAILED';

        $transactionId = $payload['transactionId']
            ?? $payload['payload']['transactionId'] ?? null
            ?? $payload['data']['transactionId'] ?? null
            ?? null;

        // Normalize state
        $newStatus = $state;
        if ($state === 'SUCCESS') $newStatus = 'COMPLETED';

        $payment->update([
            'status'         => $newStatus,
            'transaction_id' => $transactionId,
            'response_data'  => $payload,
        ]);

        Log::info('PhonePe Webhook: Payment updated', [
            'merchant_order_id' => $merchantOrderId,
            'state'             => $state,
            'transaction_id'    => $transactionId,
        ]);

        return response()->json(['status' => 'success'], 200);
    }

    // Manually Refresh Statuses for PENDING payments
    public function refreshStatuses()
    {
        // Get payments that are not yet marked as COMPLETED
        $pendingPayments = Payment::where('status', '!=', 'COMPLETED')->get();
        $updatedCount = 0;

        foreach ($pendingPayments as $payment) {
            try {
                $statusResponse = $this->phonePe->checkStatus($payment->merchant_order_id);
                
                // Extract state and transaction ID robustly (same logic as callback)
                $state = $statusResponse['state'] 
                      ?? $statusResponse['data']['state'] 
                      ?? $statusResponse['payload']['state']
                      ?? null;

                $transactionId = $statusResponse['transactionId'] 
                               ?? $statusResponse['data']['transactionId'] 
                               ?? $statusResponse['payload']['transactionId']
                               ?? null;

                if ($state && $state !== $payment->status) {
                    // Normalize state
                    $newStatus = $state;
                    if ($state === 'SUCCESS') $newStatus = 'COMPLETED';

                    $payment->update([
                        'status'         => $newStatus,
                        'transaction_id' => $transactionId,
                        'response_data'  => $statusResponse,
                    ]);
                    $updatedCount++;
                }
            } catch (\Exception $e) {
                Log::error("Failed to refresh status for {$payment->merchant_order_id}: " . $e->getMessage());
            }
        }

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
