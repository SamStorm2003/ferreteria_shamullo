<?php

namespace App\Filament\Inventario\Resources\MovimientoInventarioResource\Pages;

use App\Filament\Inventario\Resources\MovimientoInventarioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\StockAlmacen;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class EditMovimientoInventario extends EditRecord
{
    protected static string $resource = MovimientoInventarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    $this->revertStock($record);
                })
                ->visible(fn() => Auth::user()->hasRole('Super Admin')),
        ];
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!Auth::user()->hasRole('Super Admin')) {
            $data['idAlmacen'] = Auth::user()->idAlmacen;
        }

        $original = $this->record->getOriginal();
        $originalStock = StockAlmacen::where('idProducto', $original['idProducto'])
            ->where('idAlmacen', $original['idAlmacen'])
            ->lockForUpdate()
            ->first();
        $revertQuantity = $originalStock ? match ($original['tipo']) {
            'entrada' => $originalStock->cantidad - $original['cantidad'],
            'salida' => $originalStock->cantidad + $original['cantidad'],
            'ajuste' => $originalStock->cantidad - $original['cantidad'],
            default => $originalStock->cantidad,
        } : 0;
        $newStock = StockAlmacen::where('idProducto', $data['idProducto'])
            ->where('idAlmacen', $data['idAlmacen'])
            ->lockForUpdate()
            ->first();

        if (!$newStock && $data['tipo'] === 'salida') {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No hay stock del producto en el almacén seleccionado.')
                ->send();
            $this->halt();
        }
        $currentStock = $newStock ? $newStock->cantidad : 0;
        if ($original['idProducto'] == $data['idProducto'] && $original['idAlmacen'] == $data['idAlmacen']) {
            $currentStock = $revertQuantity;
        }

        $newQuantity = match ($data['tipo']) {
            'entrada' => $currentStock + $data['cantidad'],
            'salida' => $currentStock - $data['cantidad'],
            'ajuste' => $currentStock + $data['cantidad'],
            default => $currentStock,
        };

        if ($newQuantity < 0) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('El stock no puede ser negativo tras la edición.')
                ->send();
            $this->halt();
        }
        if ($originalStock && ($original['idProducto'] != $data['idProducto'] || $original['idAlmacen'] != $data['idAlmacen'])) {
            if ($revertQuantity < 0) {
                Notification::make()
                    ->danger()
                    ->title('Error')
                    ->body('El stock no puede ser negativo al revertir el movimiento original.')
                    ->send();
                $this->halt();
            }
            $originalStock->update(['cantidad' => $revertQuantity]);
        }

        return $data;
    }

    protected function afterSave(): void
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

    protected function beforeDelete(): void
    {
        $this->revertStock($this->record);
    }

    protected function revertStock($record): void
    {
        try {
            DB::transaction(function () use ($record) {
                $stock = StockAlmacen::where('idProducto', $record->idProducto)
                    ->where('idAlmacen', $record->idAlmacen)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $newQuantity = match ($record->tipo) {
                        'entrada' => $stock->cantidad - $record->cantidad,
                        'salida' => $stock->cantidad + $record->cantidad,
                        'ajuste' => $stock->cantidad - $record->cantidad,
                        default => $stock->cantidad,
                    };

                    if ($newQuantity < 0) {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body('El stock no puede ser negativo al eliminar el movimiento.')
                            ->send();
                        throw new \Exception('Stock cannot be negative.');
                    }

                    $stock->update(['cantidad' => $newQuantity]);
                }
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

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Movimiento actualizado')
            ->body('El movimiento de inventario se ha actualizado y el stock se ha ajustado.');
    }

    protected function getDeletedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Movimiento eliminado')
            ->body('El movimiento de inventario se ha eliminado y el stock se ha revertido.');
    }
}
