<?php

declare(strict_types=1);

namespace App\Filament\Resources\OutgoingWebhookResource\Pages;

use App\Filament\Pages\RegisteredWebhooks;
use App\Filament\Resources\OutgoingWebhookResource;
use App\Services\WebhookSubscriptionService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Create page for adding a webhook subscription (endpoint to receive outgoing webhooks).
 * Uses WebhookSubscriptionService; redirects to Registered webhooks list.
 */
class CreateOutgoingWebhook extends CreateRecord
{
    protected static string $resource = OutgoingWebhookResource::class;

    public function form(Form $form): Form
    {
        $eventTypeOptions = array_combine(
            $keys = array_keys(config('webhooks.outgoing_urls', [])),
            $keys
        ) ?: [];

        return $form
            ->schema([
                Forms\Components\Section::make('Add webhook endpoint')
                    ->description('This URL will receive POST requests when the selected event occurs (together with any URLs from env).')
                    ->schema([
                        Forms\Components\Select::make('event_type')
                            ->label('Event type')
                            ->required()
                            ->options($eventTypeOptions)
                            ->searchable()
                            ->helperText('Event that triggers the webhook (e.g. movie.generation.completed).'),
                        Forms\Components\TextInput::make('url')
                            ->label('Webhook URL')
                            ->required()
                            ->url()
                            ->maxLength(500)
                            ->helperText('Full URL to receive POST (e.g. https://example.com/webhook).')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return app(WebhookSubscriptionService::class)->addSubscription(
            $data['event_type'],
            $data['url']
        );
    }

    protected function getRedirectUrl(): string
    {
        return RegisteredWebhooks::getUrl();
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Webhook endpoint added';
    }

    protected static bool $canCreateAnother = false;
}
