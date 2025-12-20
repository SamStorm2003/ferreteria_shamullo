<?php

namespace App\Filament\Vendedor\Resources\ClienteExternoResource\Pages;

use App\Filament\Vendedor\Resources\ClienteExternoResource;
use App\Models\ClienteExterno;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditClienteExterno extends EditRecord
{
    protected static string $resource = ClienteExternoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        if (ClienteExterno::where('documento_identidad', $data['documento_identidad'])
            ->where('id', '!=', $this->record->id)
            ->exists()
        ) {
            Notification::make()
                ->title('Error')
                ->body('El documento de identidad ya está registrado en otro usuario.')
                ->danger()
                ->send();
            throw new \Exception('El documento de identidad ya está registrado en otro usuario.');
        }

        return $data;
    }
}
