<?php

namespace App\Console\Commands\Upgrade;

use App\Actions\Upgrade\RedeployApplications;
use Illuminate\Console\Command;

class RedeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redeploy'; // It is called in the migrate.go script like this: docker exec -it coolify php artisan redeploy

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force redeploy all migrated applications';

    /**
     * Execute the console command.
     */
    public function handle(RedeployApplications $action)
    {
        $this->info('Starting force redeploy process...');

        $action->handle();

        $this->info('Force redeploy process initiated successfully.');
    }
}
