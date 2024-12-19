<?php

namespace App\Actions\Service;

use App\Actions\Server\CleanupDocker;
use App\Models\Service;
use Lorisleiva\Actions\Concerns\AsAction;

class StopService
{
    use AsAction;

    public string $jobQueue = 'high';

    public function handle(Service $service, bool $isDeleteOperation = false, bool $dockerCleanup = true)
    {
        $server = $service->destination->server;

        if (! $server->isFunctional()) {
            return 'Server is not functional';
        }

        $containersToStop = $service->getContainers();

        if (empty($containersToStop)) {
            return 'No containers found to stop';
        }

        stopContainers($containersToStop, $server, 300);

        if (! $isDeleteOperation) {
            deleteConnectedNetworks($service->uuid, $server);
            if ($dockerCleanup) {
                CleanupDocker::dispatch($server, true);
            }
        }

    }
}
