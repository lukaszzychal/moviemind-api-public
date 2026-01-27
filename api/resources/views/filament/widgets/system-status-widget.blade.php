<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-x-3">
            <div class="flex-1">
                <h2 class="grid flex-1 text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    System Status
                </h2>
            </div>
            <div class="flex gap-x-3">
                <x-filament::link :href="url('/horizon')" icon="heroicon-m-server" color="gray" target="_blank">
                    Horizon
                </x-filament::link>
                <x-filament::link :href="url('/api/v1/admin/debug/config')" icon="heroicon-m-bug-ant" color="gray" target="_blank">
                    Debug
                </x-filament::link>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Environment</p>
                <p class="text-lg font-semibold {{ $status['app_env'] === 'production' ? 'text-danger-500' : 'text-success-500' }}">
                    {{ strtoupper($status['app_env'] ?? 'N/A') }}
                </p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Debug Mode</p>
                <p class="text-lg font-semibold {{ ($status['app_debug'] ?? false) ? 'text-danger-500' : 'text-success-500' }}">
                    {{ ($status['app_debug'] ?? false) ? 'ON' : 'OFF' }}
                </p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">AI Service</p>
                <p class="text-lg font-semibold {{ ($status['ai_service_env'] ?? 'mock') === 'real' ? 'text-primary-500' : 'text-gray-500' }}">
                    {{ strtoupper($status['ai_service_env'] ?? 'N/A') }}
                </p>
            </div>
            <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Instance ID</p>
                <p class="text-lg font-semibold text-gray-950 dark:text-white" title="{{ $status['instance_id'] ?? 'N/A' }}">
                    {{ \Illuminate\Support\Str::limit($status['instance_id'] ?? 'N/A', 15) }}
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
