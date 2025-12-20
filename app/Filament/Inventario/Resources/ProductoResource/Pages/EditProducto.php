<?php

namespace App\Filament\Inventario\Resources\ProductoResource\Pages;

use App\Filament\Inventario\Resources\ProductoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Exception;

class EditProducto extends EditRecord
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['url_imagen']) && is_string($data['url_imagen'])) {
            $bucketUrl = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
            $data['url_imagen'] = $bucketUrl . $data['url_imagen'];
        } else {
            unset($data['url_imagen']);
        }
        return $data;
    }

    protected function afterSave(): void
    {
        try {
            $producto = $this->record;
            $data = $this->data;
            $idAlmacen = $data['idAlmacen'];
            $existingStock = \App\Models\StockAlmacen::where('idProducto', $producto->idProducto)
                ->where('idAlmacen', $idAlmacen)
                ->first();
            if ($existingStock) {
                $diferenciaCantidad = $data['cantidad'] - $existingStock->cantidad;
                $existingStock->update([
                    'cantidad' => $data['cantidad'],
                    'costo_unitario' => $data['costo_unitario'],
                    'precio_venta' => $data['precio_venta'],
                ]);
                if ($diferenciaCantidad != 0) {
                    \App\Models\MovimientoInventario::create([
                        'idProducto' => $producto->idProducto,
                        'idAlmacen' => $idAlmacen,
                        'tipo' => $diferenciaCantidad > 0 ? 'entrada' : 'ajuste',
                        'cantidad' => abs($diferenciaCantidad),
                        'costo_unitario' => $data['costo_unitario'],
                        'fecha' => now(),
                        'idUsuario' => Auth::id(),
                        'motivo' => 'Ajuste de stock al editar producto',
                    ]);
                }
            } else {
                \App\Models\StockAlmacen::create([
                    'idProducto' => $producto->idProducto,
                    'idAlmacen' => $idAlmacen,
                    'cantidad' => $data['cantidad'],
                    'costo_unitario' => $data['costo_unitario'],
                    'precio_venta' => $data['precio_venta'],
                ]);
                \App\Models\MovimientoInventario::create([
                    'idProducto' => $producto->idProducto,
                    'idAlmacen' => $idAlmacen,
                    'tipo' => 'entrada',
                    'cantidad' => $data['cantidad'],
                    'costo_unitario' => $data['costo_unitario'],
                    'fecha' => now(),
                    'idUsuario' => Auth::id(),
                    'motivo' => 'Stock añadido al editar producto',
                ]);
            }
            Notification::make()
                ->title('Producto actualizado')
                ->body('El producto y su stock se actualizaron correctamente.')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Error al actualizar el producto')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
            throw $e;
        }
    }

    protected function fillForm(): void
    {
        try {
            parent::fillForm();
            $producto = $this->record;
            $stock = $producto->stockAlmacenes->first();
            if ($stock) {
                $this->form->fill([
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'categoria' => $producto->categoria,
                    'marca' => $producto->marca,
                    'url_imagen' => $producto->url_imagen,
                    'idProveedor' => $producto->idProveedor,
                    'estado' => $producto->estado,
                    'idAlmacen' => $stock->idAlmacen,
                    'cantidad' => $stock->cantidad,
                    'costo_unitario' => $stock->costo_unitario,
                    'precio_venta' => $stock->precio_venta,
                ]);
            } else {
                $this->form->fill([
                    'nombre' => $producto->nombre,
                    'codigo' => $producto->codigo,
                    'descripcion' => $producto->descripcion,
                    'categoria' => $producto->categoria,
                    'marca' => $producto->marca,
                    'url_imagen' => $producto->url_imagen,
                    'idProveedor' => $producto->idProveedor,
                    'estado' => $producto->estado,
                ]);
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Error al cargar el formulario')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
