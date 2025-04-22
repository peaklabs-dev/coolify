<?php

namespace App\Console\Commands\Upgrade;

use App\Actions\Upgrade\Upgrade;
use Illuminate\Console\Command;

class MigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate'; // It is called in the migrate.go script like this: docker exec -it coolify php artisan migrate

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Coolify upgrade migration process';

    /**
     * Execute the console command.
     */
    public function handle(Upgrade $action)
    {
        $this->info('Starting Coolify migration process...');

        $action->handle();

        $this->info('Migration process initiated successfully.');
    }
}
