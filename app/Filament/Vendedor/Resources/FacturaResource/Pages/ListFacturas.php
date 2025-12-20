<?php

namespace App\Filament\Vendedor\Resources\FacturaResource\Pages;

use App\Filament\Vendedor\Resources\FacturaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFacturas extends ListRecords
{
    protected static string $resource = FacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //    Actions\CreateAction::make(),
        ];
    }
}
