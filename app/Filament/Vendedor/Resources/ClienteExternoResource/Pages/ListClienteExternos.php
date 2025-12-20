<?php

namespace App\Filament\Vendedor\Resources\ClienteExternoResource\Pages;

use App\Filament\Vendedor\Resources\ClienteExternoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use GuzzleHttp\Client;

class ListClienteExternos extends ListRecords
{
    protected static string $resource = ClienteExternoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Registrar Cliente Externo'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ClienteExternoResource\Widgets\Clientesexternos::class,
        ];
    }
}
