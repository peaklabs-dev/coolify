<?php

namespace App\Actions\Service;

use App\Models\Service;
use Lorisleiva\Actions\Concerns\AsAction;

class RestartService
{
    use AsAction;

    public string $jobQueue = 'high';

    public function handle(Service $service)
    {
        $server = $service->destination->server;

        if (! $server->isFunctional()) {
            return 'Server is not functional';
        }

        $containersToRestart = $service->getContainers();

        if (empty($containersToRestart)) {
            return 'No containers found to restart';
        }

        restartContainers($containersToRestart, $server, 300);

    }
}
