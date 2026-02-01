<?php

namespace App\Console\Commands;

use App\Models\InventoryHold;
use Illuminate\Console\Command;

class CleanupExpiredHolds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holds:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release expired inventory holds';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredHolds = InventoryHold::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        $count = $expiredHolds->count();

        if ($count === 0) {
            $this->info('No expired holds found.');
            return 0;
        }

        foreach ($expiredHolds as $hold) {
            $hold->release();
        }

        $this->info("Released {$count} expired inventory hold(s).");

        return 0;
    }
}
