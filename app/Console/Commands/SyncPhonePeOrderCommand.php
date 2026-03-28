<?php

namespace App\Console\Commands;

use App\Services\PhonePeService;
use Illuminate\Console\Command;

class SyncPhonePeOrderCommand extends Command
{
    protected $signature = 'phonepe:sync-order {merchant_order_id : Merchant order id shown in PhonePe (payment link / dashboard)}';

    protected $description = 'Fetch one order from the PhonePe Checkout API and upsert it into the local payments table';

    public function handle(PhonePeService $phonePe): int
    {
        $id = trim((string) $this->argument('merchant_order_id'));

        try {
            $payment = $phonePe->upsertPaymentFromOrderStatus($id);
            $this->info("Synced {$payment->merchant_order_id} — status {$payment->status}, amount ₹{$payment->amount}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
