<?php

namespace App\Console\Commands;

use App\Services\PhonePeService;
use Illuminate\Console\Command;

class SyncPhonePePendingCommand extends Command
{
    protected $signature = 'phonepe:sync-pending';

    protected $description = 'Re-fetch PhonePe order status for all local payments that are not COMPLETED';

    public function handle(PhonePeService $phonePe): int
    {
        $n = $phonePe->syncPendingPayments();
        $this->info("Updated {$n} payment record(s).");

        return self::SUCCESS;
    }
}
