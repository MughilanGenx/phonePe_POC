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
        Log::info('PhonePe Callback Reached', ['payload' => $request->all()]);

        // PhonePe can send transactionId or merchantOrderId based on API version
        $merchantOrderId = $request->input('merchantOrderId')
            ?? $request->input('transactionId')
            ?? $request->input('providerReferenceId');

        if (!$merchantOrderId) {
            return redirect()->route('payment.failed', ['error' => 'Invalid callback. Order ID missing.']);
        }

        try {
            $statusResponse = $this->phonePe->checkStatus($merchantOrderId);
            $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

            if (!$payment) {
                return redirect()->route('payment.failed', ['error' => 'Order not found in database: ' . $merchantOrderId]);
            }

            // In PhonePe v2, 'state' can be at root or nested under 'data'
            $state = $statusResponse['state'] 
                  ?? $statusResponse['data']['state'] 
                  ?? 'FAILED';

            // Extract transactionId properly 
            $transactionId = $statusResponse['transactionId'] 
                          ?? $statusResponse['data']['transactionId'] 
                          ?? null;

            $payment->update([
                'status'         => $state === 'COMPLETED' ? 'COMPLETED' : 'FAILED',
                'transaction_id' => $transactionId,
                'response_data'  => $statusResponse,
            ]);

            if ($state === 'COMPLETED') {
                return redirect()->route('payment.success', ['order' => $merchantOrderId])
                    ->with('payment', $payment);
            }

            return redirect()->route('payment.failed', [
                'error' => 'Payment failed or is still pending. State: ' . $state
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

        // Extract merchant order ID from webhook payload
        $merchantOrderId = $payload['merchantOrderId']
            ?? $payload['data']['merchantOrderId']
            ?? null;

        if (!$merchantOrderId) {
            Log::warning('PhonePe Webhook: Missing merchantOrderId');
            return response()->json(['status' => 'error', 'message' => 'Missing merchantOrderId'], 400);
        }

        $payment = Payment::where('merchant_order_id', $merchantOrderId)->first();

        if (!$payment) {
            Log::warning('PhonePe Webhook: Payment not found', ['merchantOrderId' => $merchantOrderId]);
            return response()->json(['status' => 'error', 'message' => 'Payment not found'], 404);
        }

        // Extract state from webhook payload
        $state = $payload['state']
            ?? $payload['data']['state']
            ?? 'FAILED';

        $transactionId = $payload['transactionId']
            ?? $payload['data']['transactionId']
            ?? null;

        $payment->update([
            'status'         => $state === 'COMPLETED' ? 'COMPLETED' : 'FAILED',
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
