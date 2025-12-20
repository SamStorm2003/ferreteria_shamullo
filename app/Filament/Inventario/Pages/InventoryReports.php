<?php

namespace App\Filament\Inventario\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Illuminate\Http\Request;
use Filament\Notifications\Notification;

class InventoryReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $pluralLabel = 'Reportes de Inventario';
    protected static ?string $navigationLabel = 'Reportes de Inventario';
    protected static string $view = 'filament.inventario.pages.inventory-reports';

    protected function getActions(): array
    {
        return [
            Action::make('downloadStock')
                ->label('Reporte Stock Almacenes')
                ->action(function () {
                    Notification::make()
                        ->title('Generando reporte de stock de almacenes')
                        ->success()
                        ->send();

                    return redirect()->route('reportes.inventario', ['tipo' => 'stock']);
                }),

            Action::make('downloadProductos')
                ->label('Reporte Productos')
                ->action(function () {
                    Notification::make()
                        ->title('Generando reporte de productos')
                        ->success()
                        ->send();

                    return redirect()->route('reportes.inventario', ['tipo' => 'productos']);
                }),
        ];
    }
}
