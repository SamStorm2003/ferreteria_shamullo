<?php

namespace App\Services\Facturacion;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\Venta;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class FacturaVentaService
{
    public function generarDesdeVenta(Venta $venta)
    {
        $apiUrl = config('app.arkfacture_api_url');
        $missingConfig = $this->missingConfig($apiUrl);

        if ($missingConfig !== '') {
            $this->notify('Configuracion de facturacion incompleta', 'Faltan valores en .env: ' . $missingConfig);

            return null;
        }

        try {
            $response = Http::timeout(30)->post($apiUrl, $this->buildPayload($venta));

            if ($response->status() === 200) {
                $numeroFactura = $response->header('X-Numero-Factura') ?? 'factura_' . $venta->idVenta;
                $pdfContent = $response->body();

                return response()->streamDownload(
                    fn() => print($pdfContent),
                    'factura_' . $numeroFactura . '.pdf',
                    ['Content-Type' => 'application/pdf']
                );
            }

            $this->notify(
                'Error al generar factura (Codigo: ' . $response->status() . ')',
                $this->resolveErrorMessage($response->status(), $response->json()['error'] ?? 'Error desconocido')
            );
        } catch (\Exception $exception) {
            $this->notify('Error al conectar con la API', $exception->getMessage());
        }

        return null;
    }

    private function missingConfig(?string $apiUrl): string
    {
        return collect([
            'ARKFACTURE_API_URL' => $apiUrl,
            'NIT' => config('app.nit'),
            'RAZON_SOCIAL' => config('app.razon_social'),
            'ARKFACTURE_CLAVE_SECRETA' => config('app.arkfacture_clave_secreta'),
        ])->filter(fn($value) => blank($value))->keys()->implode(', ');
    }

    private function buildPayload(Venta $venta): array
    {
        $cliente = $venta->clienteUsuario ?? $venta->clienteExterno;
        $detalles = $venta->detalles->map(fn($detalle) => $this->mapDetalle($detalle))->toArray();
        $pago = $venta->pagos->first();

        return [
            'nit' => config('app.nit'),
            'razon_social' => config('app.razon_social'),
            'sucursal' => Almacen::find($venta->detalles->first()->idAlmacen)?->nombre ?? 'Sucursal Principal',
            'clave_secreta' => config('app.arkfacture_clave_secreta'),
            'codigo_autorizacion' => 'AUTORIZA-001',
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_limite_emision' => now()->addDays(30)->format('Y-m-d'),
            'codigo_control' => 'CTRL-' . $venta->idVenta,
            'tipo_emision' => 'en linea',
            'modalidad' => 'electronica en linea',
            'estado_envio' => $venta->envio?->estado_envio ?? 'pendiente',
            'total' => $venta->total,
            'id_venta' => $venta->idVenta,
            'numero_factura' => 'INV-' . $venta->idVenta,
            'usuario_atendio' => $venta->vendedor?->name ?? Auth::user()->name,
            'encargado_empresa' => config('app.encargado_empresa'),
            'telefono_empresa' => config('app.telefono_empresa'),
            'cliente_nombre' => $cliente->name ?? $cliente->nombre ?? null,
            'cliente_nit_ci' => $cliente->documento_identidad ?? null,
            'cliente_direccion' => $cliente->direccion ?? ($venta->envio?->direccion_envio ?? null),
            'cliente_telefono' => $cliente->telefono ?? null,
            'detalle_venta' => $detalles,
            'descuentos' => array_sum(array_column($detalles, 'descuento')),
            'impuestos' => config('app.impuestos'),
            'monto_pagado' => $pago->monto ?? $venta->total,
            'moneda' => 'BOB',
            'metodo_pago' => $pago->metodo ?? 'efectivo',
            'fecha_impresion' => now()->format('Y-m-d'),
            'notas' => 'Factura generada desde el sistema de ventas',
            'tipo_entrega' => $venta->tipo_entrega === 'envio' ? 'delivery' : 'retiro en tienda',
            'numero_atencion_cliente' => 'ATN-' . $venta->idVenta,
        ];
    }

    private function mapDetalle($detalle): array
    {
        $producto = Producto::with('promocion')->find($detalle->idProducto);
        $descuento = 0;

        if ($producto->promocion && $producto->promocion->estado === 'activo' && now()->between($producto->promocion->fecha_inicio, $producto->promocion->fecha_fin)) {
            $descuento = $producto->promocion->descuento * $detalle->cantidad;
        }

        return [
            'descripcion' => $producto->nombre,
            'cantidad' => $detalle->cantidad,
            'precio_unitario' => $detalle->precio_unitario,
            'descuento' => $descuento,
        ];
    }

    private function resolveErrorMessage(int $status, string $errorMessage): string
    {
        return match ($status) {
            401 => 'Clave secreta invalida.',
            404 => 'Empresa no encontrada.',
            429 => 'Sistema saturado. Intente de nuevo mas tarde.',
            500 => 'Error interno al procesar la factura: ' . $errorMessage,
            504 => 'Tiempo de espera excedido.',
            default => $errorMessage,
        };
    }

    private function notify(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->send();
    }
}
