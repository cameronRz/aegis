<?php

namespace App\Console\Commands;

use App\Enum\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[Signature('orders:expire-stale')]
#[Description('Mark pending orders older than 25 hours as expired')]
class ExpireStaleOrders extends Command
{
    public function handle(): int
    {
        $count = Order::where('status', OrderStatus::Pending)
            ->where('created_at', '<', now()->subHours(25))
            ->update(['status' => OrderStatus::Expired]);

        $this->info("Expired {$count} stale order(s).");

        return CommandAlias::SUCCESS;
    }
}
