<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\OutgoingWebhookResource;
use App\Services\WebhookSubscriptionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class RegisteredWebhooks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Webhooks';

    protected static ?string $navigationLabel = 'Registered webhooks';

    protected static ?string $title = 'Registered webhooks';

    protected static string $view = 'filament.pages.registered-webhooks';

    protected static ?int $navigationSort = 1;

    public function getRegisteredList(): array
    {
        return app(WebhookSubscriptionService::class)->listRegistered();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add')
                ->label('Add webhook')
                ->url(OutgoingWebhookResource::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }

    public function deleteSubscription(string $id): void
    {
        try {
            app(WebhookSubscriptionService::class)->deleteSubscription($id);
            Notification::make()
                ->title('Webhook subscription removed')
                ->success()
                ->send();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            Notification::make()
                ->title('Subscription not found')
                ->danger()
                ->send();
        }
    }

    public function updateSubscription(string $id, array $data): void
    {
        try {
            app(WebhookSubscriptionService::class)->updateSubscription(
                $id,
                $data['event_type'],
                $data['url']
            );
            Notification::make()
                ->title('Webhook subscription updated')
                ->success()
                ->send();
        } catch (\Illuminate\Validation\ValidationException $e) {
            Notification::make()
                ->title('Validation failed')
                ->body(implode(' ', array_map(fn ($msgs) => implode(' ', $msgs), $e->errors())))
                ->danger()
                ->send();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            Notification::make()
                ->title('Subscription not found')
                ->danger()
                ->send();
        }
    }
}
