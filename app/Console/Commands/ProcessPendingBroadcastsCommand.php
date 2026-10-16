<?php

namespace App\Console\Commands;

use App\Services\BroadcastService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('broadcasts:process-pending {--limit=50 : Max pending deliveries to send this run}')]
#[Description('Send pending broadcast deliveries in small batches')]
class ProcessPendingBroadcastsCommand extends Command
{
    public function handle(BroadcastService $broadcasts): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = $broadcasts->processPendingDeliveries($limit);

        $this->info("Processed {$processed} pending broadcast delivery(ies).");

        return self::SUCCESS;
    }
}
