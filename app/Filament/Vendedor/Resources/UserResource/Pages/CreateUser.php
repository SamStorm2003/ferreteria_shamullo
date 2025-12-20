<?php

namespace App\Filament\Vendedor\Resources\UserResource\Pages;

use App\Filament\Vendedor\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Models\User;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (User::where('email', $data['email'])->exists()) {
            Notification::make()
                ->title('Error')
                ->body('El correo electrónico ya está registrado.')
                ->danger()
                ->send();
            throw new \Exception('El correo electrónico ya está registrado.');
        }
        return $data;
    }
}
