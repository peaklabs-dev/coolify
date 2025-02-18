<?php

namespace App\Actions\Migration;

use App\Events\MigrationStatusUpdate;
use Illuminate\Support\Facades\Http;

class MigrateProjects
{
    public function execute(
        string $sourceUrl,
        string $sourceToken,
        string $targetUrl,
        string $targetToken,
        int $teamId,
    ) {
        try {
            $sourceResponse = Http::withToken($sourceToken)
                ->acceptJson()
                ->get("{$sourceUrl}/api/v1/projects");

            if (! $sourceResponse->successful()) {
                event(new MigrationStatusUpdate(
                    message: 'Failed to fetch projects from source: '.$sourceResponse->body(),
                    type: 'error',
                    teamId: $teamId
                ));

                return;
            } else {
                $projects = $sourceResponse->json();
                event(new MigrationStatusUpdate(
                    message: 'Found '.count($projects).' projects to migrate.',
                    type: 'info',
                    teamId: $teamId
                ));
            }

            $hasErrors = false;

            foreach ($projects as $project) {
                try {
                    $environments = [];
                    if (isset($project['environments']) && is_array($project['environments'])) {
                        foreach ($project['environments'] as $environment) {
                            $environments[] = [
                                'name' => $environment['name'],
                                'description' => $environment['description'] ?? null,
                            ];
                        }
                        event(new MigrationStatusUpdate(
                            message: 'Found '.count($environments)." environments to migrate for project: {$project['name']}",
                            type: 'info',
                            teamId: $teamId
                        ));
                    }

                    $projectPayload = [
                        'name' => $project['name'],
                        'description' => $project['description'],
                        'environments' => $environments,
                    ];

                    $targetResponse = Http::withToken($targetToken)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                        ])
                        ->post("{$targetUrl}/api/v1/projects", $projectPayload);

                    if ($targetResponse->status() !== 201) {
                        $hasErrors = true;
                        $errorMessage = $targetResponse->json()['message']
                            ?? 'No error message returned (Status: '.$targetResponse->status().')';

                        event(new MigrationStatusUpdate(
                            message: "Failed to create project {$project['name']}: ".$errorMessage,
                            type: 'error',
                            teamId: $teamId
                        ));

                        continue;
                    }

                    event(new MigrationStatusUpdate(
                        message: "Successfully migrated project: {$project['name']}",
                        type: 'success',
                        teamId: $teamId
                    ));
                } catch (\Exception $e) {
                    $hasErrors = true;
                    event(new MigrationStatusUpdate(
                        message: "Error migrating project {$project['name']}: ".$e->getMessage(),
                        type: 'error',
                        teamId: $teamId
                    ));
                }
            }

            if (! $hasErrors) {
                event(new MigrationStatusUpdate(
                    message: 'All projects and their environments have been migrated successfully.',
                    type: 'success',
                    teamId: $teamId
                ));
            } else {
                event(new MigrationStatusUpdate(
                    message: 'Projects and environments migration has been completed with errors.',
                    type: 'warning',
                    teamId: $teamId
                ));
            }
        } catch (\Exception $e) {
            event(new MigrationStatusUpdate(
                message: 'Projects and environments migration failed: '.$e->getMessage(),
                type: 'error',
                teamId: $teamId
            ));
            throw $e;
        }
    }
}
