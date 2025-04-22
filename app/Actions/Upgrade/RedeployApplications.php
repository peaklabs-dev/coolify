<?php

namespace App\Actions\Upgrade;

use App\Models\Application;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

class RedeployApplications
{
    use AsAction;

    public function handle()
    {
        Application::on('old_db') // Use the v5.x DB connection (just the standard one) -> for the POC we will just use the v4.x connection for simplicity (as we would need all tables migrated otherwise)
            ->orderBy('id')
            ->chunk(100, function ($applications) {
                foreach ($applications as $application) {
                    try {
                        $deployment_uuid = new Cuid2;
                        ray([
                            'status' => 'Starting redeployment',
                            'application' => $application,
                            'deployment_uuid' => $deployment_uuid,
                        ]);

                        // Queue a fresh re-deployment for each application
                        queue_application_deployment(
                            application: $application,
                            deployment_uuid: $deployment_uuid,
                            no_questions_asked: true,
                            force_rebuild: true
                        );

                        ray([
                            'application_id' => $application->id,
                            'status' => 'Redeployment queued successfully',
                        ]);

                    } catch (\Exception $e) {
                        ray([
                            'application_id' => $application->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
