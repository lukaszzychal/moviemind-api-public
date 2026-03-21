<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Models\ApiKey;
use App\Services\ApiKeyService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ApiKey $record */
        /** @var ApiKeyService $service */
        $service = app(ApiKeyService::class);

        $newPlaintextKey = isset($data['public_plaintext_key']) ? trim((string) $data['public_plaintext_key']) : null;

        // Always re-hash when is_public=true and public_plaintext_key is set.
        // This ensures the key hash is always in sync with the plaintext key,
        // even if admin saves the form without changing the value.
        if (! empty($newPlaintextKey) && ($data['is_public'] ?? false)) {
            $data['key'] = $service->hashKey($newPlaintextKey);
            $data['key_prefix'] = $service->extractPrefix($newPlaintextKey);
        }

        $record->update($data);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
