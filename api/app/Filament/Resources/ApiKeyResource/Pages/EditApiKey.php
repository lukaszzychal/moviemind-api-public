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

        $newPlaintextKey = $data['public_plaintext_key'] ?? null;
        $existingPlaintextKey = $record->public_plaintext_key;

        // If public_plaintext_key was changed (or newly set), re-hash the key
        if ($newPlaintextKey && $newPlaintextKey !== $existingPlaintextKey) {
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
