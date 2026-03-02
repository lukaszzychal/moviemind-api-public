<x-filament::widget>
    <x-filament::section>
        <x-slot name="heading">
            Active Workers (Horizon)
        </x-slot>

        @if($supervisors->isEmpty() && $masters->isEmpty())
            <div class="flex items-center justify-center p-4 text-gray-500">
                <span>No active workers found. Is Horizon running?</span>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Supervisor Name</th>
                            <th scope="col" class="px-6 py-3">Status</th>
                            <th scope="col" class="px-6 py-3">Processes</th>
                            <th scope="col" class="px-6 py-3">Queues</th>
                            <th scope="col" class="px-6 py-3">Environment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($supervisors as $supervisor)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {{ $supervisor->name }}
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusColor = match($supervisor->status) {
                                            'running' => 'text-success-600 dark:text-success-400',
                                            'paused' => 'text-warning-600 dark:text-warning-400',
                                            default => 'text-danger-600 dark:text-danger-400',
                                        };
                                        $badgeColor = match($supervisor->status) {
                                            'running' => 'success',
                                            'paused' => 'warning',
                                            default => 'danger',
                                        };
                                    @endphp
                                    <x-filament::badge :color="$badgeColor">
                                        {{ ucfirst($supervisor->status) }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-6 py-4">
                                    {{ count($supervisor->processes) }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(\Illuminate\Support\Arr::wrap($supervisor->options['queue'] ?? []) as $queue)
                                            <span class="bg-gray-100 text-gray-800 text-xs font-medium me-2 px-2.5 py-0.5 rounded dark:bg-gray-900 dark:text-gray-300">
                                                {{ $queue }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                     {{ $supervisor->options['env'] ?? 'N/A' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament::widget>
