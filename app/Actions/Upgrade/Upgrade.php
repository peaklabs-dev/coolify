<?php

namespace App\Actions\Upgrade;

use App\Jobs\Upgrade\MigratePrivateKeys;

class Upgrade
{
    private RedeployApplications $redeployAction;

    public function __construct(RedeployApplications $redeployAction)
    {
        $this->redeployAction = $redeployAction;
    }

    public function handle()
    {
        MigratePrivateKeys::dispatch()->onQueue('high');
        $this->redeployAction->handle();
    }
}
