<div>
    <x-slot:title>
        Migration Settings | Coolify
        </x-slot>
        <x-settings.navbar />
        <div class="flex flex-col">
            <h2>Migration Configuration</h2>
            <div>Migrate your Coolify instance to another Coolify instance on the Cloud or another self-hosted instance.</div>

            <div class="w-56 pt-2">
                <x-forms.checkbox wire:model.live="migration_enabled" id="migration_enabled" label="Enable Migration" />
            </div>

            @if($migration_enabled)
            @if(!$is_api_enabled)
            <div class="mt-4 p-4 bg-warning/10 border border-warning text-warning rounded-md">
                <p class="font-bold">API is not enabled</p>
                <p>To migrate to or from this instance, you need to enable the API on this and the target instance.
                    <a href="{{ route('settings.index') }}" class="underline hover:text-error/80">Configuration settings</a>.
                </p>
            </div>
            @else
            <div class="flex flex-col gap-2 pt-6">
                <div class="flex gap-4 items-center">
                    <button type="button" wire:click="$set('migration_direction', 'from')" class="px-4 py-2 rounded-md {{ $migration_direction === 'from' ? 'bg-coollabs text-white' : 'bg-coolgray-100 dark:bg-coolgray-700 dark:text-white' }}">
                        Migrate from this Instance
                    </button>
                    <button type="button" wire:click="$set('migration_direction', 'to')" class="px-4 py-2 rounded-md {{ $migration_direction === 'to' ? 'bg-coollabs text-white' : 'bg-coolgray-100 dark:bg-coolgray-700 dark:text-white' }}">
                        Migrate to this Instance
                    </button>
                </div>

                @if($migration_direction === 'to' && $is_api_enabled)
                <div class="flex flex-wrap items-end gap-2 mt-4">
                    <div class="flex gap-2 md:flex-row flex-col w-full">
                        <div class="md:w-2/5">
                            <x-forms.input readonly wire:model="source_migration_url" type="url" label="Source Instance URL" helper="URL of the source Coolify instance to migrate from. All your stuff will be migrated from this instance." autocomplete="new-password" />
                        </div>
                        <div class="flex-1">
                            <div class="flex gap-2 items-end">
                                <div class="flex-1">
                                    <x-forms.input readonly wire:model="source_migration_api_token" type="password" label="Source Instance API Token" helper="API token from the source instances migration settings" autocomplete="new-password" />
                                </div>
                                <x-forms.button wire:click="regenerateSourceApiToken" class="!p-2 shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path d="M20 11a8.1 8.1 0 0 0 -15.5 -2m-.5 -4v4h4" />
                                        <path d="M4 13a8.1 8.1 0 0 0 15.5 2m.5 4v-4h-4" />
                                    </svg>
                                </x-forms.button>
                            </div>
                        </div>
                    </div>
                </div>

                @else
                <div class="flex flex-wrap items-end gap-2 mt-4">
                    <div class="flex gap-2 md:flex-row flex-col w-full">
                        <x-forms.input wire:model="target_migration_url" type="url" label="Target Instance URL" helper="URL of the target Coolify instance to migrate to. All your stuff will be migrated to this instance." autocomplete="new-password" />
                        <x-forms.input wire:model="target_migration_api_token" type="password" label="Target Instance API Token" helper="API token from the target instances migration settings" autocomplete="new-password" />
                    </div>
                </div>
                <div class="pt-4">
                    <x-forms.button wire:click='startMigration' class="bg-warning text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M7 4v16l13 -8z" />
                        </svg>
                        Start Migration
                    </x-forms.button>
                </div>

                <div class="mt-8 bg-coolgray-50 dark:bg-coolgray-800 border border-coolgray-100 dark:border-coolgray-700 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-coolgray-100/50 dark:bg-coolgray-700/50 border-b border-coolgray-200 dark:border-coolgray-600">
                        <h3>Migration Status</h3>
                    </div>
                    <div class="divide-y divide-coolgray-100 dark:divide-coolgray-700">
                        <div class="p-4 space-y-2 max-h-[400px] overflow-y-auto" wire:poll.5s>
                            @if(empty($migration_messages))
                            <div class="text-sm italic">
                                No migration events yet. Status updates will appear here when migration starts.
                            </div>
                            @endif
                            @foreach($migration_messages as $message)
                            <div class="flex items-center gap-2 p-2 text-sm rounded {{ 
                                        $message['type'] === 'success' ? 'bg-success/5 dark:bg-success/10' : 
                                        ($message['type'] === 'error' ? 'bg-error/5 dark:bg-error/10' : 
                                        ($message['type'] === 'warning' ? 'bg-warning/5 dark:bg-warning/10' : 
                                        'bg-info/5 dark:bg-info/10')) 
                                    }}">
                                @if($message['type'] === 'success')
                                <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 w-4 h-4 text-success-600 dark:text-success-400" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M5 12l5 5l10 -10" />
                                </svg>
                                @elseif($message['type'] === 'error')
                                <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 w-4 h-4 text-error-600 dark:text-error-400" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M18 6l-12 12" />
                                    <path d="M6 6l12 12" />
                                </svg>
                                @elseif($message['type'] === 'warning')
                                <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 w-4 h-4 text-warning-600 dark:text-warning-400" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 9v2m0 4v.01" />
                                    <path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75" />
                                </svg>
                                @else
                                <svg xmlns="http://www.w3.org/2000/svg" class="shrink-0 w-4 h-4 text-info-600 dark:text-info-400" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path d="M12 8l.01 0" />
                                    <path d="M11 12l1 0l0 4l1 0" />
                                </svg>
                                @endif
                                <span>{{ $message['message'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif
            @endif
        </div>
</div>
