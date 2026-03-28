<?php

namespace App\Console\Commands;

use App\Services\PhonePeService;
use Illuminate\Console\Command;

class ImportPhonePeCsvCommand extends Command
{
    protected $signature = 'phonepe:import-csv
        {path? : CSV path (default: storage/app/phonepe_merchant_order_ids.csv)}
        {--column= : Header name for the merchant order id column (auto-detected if omitted)}';

    protected $description = 'Read merchant order IDs from a CSV and upsert each from the PhonePe Order Status API (bulk backfill)';

    public function handle(PhonePeService $phonePe): int
    {
        $path = $this->argument('path')
            ?? storage_path('app/phonepe_merchant_order_ids.csv');

        if (! is_readable($path)) {
            $this->error('File not readable: '.$path);
            $this->line('Create a CSV with one column header like "merchant_order_id" and one ID per row, or place it at storage/app/phonepe_merchant_order_ids.csv');

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error('Could not open file.');

            return self::FAILURE;
        }

        $first = fgetcsv($handle);
        if ($first === false) {
            fclose($handle);
            $this->error('Empty CSV.');

            return self::FAILURE;
        }

        $ids = [];
        $columnOption = $this->option('column');

        if ($columnOption) {
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $first);
            $idx = array_search(strtolower((string) $columnOption), $header, true);
            if ($idx === false) {
                fclose($handle);
                $this->error('Column not found: '.$columnOption);

                return self::FAILURE;
            }
            while (($row = fgetcsv($handle)) !== false) {
                $ids[] = $row[$idx] ?? '';
            }
        } elseif ($this->looksLikeHeaderRow($first)) {
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $first);
            $idx = $this->detectMerchantOrderColumnIndex($header);
            if ($idx === null) {
                fclose($handle);
                $this->error('Could not detect merchant order id column. Use --column="Your Header Name".');

                return self::FAILURE;
            }
            while (($row = fgetcsv($handle)) !== false) {
                $ids[] = $row[$idx] ?? '';
            }
        } else {
            $ids[] = $first[0] ?? '';
            while (($row = fgetcsv($handle)) !== false) {
                $ids[] = $row[0] ?? '';
            }
        }

        fclose($handle);

        $ids = array_values(array_filter(array_map('trim', $ids), fn ($v) => $v !== ''));
        if ($ids === []) {
            $this->error('No merchant order IDs found in CSV.');

            return self::FAILURE;
        }

        $this->info('Importing '.count($ids).' id(s)…');
        $result = $phonePe->importOrdersByMerchantIds($ids);
        $this->info("OK: {$result['ok']}, failed: {$result['failed']}");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function looksLikeHeaderRow(array $first): bool
    {
        $joined = strtolower(implode(' ', array_map('strval', $first)));

        return str_contains($joined, 'merchant')
            || str_contains($joined, 'order')
            || str_contains($joined, 'reference');
    }

    /**
     * @param  array<int, string>  $headerLower
     */
    private function detectMerchantOrderColumnIndex(array $headerLower): ?int
    {
        foreach ($headerLower as $i => $name) {
            if (str_contains($name, 'merchant') && str_contains($name, 'order')) {
                return $i;
            }
        }
        foreach ($headerLower as $i => $name) {
            if ($name === 'merchant_order_id' || $name === 'merchant order id') {
                return $i;
            }
        }

        return 0;
    }
}
