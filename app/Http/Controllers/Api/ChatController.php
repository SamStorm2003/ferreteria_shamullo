<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\StockAlmacen;
use App\Models\Almacen;
use App\Models\MovimientoInventario;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Promocion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class ChatController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'mensaje' => 'required|string|max:1000',
        ]);
        $mensaje = strtolower(trim($request->input('mensaje')));
        $response = $this->procesarMensaje($mensaje);

        return response()->json(['respuesta' => $response]);
    }

    protected function procesarMensaje(string $mensaje): string
    {
        if (str_contains($mensaje, 'vendieron') || str_contains($mensaje, 'más vendidos')) {
            return $this->recomendarProductos();
        }

        if (str_contains($mensaje, 'movimiento') || str_contains($mensaje, 'cambio de almacen')) {
            return $this->consultarMovimientosAlmacen();
        }

        if (str_contains($mensaje, 'compras') || str_contains($mensaje, 'compra')) {
            return $this->analizarCompras();
        }

        if (str_contains($mensaje, 'recomienda') || str_contains($mensaje, 'recomendar')) {
            return $this->recomendarProductos();
        }

        if (str_contains($mensaje, 'bajo stock') || str_contains($mensaje, 'reponer')) {
            return $this->consultarBajoStock();
        }

        if (str_contains($mensaje, 'inventario') || (str_contains($mensaje, 'stock') && !str_contains($mensaje, 'bajo'))) {
            return $this->consultarInventario($mensaje);
        }

        if (str_contains($mensaje, 'producto')) {
            return $this->consultarProductos($mensaje);
        }

        if (str_contains($mensaje, 'proveedor')) {
            return $this->consultarProveedor($mensaje);
        }

        if (str_contains($mensaje, 'pronóstico') || str_contains($mensaje, 'ventas') || str_contains($mensaje, 'predicción')) {
            return $this->pronosticarVentas();
        }

        if (str_contains($mensaje, 'promociones')) {
            return $this->consultarPromociones();
        }

        if (str_contains($mensaje, 'márgenes') || str_contains($mensaje, 'ganancia')) {
            return $this->analizarMargenes();
        }

        if (str_contains($mensaje, 'rotación') || str_contains($mensaje, 'inventario lento')) {
            return $this->analizarRotacionInventario();
        }

        return $this->consultarGeminiConContexto($mensaje);
    }

    private function obtenerContextoBaseDatos(): array
    {
        try {
            return Cache::remember('contexto_negocio_avanzado', 3600, function () {
                $ventasTotales = DetalleVenta::join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->select(
                        DB::raw('SUM(detalle_ventas.cantidad) as unidades_vendidas'),
                        DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio_unitario) as ingresos_totales')
                    )
                    ->first();
                $ventasMensuales = DetalleVenta::join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->groupBy(DB::raw('MONTHNAME(ventas.fecha), MONTH(ventas.fecha)'))
                    ->select(
                        DB::raw('MONTHNAME(ventas.fecha) as mes'),
                        DB::raw('SUM(detalle_ventas.cantidad) as unidades'),
                        DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio_unitario) as ingresos')
                    )
                    ->get()
                    ->map(fn($v) => ['mes' => $v->mes, 'unidades' => (int)$v->unidades, 'ingresos' => (float)$v->ingresos])
                    ->toArray();
                $productosTop = DetalleVenta::join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                    ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
                    ->leftJoin('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->groupBy('productos.idProducto', 'productos.nombre', 'categorias.nombre')
                    ->select(
                        'productos.nombre',
                        'categorias.nombre as categoria',
                        DB::raw('SUM(detalle_ventas.cantidad) as total_vendido'),
                        DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio_unitario) as ingresos')
                    )
                    ->orderByDesc('total_vendido')
                    ->limit(5)
                    ->get()
                    ->map(fn($p) => [
                        'nombre' => $p->nombre,
                        'categoria' => $p->categoria ?? 'Sin categoría',
                        'vendido' => (int)$p->total_vendido,
                        'ingresos' => (float)$p->ingresos
                    ])
                    ->toArray();
                $stockTotal = StockAlmacen::whereHas('producto', fn($q) => $q->where('estado', 'activo'))
                    ->select(
                        DB::raw('SUM(cantidad) as unidades_totales'),
                        DB::raw('SUM(cantidad * costo_unitario) as costo_total'),
                        DB::raw('SUM(cantidad * precio_venta) as valor_venta_total')
                    )
                    ->first();
                $bajoStock = StockAlmacen::join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                    ->join('almacens', 'stock_almacens.idAlmacen', '=', 'almacens.idAlmacen')
                    ->where('stock_almacens.cantidad', '<', 10)
                    ->where('productos.estado', 'activo')
                    ->select('productos.nombre', 'almacens.nombre as almacen', 'stock_almacens.cantidad')
                    ->limit(5)
                    ->get()
                    ->map(fn($s) => [
                        'nombre' => $s->nombre,
                        'almacen' => $s->almacen,
                        'cantidad' => (int)$s->cantidad
                    ])
                    ->toArray();
                $rotacionBaja = StockAlmacen::join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                    ->groupBy('productos.idProducto', 'productos.nombre')
                    ->select(
                        'productos.nombre',
                        DB::raw('SUM(stock_almacens.cantidad) as stock_total'),
                        DB::raw('(SELECT SUM(detalle_ventas.cantidad) 
                              FROM detalle_ventas 
                              JOIN ventas ON detalle_ventas.idVenta = ventas.idVenta 
                              WHERE detalle_ventas.idProducto = productos.idProducto 
                              AND ventas.fecha >= NOW() - INTERVAL 3 MONTH 
                              AND ventas.estado = "completada") as vendido')
                    )
                    ->havingRaw('vendido IS NULL OR vendido / stock_total < 0.5')
                    ->limit(3)
                    ->get()
                    ->map(fn($r) => [
                        'nombre' => $r->nombre,
                        'stock_total' => (int)$r->stock_total,
                        'vendido' => (int)($r->vendido ?? 0)
                    ])
                    ->toArray();
                $proveedoresTop = Compra::join('proveedors', 'compras.idProveedor', '=', 'proveedors.idProveedor')
                    ->where('compras.fecha', '>=', now()->subMonths(3))
                    ->where('compras.estado', 'completada')
                    ->groupBy('proveedors.idProveedor', 'proveedors.nombre')
                    ->select(
                        'proveedors.nombre',
                        DB::raw('COUNT(compras.idCompra) as total_compras'),
                        DB::raw('SUM(compras.total) as costo_total')
                    )
                    ->orderByDesc('costo_total')
                    ->limit(3)
                    ->get()
                    ->map(fn($p) => [
                        'nombre' => $p->nombre,
                        'compras' => (int)$p->total_compras,
                        'costo_total' => (float)$p->costo_total
                    ])
                    ->toArray();
                $comprasTotales = Compra::where('compras.fecha', '>=', now()->subMonths(3))
                    ->where('compras.estado', 'completada')
                    ->select(
                        DB::raw('SUM(total) as costo_total'),
                        DB::raw('COUNT(idCompra) as total_compras')
                    )
                    ->first();
                $promocionesActivas = Promocion::where('estado', 'activa')
                    ->where('fecha_fin', '>=', now())
                    ->select('nombre', 'descuento', 'fecha_fin')
                    ->limit(3)
                    ->get()
                    ->map(fn($p) => [
                        'nombre' => $p->nombre,
                        'descuento' => (float)$p->descuento,
                        'fecha_fin' => $p->fecha_fin->toDateString()
                    ])
                    ->toArray();
                $almacenes = Almacen::select('nombre', 'ubicacion')
                    ->get()
                    ->map(fn($a) => ['nombre' => $a->nombre, 'ubicacion' => $a->ubicacion])
                    ->toArray();
                $clientesTop = Venta::join('users', 'ventas.idUsuarioCliente', '=', 'users.id')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->groupBy('users.id', 'users.name')
                    ->select(
                        'users.name',
                        DB::raw('COUNT(ventas.idVenta) as total_ventas'),
                        DB::raw('SUM(ventas.total) as ingresos_totales')
                    )
                    ->orderByDesc('ingresos_totales')
                    ->limit(3)
                    ->get()
                    ->map(fn($c) => [
                        'nombre' => $c->name,
                        'ventas' => (int)$c->total_ventas,
                        'ingresos' => (float)$c->ingresos_totales
                    ])
                    ->toArray();
                $usuariosActivos = MovimientoInventario::join('users', 'movimiento_inventarios.idUsuario', '=', 'users.id')
                    ->where('movimiento_inventarios.fecha', '>=', now()->subMonths(3))
                    ->groupBy('users.id', 'users.name')
                    ->select(
                        'users.name',
                        DB::raw('COUNT(movimiento_inventarios.idMovimiento) as movimientos')
                    )
                    ->orderByDesc('movimientos')
                    ->limit(3)
                    ->get()
                    ->map(fn($u) => ['nombre' => $u->name, 'movimientos' => (int)$u->movimientos])
                    ->toArray();
                $margenesPromedio = StockAlmacen::join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                    ->where('productos.estado', 'activo')
                    ->select(
                        DB::raw('AVG(stock_almacens.precio_venta - stock_almacens.costo_unitario) / AVG(stock_almacens.precio_venta) * 100 as margen_promedio')
                    )
                    ->first();
                $clientesRepetidos = Venta::join('users', 'ventas.idUsuarioCliente', '=', 'users.id')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->groupBy('users.id')
                    ->havingRaw('COUNT(ventas.idVenta) > 1')
                    ->select(DB::raw('COUNT(DISTINCT users.id) as clientes_repetidos'))
                    ->first();
                $totalClientes = Venta::join('users', 'ventas.idUsuarioCliente', '=', 'users.id')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->select(DB::raw('COUNT(DISTINCT users.id) as total_clientes'))
                    ->first();
                $tasaRetencion = $totalClientes->total_clientes > 0
                    ? ($clientesRepetidos->clientes_repetidos / $totalClientes->total_clientes) * 100
                    : 0;
                $productosPorProveedor = DetalleVenta::join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                    ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
                    ->join('proveedors', 'productos.idProveedor', '=', 'proveedors.idProveedor')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->groupBy('proveedors.idProveedor', 'proveedors.nombre')
                    ->select(
                        'proveedors.nombre',
                        DB::raw('SUM(detalle_ventas.cantidad) as unidades_vendidas'),
                        DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio_unitario) as ingresos')
                    )
                    ->orderByDesc('ingresos')
                    ->limit(3)
                    ->get()
                    ->map(fn($p) => [
                        'proveedor' => $p->nombre,
                        'unidades' => (int)$p->unidades_vendidas,
                        'ingresos' => (float)$p->ingresos
                    ])
                    ->toArray();
                $movimientosInventario = MovimientoInventario::where('fecha', '>=', now()->subMonths(3))
                    ->groupBy('tipo')
                    ->select(
                        'tipo',
                        DB::raw('COUNT(idMovimiento) as total_movimientos'),
                        DB::raw('SUM(cantidad) as total_cantidad')
                    )
                    ->get()
                    ->map(fn($m) => [
                        'tipo' => $m->tipo,
                        'movimientos' => (int)$m->total_movimientos,
                        'cantidad' => (int)$m->total_cantidad
                    ])
                    ->toArray();
                $ventasPorCategoria = DetalleVenta::join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                    ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
                    ->leftJoin('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
                    ->where('ventas.fecha', '>=', now()->subMonths(3))
                    ->where('ventas.estado', 'completada')
                    ->groupBy('categorias.nombre')
                    ->select(
                        'categorias.nombre as categoria',
                        DB::raw('SUM(detalle_ventas.cantidad) as unidades'),
                        DB::raw('SUM(detalle_ventas.cantidad * detalle_ventas.precio_unitario) as ingresos')
                    )
                    ->orderByDesc('ingresos')
                    ->limit(5)
                    ->get()
                    ->map(fn($c) => [
                        'categoria' => $c->categoria ?? 'Sin categoría',
                        'unidades' => (int)$c->unidades,
                        'ingresos' => (float)$c->ingresos
                    ])
                    ->toArray();

                return [
                    'ventas' => [
                        'total_unidades' => (int)($ventasTotales->unidades_vendidas ?? 0),
                        'total_ingresos' => (float)($ventasTotales->ingresos_totales ?? 0),
                        'tendencia_mensual' => $ventasMensuales,
                        'productos_mas_vendidos' => $productosTop,
                        'ventas_por_categoria' => $ventasPorCategoria
                    ],
                    'inventario' => [
                        'unidades_totales' => (int)($stockTotal->unidades_totales ?? 0),
                        'costo_total' => (float)($stockTotal->costo_total ?? 0),
                        'valor_venta_total' => (float)($stockTotal->valor_venta_total ?? 0),
                        'productos_bajo_stock' => $bajoStock,
                        'productos_rotacion_baja' => $rotacionBaja,
                        'movimientos_inventario' => $movimientosInventario
                    ],
                    'proveedores' => [
                        'top_proveedores' => $proveedoresTop,
                        'productos_por_proveedor' => $productosPorProveedor
                    ],
                    'compras' => [
                        'costo_total' => (float)($comprasTotales->costo_total ?? 0),
                        'total_compras' => (int)($comprasTotales->total_compras ?? 0)
                    ],
                    'promociones' => [
                        'activas' => $promocionesActivas
                    ],
                    'almacenes' => $almacenes,
                    'clientes' => [
                        'top_clientes' => $clientesTop,
                        'tasa_retencion' => (float)$tasaRetencion
                    ],
                    'usuarios' => [
                        'activos' => $usuariosActivos
                    ],
                    'finanzas' => [
                        'margen_promedio' => (float)($margenesPromedio->margen_promedio ?? 0)
                    ],
                    'fecha_datos' => now()->toDateString()
                ];
            });
        } catch (QueryException $e) {
            return [
                'error' => 'Error en la consulta a la base de datos: ' . $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings()
            ];
        } catch (\Exception $e) {
            return ['error' => 'Error general: ' . $e->getMessage()];
        }
    }

    protected function consultarGeminiConContexto(string $mensaje): string
    {
        $usuario = request()->user()->id ?? request()->ip();
        $cacheKey = 'consulta_gemini_' . $usuario . '_' . now()->format('Y-m-d');
        $consultasRealizadas = Cache::get($cacheKey, 0);
        $limiteDiario = 10;
        if ($consultasRealizadas >= $limiteDiario) {
            return '⚠️ Has alcanzado el límite diario de ' . $limiteDiario . ' consultas a la IA. Intenta nuevamente mañana.';
        }
        Cache::put($cacheKey, $consultasRealizadas + 1, now()->endOfDay());
        $contexto = $this->obtenerContextoBaseDatos();
        if (isset($contexto['error'])) {
            return 'Error al obtener datos del negocio: ' . $contexto['error'];
        }
        $dias_periodo = 90;
        $semanas_periodo = $dias_periodo / 7;
        $unidades_semanales = $contexto['ventas']['total_unidades'] > 0
            ? round($contexto['ventas']['total_unidades'] / $semanas_periodo)
            : 0;
        $prompt = <<<EOD
**Contexto Empresarial Completo** (Datos de los últimos 3 meses, a menos que se especifique):
- **Ventas**:
  - Total: {$contexto['ventas']['total_unidades']} unidades, \${$contexto['ventas']['total_ingresos']} en ingresos.
  - Tendencia mensual: {$this->formatArray($contexto['ventas']['tendencia_mensual'])}.
  - Productos más vendidos: {$this->formatArray($contexto['ventas']['productos_mas_vendidos'])}.
- **Inventario**:
  - Total: {$contexto['inventario']['unidades_totales']} unidades, costo \${$contexto['inventario']['costo_total']}, valor de venta \${$contexto['inventario']['valor_venta_total']}.
  - Bajo stock: {$this->formatArray($contexto['inventario']['productos_bajo_stock'])}.
  - Rotación baja: {$this->formatArray($contexto['inventario']['productos_rotacion_baja'])}.
- **Proveedores**:
  - Top proveedores: {$this->formatArray($contexto['proveedores']['top_proveedores'])}.
- **Compras**:
  - Total: {$contexto['compras']['total_compras']} compras, \${$contexto['compras']['costo_total']} gastado.
- **Promociones**:
  - Activas: {$this->formatArray($contexto['promociones']['activas'])}.
- **Almacenes**:
  - Lista: {$this->formatArray($contexto['almacenes'])}.
- **Clientes**:
  - Top clientes: {$this->formatArray($contexto['clientes']['top_clientes'])}.
- **Usuarios (empleados)**:
  - Activos: {$this->formatArray($contexto['usuarios']['activos'])}.
- **Finanzas**:
  - Margen de ganancia promedio: {$contexto['finanzas']['margen_promedio']}%.
- **Fecha de datos**: {$contexto['fecha_datos']}.
**Consulta del usuario**: {$mensaje}
**Instrucciones para la respuesta**:
1. **Contexto inicial**: Reconoce que la empresa está en sus primeras etapas, con ventas nulas y un inventario mínimo. Usa esto como base para todas las recomendaciones.
2. **Análisis proactivo**: Identifica oportunidades (ej. alto margen de ganancia) y riesgos (ej. inventario sin rotación) basados en los datos, incluso si son limitados.
3. **Estrategias de arranque**: Sugiere acciones específicas para iniciar ventas, como:
   - Marketing local en "{$contexto['almacenes'][0]['ubicacion']}" (ej. redes sociales, volantes).
   - Promociones iniciales (ej. descuentos, paquetes de prueba).
   - Identificación de clientes potenciales (ej. ferreterías, talleres).
4. **Gestión de inventario**: Propón evaluar la demanda de los productos actuales y diversificar si es necesario. Sugiere reabastecimiento solo si hay señales de interés.
5. **Optimización de recursos**: Recomienda negociar con proveedores (ej. "hola") para costos claros y rentables, y evaluar la necesidad de más personal además de "Admin User".
6. **Enfoque financiero**: Usa el margen de ganancia teórico para proponer precios competitivos que atraigan clientes sin sacrificar rentabilidad.
7. **Crecimiento sostenible**: Ofrece ideas para adquirir y retener clientes (ej. programas de lealtad, seguimiento post-venta).
8. **Datos limitados**: Si faltan datos, haz supuestos razonables basados en el contexto (ej. demanda local en "{$contexto['almacenes'][0]['ubicacion']}") y explícalos.
9. **Tono y estilo**: Responde en un tono profesional, estratégico y orientado a resultados, con pasos claros y medibles.
**Formato de la respuesta**:
- **Análisis breve**: Resume la situación actual en 2-3 líneas.
- **Recomendaciones**: Proporciona al menos 3 acciones específicas, detalladas y prácticas.
- Usa listas o viñetas para claridad y facilidad de implementación.
EOD;
        $postUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . config('services.gemini.model') . ":generateContent?key=" . config('services.gemini.api_key');
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($postUrl, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]);
        $respuesta = $response->json();
        $textoRespuesta = $respuesta['candidates'][0]['content']['parts'][0]['text'] ?? 'No se obtuvo respuesta 😔.';
        $consultasRestantes = $limiteDiario - ($consultasRealizadas + 1);
        $textoRespuesta .= "\n\n(Te quedan " . $consultasRestantes . " consultas hoy.)";

        return $textoRespuesta;
    }

    private function formatArray(array $array): string
    {
        if (empty($array)) {
            return 'Ninguno';
        }

        $formatted = '';
        foreach ($array as $item) {
            if (is_array($item)) {
                $formatted .= '- ' . json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                $formatted .= '- ' . $item . "\n";
            }
        }
        return trim($formatted);
    }

    private function consultarInventario(string $mensaje): string
    {
        Cache::forget('inventario_general');

        $stocks = StockAlmacen::with([
            'producto' => function ($query) {
                $query->select('productos.idProducto', 'productos.nombre', 'productos.idCategoria')
                    ->with(['categoria' => function ($q) {
                        $q->select('idCategoria', 'nombre');
                    }]);
            },
            'almacen' => function ($query) {
                $query->select('idAlmacen', 'nombre');
            }
        ])
            ->select('idProducto', 'idAlmacen', 'cantidad')
            ->whereHas('producto', fn($q) => $q->where('estado', 'activo'))
            ->orderBy('cantidad', 'desc')
            ->limit(10)
            ->get();
        \Illuminate\Support\Facades\Log::debug('Stocks cargados:', $stocks->toArray());
        if ($stocks->isEmpty()) {
            return $this->consultarGeminiConContexto($mensaje);
        }
        $respuesta = "📦 **Inventario actual**:\n\n";
        foreach ($stocks as $stock) {
            $categoria = $stock->producto->categoria ? $stock->producto->categoria->nombre : 'Sin categoría';
            $respuesta .= "- {$stock->producto->nombre} ({$categoria}): {$stock->cantidad} unidades en {$stock->almacen->nombre}\n";
        }
        $respuesta .= "\n💡 Usa 'bajo stock' para productos que necesitan reponer o 'movimientos' para cambios recientes.";
        return $respuesta;
    }

    private function consultarProductos(string $mensaje): string
    {
        $search = trim(str_replace(['producto', 's'], '', $mensaje));
        $productos = Producto::where('descripcion', 'like', "%{$search}%")
            ->orWhereHas('categoria', fn($q) => $q->where('nombre', 'like', "%{$search}%"))
            ->where('estado', 'activo')
            ->select('idProducto', 'descripcion', 'idCategoria', 'estado')
            ->with([
                'categoria' => fn($q) => $q->select('idCategoria', 'nombre'),
                'stockAlmacenes' => fn($q) => $q->select('idProducto', DB::raw('SUM(cantidad) as total_cantidad'))->groupBy('idProducto')
            ])
            ->limit(5)
            ->get();

        if ($productos->isEmpty()) {
            return $this->consultarGeminiConContexto($mensaje);
        }

        $respuesta = "📋 **Productos encontrados**:\n\n";
        foreach ($productos as $producto) {
            $stock = $producto->stockAlmacenes->first()->total_cantidad ?? 0;
            $categoria = $producto->categoria ? $producto->categoria->nombre : 'Sin categoría';
            $respuesta .= "- {$producto->descripcion} ({$categoria}): {$stock} unidades, estado: {$producto->estado}\n";
        }
        return $respuesta;
    }

    private function consultarProveedor(string $mensaje): string
    {
        $search = trim(str_replace(['proveedor', 'es'], '', $mensaje));
        $proveedores = Proveedor::where('nombre', 'like', "%{$search}%")
            ->where('estado', 'activo')
            ->select('nombre', 'contacto', 'telefono', 'correo', 'estado')
            ->limit(5)
            ->get();

        if ($proveedores->isEmpty()) {
            return $this->consultarGeminiConContexto($mensaje);
        }

        $respuesta = "📞 **Proveedores encontrados**:\n\n";
        foreach ($proveedores as $prov) {
            $respuesta .= "- {$prov->nombre}, contacto: {$prov->contacto}, tel: {$prov->telefono}, correo: {$prov->correo}\n";
        }
        $respuesta .= "\n💡 Útil para reponer productos con bajo stock.";
        return $respuesta;
    }

    private function recomendarProductos(): string
    {
        $populares = Cache::remember('productos_populares', 3600, function () {
            return DetalleVenta::select('productos.descripcion', 'categorias.nombre as categoria', DB::raw('SUM(detalle_ventas.cantidad) as total_vendido'))
                ->join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
                ->join('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
                ->where('ventas.fecha', '>=', now()->subMonths(3))
                ->where('ventas.estado', 'completada')
                ->where('productos.estado', 'activo')
                ->groupBy('productos.idProducto', 'productos.descripcion', 'categorias.nombre')
                ->orderByDesc('total_vendido')
                ->limit(5)
                ->get();
        });

        if ($populares->isEmpty()) {
            return "No hay datos de ventas para recomendar 😔. Prueba con 'inventario' para ver existencias.";
        }

        $respuesta = "🌟 **Productos más vendidos**:\n\n";
        foreach ($populares as $producto) {
            $categoria = $producto->categoria ?? 'Sin categoría';
            $respuesta .= "- {$producto->descripcion} ({$categoria}): {$producto->total_vendido} unidades vendidas\n";
        }
        $respuesta .= "\n💡 Estos son tus productos estrella. Aumenta su stock o crea promociones.";
        return $respuesta;
    }

    private function pronosticarVentas(): string
    {
        $ventas = Cache::remember('ventas_prediccion', 3600, function () {
            return DetalleVenta::select(
                'productos.descripcion',
                DB::raw('SUM(detalle_ventas.cantidad) as total_vendido'),
                DB::raw('SUM(CASE WHEN ventas.fecha >= NOW() - INTERVAL 1 MONTH THEN detalle_ventas.cantidad ELSE 0 END) / 3 as peso_reciente'),
                DB::raw('SUM(CASE WHEN ventas.fecha < NOW() - INTERVAL 1 MONTH THEN detalle_ventas.cantidad ELSE 0 END) / 6 as peso_anterior')
            )
                ->join('ventas', 'detalle_ventas.idVenta', '=', 'ventas.idVenta')
                ->join('productos', 'detalle_ventas.idProducto', '=', 'productos.idProducto')
                ->where('ventas.fecha', '>=', now()->subMonths(3))
                ->where('ventas.estado', 'completada')
                ->groupBy('productos.idProducto', 'productos.descripcion')
                ->orderByDesc('total_vendido')
                ->limit(5)
                ->get();
        });

        if ($ventas->isEmpty()) {
            return "No hay datos de ventas para predecir 😔. Revisa tus registros.";
        }

        $respuesta = "📈 **Predicción de ventas (próximo mes)**:\n\n";
        foreach ($ventas as $venta) {
            $prediccion = round(($venta->peso_reciente * 0.7 + $venta->peso_anterior * 0.3) * 1.1, 0); // Peso reciente + ajuste 10%
            $respuesta .= "- {$venta->descripcion}: ~{$prediccion} unidades esperadas (vendió {$venta->total_vendido} en 3 meses)\n";
        }
        $respuesta .= "\n💡 Asegúrate de tener stock suficiente. Usa 'bajo stock' para verificar.";
        return $respuesta;
    }

    private function consultarBajoStock(): string
    {
        $umbral = 10;
        $bajos = Cache::remember('bajo_stock', 300, function () use ($umbral) {
            return StockAlmacen::select('productos.descripcion', 'categorias.nombre as categoria', 'stock_almacens.cantidad', 'almacens.nombre as almacen', 'productos.idProveedor')
                ->join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                ->join('almacens', 'stock_almacens.idAlmacen', '=', 'almacens.idAlmacen')
                ->leftJoin('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
                ->where('stock_almacens.cantidad', '<', $umbral)
                ->where('productos.estado', 'activo')
                ->limit(10)
                ->get();
        });

        if ($bajos->isEmpty()) {
            return "¡Todo en orden! No hay productos con bajo stock ✅.";
        }

        $respuesta = "⚠️ **Productos con bajo stock**:\n\n";
        foreach ($bajos as $bajo) {
            $proveedor = Proveedor::find($bajo->idProveedor);
            $contacto = $proveedor ? "contacta a {$proveedor->nombre} ({$proveedor->telefono})" : "sin proveedor asignado";
            $categoria = $bajo->categoria ?? 'Sin categoría';
            $respuesta .= "- {$bajo->descripcion} ({$categoria}): {$bajo->cantidad} unidades en {$bajo->almacen} ($contacto)\n";
        }
        $respuesta .= "\n💡 Repón estos productos pronto. Usa 'compras' para revisar proveedores.";
        return $respuesta;
    }

    private function consultarPromociones(): string
    {
        $promociones = Promocion::where('estado', 'activa')
            ->where('fecha_fin', '>=', now())
            ->select('nombre', 'descripcion', 'descuento', 'fecha_fin')
            ->with(['producto' => fn($q) => $q->select('idProducto', 'descripcion')])
            ->limit(5)
            ->get();

        if ($promociones->isEmpty()) {
            return "No hay promociones activas 😕. Crea una nueva para impulsar ventas.";
        }

        $respuesta = "🎉 **Promociones activas**:\n\n";
        foreach ($promociones as $promo) {
            $producto = $promo->producto ? $promo->producto->descripcion : 'Sin producto asociado';
            $respuesta .= "- {$promo->nombre}: {$promo->descripcion} ({$promo->descuento}% off) para {$producto}, hasta {$promo->fecha_fin}\n";
        }
        $respuesta .= "\n💡 Revisa el impacto de estas promociones en tus ventas.";
        return $respuesta;
    }

    private function analizarMargenes(): string
    {
        $margenes = Cache::remember('margenes_productos', 3600, function (): \Illuminate\Support\Collection {
            return StockAlmacen::select(
                'productos.descripcion',
                DB::raw('ROUND(AVG(stock_almacens.costo_unitario), 2) as costo_promedio'),
                DB::raw('ROUND(AVG(stock_almacens.precio_venta), 2) as precio_promedio')
            )
                ->join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                ->where('productos.estado', 'activo')
                ->groupBy('productos.idProducto', 'productos.descripcion')
                ->orderByRaw('(ROUND(AVG(stock_almacens.precio_venta), 2) - ROUND(AVG(stock_almacens.costo_unitario), 2)) / ROUND(AVG(stock_almacens.precio_venta), 2) DESC')
                ->limit(5)
                ->get();
        });

        if ($margenes->isEmpty()) {
            return "No hay datos para analizar márgenes 😔. Verifica los costos y precios.";
        }

        $respuesta = "💰 **Productos con mejores márgenes**:\n\n";
        foreach ($margenes as $producto) {
            $margen = round(($producto->precio_promedio - $producto->costo_promedio) / $producto->precio_promedio * 100, 2);
            $respuesta .= "- {$producto->descripcion}: Margen de {$margen}% (costo: $" . number_format($producto->costo_promedio, 2) . ", venta: $" . number_format($producto->precio_promedio, 2) . ")\n";
        }
        $respuesta .= "\n💡 Prioriza estos productos para maximizar ganancias.";
        return $respuesta;
    }

    private function consultarMovimientosAlmacen(): string
    {
        $movimientos = Cache::remember('movimientos_almacen', 300, function () {
            return MovimientoInventario::select(
                'productos.descripcion',
                'almacens.nombre as almacen',
                'movimiento_inventarios.tipo',
                'movimiento_inventarios.cantidad',
                'movimiento_inventarios.fecha',
                'movimiento_inventarios.motivo'
            )
                ->join('productos', 'movimiento_inventarios.idProducto', '=', 'productos.idProducto')
                ->join('almacens', 'movimiento_inventarios.idAlmacen', '=', 'almacens.idAlmacen')
                ->where('movimiento_inventarios.fecha', '>=', now()->subDays(30))
                ->orderBy('movimiento_inventarios.fecha', 'desc')
                ->limit(10)
                ->get();
        });

        if ($movimientos->isEmpty()) {
            return "No hay movimientos recientes en almacenes 😕. Revisa los registros.";
        }

        $respuesta = "📅 **Movimientos recientes de almacén (último mes)**:\n\n";
        foreach ($movimientos as $mov) {
            $tipo = $mov->tipo === 'entrada' ? '➡️ Entrada' : ($mov->tipo === 'salida' ? '⬅️ Salida' : '🔄 Ajuste');
            $motivo = isset($mov->motivo) ? $mov->motivo : 'N/A';
            $respuesta .= "- " . $tipo . ": " . $mov->descripcion . " (" . $mov->cantidad . " unidades) en " . $mov->almacen . " el " . $mov->fecha . " (motivo: " . $motivo . ")\n";
        }
        $respuesta .= "\n💡 Usa 'inventario' para ver el stock actual.";
        return $respuesta;
    }

    private function analizarCompras(): string
    {
        $compras = Cache::remember('analisis_compras', 3600, function () {
            return Compra::select(
                'proveedors.nombre as proveedor',
                DB::raw('COUNT(compras.idCompra) as total_compras'),
                DB::raw('SUM(compras.total) as costo_total')
            )
                ->join('proveedors', 'compras.idProveedor', '=', 'proveedors.idProveedor')
                ->where('compras.fecha', '>=', now()->subMonths(3))
                ->where('compras.estado', 'completada')
                ->groupBy('proveedors.idProveedor', 'proveedors.nombre')
                ->orderByDesc('costo_total')
                ->limit(5)
                ->get();
        });

        if ($compras->isEmpty()) {
            return "No hay compras recientes para analizar 😔. Revisa tus registros.";
        }

        $respuesta = "🛒 **Análisis de compras (últimos 3 meses)**:\n\n";
        foreach ($compras as $compra) {
            $respuesta .= "- " . $compra->proveedor . ": " . $compra->total_compras . " compras, total gastado: $" . $compra->costo_total . "\n";
        }
        $respuesta .= "\n💡 Evalúa negociar con proveedores frecuentes para mejores precios.";
        return $respuesta;
    }

    private function analizarRotacionInventario(): string
    {
        $rotacion = Cache::remember('rotacion_inventario', 3600, function () {
            return StockAlmacen::select(
                'productos.descripcion',
                'categorias.nombre as categoria',
                DB::raw('SUM(stock_almacens.cantidad) as stock_total'),
                DB::raw('(SELECT SUM(detalle_ventas.cantidad) 
                      FROM detalle_ventas 
                      JOIN ventas ON detalle_ventas.idVenta = ventas.idVenta 
                      WHERE detalle_ventas.idProducto = productos.idProducto 
                      AND ventas.fecha >= NOW() - INTERVAL 3 MONTH 
                      AND ventas.estado = "completada") as vendido')
            )
                ->join('productos', 'stock_almacens.idProducto', '=', 'productos.idProducto')
                ->leftJoin('categorias', 'productos.idCategoria', '=', 'categorias.idCategoria')
                ->where('productos.estado', 'activo')
                ->groupBy('productos.idProducto', 'productos.descripcion', 'categorias.nombre')
                ->havingRaw('vendido IS NULL OR vendido / stock_total < 0.5')
                ->orderBy('stock_total', 'desc')
                ->limit(5)
                ->get();
        });

        if ($rotacion->isEmpty()) {
            return "¡Buen trabajo! No hay productos con baja rotación ✅.";
        }

        $respuesta = "🐢 **Productos con baja rotación**:\n\n";
        foreach ($rotacion as $producto) {
            $ventas = $producto->vendido ?? 0;
            $rotacion = $producto->stock_total > 0 ? round($ventas / $producto->stock_total, 2) : 0;
            $categoria = $producto->categoria ?? 'Sin categoría';
            $respuesta .= "- {$producto->descripcion} ({$categoria}): {$producto->stock_total} unidades, rotación: {$rotacion} (vendió {$ventas})\n";
        }
        $respuesta .= "\n💡 Considera promociones o descuentos para mover este inventario.";
        return $respuesta;
    }
}
