<?php

namespace App\Filament\Inventario\Resources\MovimientoInventarioResource\Pages;

use App\Filament\Inventario\Resources\MovimientoInventarioResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\StockAlmacen;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class CreateMovimientoInventario extends CreateRecord
{
    protected static string $resource = MovimientoInventarioResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['idUsuario'] = Auth::id();

        if (!Auth::user()->hasRole('Super Admin')) {
            $data['idAlmacen'] = Auth::user()->idAlmacen;
        }

        $stock = StockAlmacen::where('idProducto', $data['idProducto'])
            ->where('idAlmacen', $data['idAlmacen'])
            ->first();

        if (!$stock && $data['tipo'] === 'salida') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No hay stock del producto en el almacén seleccionado.')
                ->send();
            $this->halt();
        }

        $currentStock = $stock ? $stock->cantidad : 0;

        if ($data['tipo'] === 'salida') {
            if ($currentStock < $data['cantidad']) {
                Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body('Stock insuficiente en el almacén seleccionado.')
                    ->send();
                $this->halt();
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->updateStock($this->record);
    }

    protected function updateStock($record): void
    {
        try {
            DB::transaction(function () use ($record) {
                $stock = StockAlmacen::where('idProducto', $record->idProducto)
                    ->where('idAlmacen', $record->idAlmacen)
                    ->lockForUpdate()
                    ->first();

                if (!$stock) {
                    $stock = StockAlmacen::create([
                        'idProducto' => $record->idProducto,
                        'idAlmacen' => $record->idAlmacen,
                        'cantidad' => 0,
                        'costo_unitario' => $record->costo_unitario,
                        'precio_venta' => 0,
                    ]);
                }

                $newQuantity = match ($record->tipo) {
                    'entrada' => $stock->cantidad + $record->cantidad,
                    'salida' => $stock->cantidad - $record->cantidad,
                    'ajuste' => $stock->cantidad + $record->cantidad,
                    default => $stock->cantidad,
                };

                if ($newQuantity < 0) {
                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body('El stock no puede ser negativo.')
                        ->send();
                    throw new \Exception('Stock cannot be negative.');
                }

                $stock->update([
                    'cantidad' => $newQuantity,
                    'costo_unitario' => $record->costo_unitario,
                ]);
            });
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body($e->getMessage())
                ->send();
            throw $e;
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Movimiento creado')
            ->body('El movimiento de inventario se ha registrado y el stock se ha actualizado.');
    }
}
