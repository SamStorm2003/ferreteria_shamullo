<?php

namespace App\Filament\Vendedor\Resources\FacturaResource\Pages;

use App\Filament\Vendedor\Resources\FacturaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFactura extends EditRecord
{
    protected static string $resource = FacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
