<?php

namespace App\Console\Commands;

use App\Services\PhonePeService;
use Illuminate\Console\Command;

class SyncPhonePeAllCommand extends Command
{
    protected $signature = 'phonepe:sync-all';

    protected $description = 'Re-fetch PhonePe order status for every row in the payments table (use after CSV import or to refresh everything)';

    public function handle(PhonePeService $phonePe): int
    {
        $this->warn('Calling PhonePe once per local row — this may take a while.');
        $n = $phonePe->syncAllPaymentsFromApi();
        $this->info("Changed {$n} payment record(s).");

        return self::SUCCESS;
    }
}
