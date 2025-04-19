<?php

namespace App\Actions\Upgrade;

use App\Jobs\Upgrade\MigratePrivateKeys;

class Upgrade
{
    public function handle()
    {
        MigratePrivateKeys::dispatch()->onQueue('high');
    }
}
