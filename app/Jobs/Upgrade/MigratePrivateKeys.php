<?php

namespace App\Jobs\Upgrade;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class MigratePrivateKeys implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        DB::connection('old_db') // Coolify v4.x DB
            ->table('private_keys')
            ->orderBy('id')
            ->chunk(100, function ($privateKeys) {
                foreach ($privateKeys as $oldKey) {
                    $this->migrateData($oldKey);
                    ray('Old Key:', $oldKey);
                }
            });
    }

    private function migrateData(object $oldKey): void
    {
        DB::transaction(function () use ($oldKey) {

            $privateKey = Crypt::decryptString($oldKey->private_key); // Uses APP_PREVIOUS_KEYS for decryption, as the APP_KEY is new and does not work for decryption.

            $newKeyData = [
                'id' => $oldKey->id,
                'cuid' => $oldKey->uuid, // New column Name
                'name' => $oldKey->name,
                'description' => $oldKey->description,
                'private_key' => Crypt::encryptString($privateKey), // Encrypts the private key with the new APP_KEY
                'is_git_related' => $oldKey->is_git_related,
                'team_id' => $oldKey->team_id,
                'fingerprint' => $oldKey->fingerprint,
                'created_at' => $oldKey->created_at,
                'updated_at' => $oldKey->updated_at,
            ];
            ray('New Key:', $newKeyData);
            DB::connection('pgsql') // Coolify v5x DB --> New Schema & Postgres 17
                ->table('ssh_keys') // New table name
                ->insert($newKeyData); // Insert new Data
        });
    }
}
