<?php

namespace App\Livewire\Settings;

use App\Jobs\InstanceMigrationJob;
use App\Models\InstanceSettings;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;
use Livewire\Component;

class Migration extends Component
{
    use HasApiTokens;

    public InstanceSettings $settings;

    public bool $migration_enabled = false;

    public bool $is_api_enabled = false;

    public ?string $source_migration_url = null;

    public ?string $source_migration_api_token = null;

    public ?string $target_migration_url = null;

    public ?string $target_migration_api_token = null;

    public ?string $migration_direction = 'from';

    public array $migration_messages = [];

    public int $teamId;

    protected function getListeners()
    {
        $this->teamId = auth()->user()->currentTeam()->id;

        return [
            "echo-private:team.{$this->teamId},.status-update" => 'handleMigrationStatusUpdate',
        ];
    }

    public function mount()
    {
        $this->settings = instanceSettings();

        $this->migration_enabled = $this->settings->migration_enabled;
        $this->is_api_enabled = $this->settings->is_api_enabled;

        $this->source_migration_api_token = $this->settings->source_instance_migration_api_token;
        $this->source_migration_url = request()->getSchemeAndHttpHost();

        if ($this->migration_enabled && $this->is_api_enabled && empty($this->source_migration_api_token)) {
            $this->generateSourceInstanceMigrationToken();
        }
    }

    public function handleMigrationStatusUpdate($event)
    {
        $message = [
            'message' => $event['message'],
            'type' => $event['type'],
            'timestamp' => $event['timestamp'],
        ];

        $this->migration_messages[] = $message;
        Cache::put('migration_messages', $this->migration_messages, now()->addHours(1));

        $this->dispatch('migration-message-received', $message);
    }

    public function updatedMigrationEnabled($value)
    {
        $this->settings->migration_enabled = $value;
        $this->settings->save();

        if (! $value) {
            $this->invalidateMigrationToken();
            $this->source_migration_api_token = null;
            $this->settings->source_instance_migration_api_token = null;
            $this->dispatch('success', 'Migration disabled successfully.');

            return;
        }

        if ($this->is_api_enabled) {
            $this->generateSourceInstanceMigrationToken();
        }
        $this->dispatch('success', 'Migration enabled successfully.');
    }

    public function regenerateSourceApiToken()
    {
        if (! $this->is_api_enabled || ! $this->migration_enabled) {
            return;
        }

        $this->invalidateMigrationToken();
        $this->generateSourceInstanceMigrationToken();
        $this->dispatch('success', 'Migration token regenerated successfully!');
    }

    private function invalidateMigrationToken(): void
    {
        auth()->user()->tokens()->where('name', 'Migration Token')->delete();
        $this->settings->source_instance_migration_api_token = null;
        $this->settings->save();
    }

    private function generateSourceInstanceMigrationToken(): void
    {
        if (! $this->is_api_enabled || ! $this->migration_enabled) {
            return;
        }

        $token = auth()->user()->createToken('Migration Token', ['root'])->plainTextToken;
        $this->source_migration_api_token = $token;
        $this->settings->source_instance_migration_api_token = $token;
        $this->settings->save();
    }

    public function startMigration()
    {
        if (empty($this->target_migration_url) || empty($this->target_migration_api_token)) {
            $this->dispatch('error', 'Please configure the target instance migration URL and API token first.');

            return;
        }

        try {
            $this->migration_messages = [];
            Cache::put('migration_messages', [], now()->addHours(1));

            InstanceMigrationJob::dispatch(
                sourceUrl: $this->source_migration_url,
                sourceToken: $this->source_migration_api_token,
                targetUrl: $this->target_migration_url,
                targetToken: $this->target_migration_api_token,
                teamId: $this->teamId,
            );
        } catch (\Exception $e) {
            $this->dispatch('error', 'Failed to start migration: '.$e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.settings.migration');
    }
}
