<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Services\ApiKeyService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var ApiKeyService $service */
        $service = app(ApiKeyService::class);

        $result = $service->createKey(
            name: $data['name'],
            planId: $data['plan_id'] ?? null,
            userId: $data['user_id'] ?? null,
            expiresAt: isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null,
            isPublic: $data['is_public'] ?? false,
            publicPlaintextKey: $data['public_plaintext_key'] ?? null,
        );

        // Flash the key to the session to display it
        session()->flash('new_api_key', $result['key']);

        return $result['apiKey'];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Show persistent notification with the key
        $key = session('new_api_key');

        if ($key) {
            Notification::make()
                ->title('API Key Created Successfully')
                ->body("Your new API key is: **{$key}**\n\nPlease copy it now. You won't be able to see it again.")
                ->success()
                ->persistent()
                ->send();
        }
    }
}
