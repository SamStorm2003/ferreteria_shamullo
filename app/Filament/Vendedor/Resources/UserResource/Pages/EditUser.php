<?php

namespace App\Filament\Vendedor\Resources\UserResource\Pages;

use App\Filament\Vendedor\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Models\User;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //  Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (User::where('email', $data['email'])
            ->where('id', '!=', $this->record->id)
            ->exists()
        ) {
            Notification::make()
                ->title('Error')
                ->body('El correo electrónico ya está registrado en otro usuario.')
                ->danger()
                ->send();
            throw new \Exception('El correo electrónico ya está registrado en otro usuario.');
        }
        return $data;
    }
}
