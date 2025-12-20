<?php

namespace App\Filament\Vendedor\Resources\ClienteExternoResource\Pages;

use App\Filament\Vendedor\Resources\ClienteExternoResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\ClienteExterno;

class CreateClienteExterno extends CreateRecord
{
    protected static string $resource = ClienteExternoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['documento_identidad']) && ClienteExterno::where('documento_identidad', $data['documento_identidad'])->exists()) {
            Notification::make()
                ->title('Error')
                ->body('El documento de identidad ya está registrado.')
                ->danger()
                ->send();
            throw new \Exception('El documento de identidad ya está registrado.');
        }

        return $data;
    }
}
