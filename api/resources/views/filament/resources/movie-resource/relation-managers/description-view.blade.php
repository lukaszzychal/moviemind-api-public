<div class="space-y-6">
    <div>
        <h3 class="text-lg font-semibold mb-2">Description</h3>
        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->text }}</p>
        </div>
    </div>

    <div>
        <h3 class="text-lg font-semibold mb-2">Metadata</h3>
        <dl class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Locale</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->locale->value }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Context Tag</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->context_tag?->value ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">AI Model</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->ai_model ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Origin</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->origin->value }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created At</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $record->created_at->format('Y-m-d H:i:s') }}</dd>
            </div>
        </dl>
    </div>

    @if($metrics)
        <div>
            <h3 class="text-lg font-semibold mb-2">AI Generation Metrics</h3>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tokens</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $metrics->total_tokens ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Prompt Tokens</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $metrics->prompt_tokens ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Completion Tokens</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $metrics->completion_tokens ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parsing Successful</dt>
                    <dd class="mt-1 text-sm">
                        @if($metrics->parsing_successful)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                Yes
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                No
                            </span>
                        @endif
                    </dd>
                </div>
                @if($metrics->parsing_errors)
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Parsing Errors</dt>
                        <dd class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $metrics->parsing_errors }}</dd>
                    </div>
                @endif
                @if($metrics->validation_errors)
                    <div class="col-span-2">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Validation Errors</dt>
                        <dd class="mt-1 text-sm">
                            <pre class="bg-gray-100 dark:bg-gray-800 p-2 rounded text-xs overflow-auto">{{ json_encode($metrics->validation_errors, JSON_PRETTY_PRINT) }}</pre>
                        </dd>
                    </div>
                @endif
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Response Time</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $metrics->response_time_ms ? $metrics->response_time_ms . ' ms' : 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Data Format</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $metrics->data_format ?? 'N/A' }}</dd>
                </div>
                @if($metrics->token_savings_vs_json)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Token Savings vs JSON</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ number_format($metrics->token_savings_vs_json, 2) }}%</dd>
                    </div>
                @endif
            </dl>
        </div>
    @else
        <div>
            <h3 class="text-lg font-semibold mb-2">AI Generation Metrics</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">No metrics available for this description.</p>
        </div>
    @endif
</div>
