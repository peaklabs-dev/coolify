<?php

namespace App\Jobs;

use App\Actions\Migration\CheckApiHealth;
use App\Actions\Migration\MigrateTeams;
use App\Actions\Migration\MigrateUsers;
use App\Events\MigrationStatusUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class InstanceMigrationJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $sourceUrl,
        protected string $sourceToken,
        protected string $targetUrl,
        protected string $targetToken,
        protected int $teamId,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->sourceUrl))->dontRelease()];
    }

    public function handle(): void
    {
        try {
            event(new MigrationStatusUpdate(
                message: 'Starting instance migration process...',
                type: 'info',
                teamId: $this->teamId,
            ));

            app(CheckApiHealth::class)->execute(
                sourceUrl: $this->sourceUrl,
                sourceToken: $this->sourceToken,
                targetUrl: $this->targetUrl,
                targetToken: $this->targetToken,
                teamId: $this->teamId,
            );

            // app(MigrateUsers::class)->execute(
            //     sourceUrl: $this->sourceUrl,
            //     sourceToken: $this->sourceToken,
            //     targetUrl: $this->targetUrl,
            //     targetToken: $this->targetToken,
            //     teamId: $this->teamId,
            // );

            // app(MigrateTeams::class)->execute(
            //     sourceUrl: $this->sourceUrl,
            //     sourceToken: $this->sourceToken,
            //     targetUrl: $this->targetUrl,
            //     targetToken: $this->targetToken,
            //     teamId: $this->teamId,
            // );

        } catch (\Exception $e) {
            event(new MigrationStatusUpdate(
                message: 'Migration failed with the following error: '.$e->getMessage(),
                type: 'error',
                teamId: $this->teamId,
            ));
            throw new \Exception('Instance migration failed: '.$e->getMessage());
        }
    }
}
