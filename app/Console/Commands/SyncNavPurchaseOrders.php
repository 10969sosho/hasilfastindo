<?php

namespace App\Console\Commands;

use App\Services\BusinessCentralService;
use Illuminate\Console\Command;

class SyncNavPurchaseOrders extends Command
{
    protected $signature = 'nav:sync-po {--limit=50 : Jumlah Purchase Order yang ditarik dari NAV}';

    protected $description = 'Sinkronisasi Purchase Order dari Microsoft Dynamics Business Central / NAV Cloud';

    public function handle(BusinessCentralService $bc): int
    {
        $count = $bc->syncPurchaseOrders((int) $this->option('limit'));

        $this->info("{$count} Purchase Order berhasil disinkronkan dari Microsoft Dynamics NAV");

        return Command::SUCCESS;
    }
}
