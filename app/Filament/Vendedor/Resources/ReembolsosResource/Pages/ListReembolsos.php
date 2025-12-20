<?php

namespace App\Filament\Vendedor\Resources\ReembolsosResource\Pages;

use App\Filament\Vendedor\Resources\ReembolsosResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListReembolsos extends ListRecords
{
    protected static string $resource = ReembolsosResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Crear Reembolso'),
        ];
    }
     protected function getHeaderWidgets(): array
    {
        return [
            ReembolsosResource\Widgets\Rembolsoswidget::class,
        ];
    }
}
