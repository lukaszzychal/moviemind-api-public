<x-filament-panels::page>
    @php
        $list = $this->getRegisteredList();
    @endphp

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                <thead class="divide-y divide-gray-200 dark:divide-white/5">
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">Event type</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">URL</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-start text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">Source</th>
                        <th class="fi-ta-header-cell px-3 py-3.5 text-end text-sm font-semibold text-gray-950 dark:text-white sm:first-of-type:ps-6 sm:last-of-type:pe-6">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse ($list as $row)
                        <tr class="fi-ta-row">
                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                <span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-custom bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-300 dark:ring-gray-400/20">{{ $row['event_type'] }}</span>
                            </td>
                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                <span class="text-sm text-gray-950 dark:text-white">{{ Str::limit($row['url'], 60) }}</span>
                            </td>
                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3">
                                @if ($row['source'] === 'env')
                                    <span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-custom bg-gray-50 text-gray-600 ring-gray-600/20 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">env</span>
                                @else
                                    <span class="inline-flex items-center gap-x-1.5 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset fi-color-custom bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/20">subscription</span>
                                @endif
                            </td>
                            <td class="fi-ta-cell p-0 first-of-type:ps-1 last-of-type:pe-1 sm:first-of-type:ps-3 sm:last-of-type:pe-3 text-end">
                                @if (($row['source'] ?? '') === 'subscription' && !empty($row['id']))
                                    <x-filament::button size="sm" color="danger" wire:click="deleteSubscription('{{ $row['id'] }}')" wire:confirm="Remove this webhook subscription?">
                                        Delete
                                    </x-filament::button>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No webhook URLs registered. Add URLs in .env (WEBHOOK_URL_*) or use "Add webhook".</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
