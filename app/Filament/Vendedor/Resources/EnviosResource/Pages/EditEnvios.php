<?php

namespace App\Filament\Vendedor\Resources\EnviosResource\Pages;

use App\Filament\Vendedor\Resources\EnviosResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEnvios extends EditRecord
{
    protected static string $resource = EnviosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //  Actions\DeleteAction::make(),
        ];
    }
}
