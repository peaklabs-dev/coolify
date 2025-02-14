<?php

namespace App\Actions\Migration;

use App\Events\MigrationStatusUpdate;
use Illuminate\Support\Facades\Http;

class CheckApiHealth
{
    public function execute(
        string $sourceUrl,
        string $targetUrl,
        int $teamId,
    ) {
        try {
            $sourceHealthResponse = Http::get("{$sourceUrl}/api/health");

            if (! $sourceHealthResponse->successful()) {
                event(new MigrationStatusUpdate(
                    message: 'Source API health check failed with the following response: '.$sourceHealthResponse->body(),
                    type: 'error',
                    teamId: $teamId
                ));
            } else {
                event(new MigrationStatusUpdate(
                    message: 'Source API health check successful.',
                    type: 'success',
                    teamId: $teamId
                ));
            }

            $targetHealthResponse = Http::get("{$targetUrl}/api/health");

            if (! $targetHealthResponse->successful()) {
                event(new MigrationStatusUpdate(
                    message: 'Target API health check failed with the following response: '.$targetHealthResponse->body(),
                    type: 'error',
                    teamId: $teamId
                ));
            } else {
                event(new MigrationStatusUpdate(
                    message: 'Target API health check successful.',
                    type: 'success',
                    teamId: $teamId
                ));
            }
        } catch (\Exception $e) {
            event(new MigrationStatusUpdate(
                message: 'API health check failed with the following error: '.$e->getMessage(),
                type: 'error',
                teamId: $teamId
            ));
        }
    }
}
