<?php

namespace App\Http\Controllers;

use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  public function index(Request $request)
    {
        $query = Producto::query()
            ->with([
                'categoria',
                'stockAlmacenes' => function ($query) {
                    $query->with('almacen')->whereNull('stock_almacens.deleted_at');
                },
                'promocion',
                'proveedor'
            ])
            ->select('productos.*')
            ->withCount(['stockAlmacenes as total_stock' => function ($query) {
                $query->select(DB::raw('COALESCE(SUM(cantidad), 0)'))->whereNull('stock_almacens.deleted_at');
            }])
            ->whereNull('productos.deleted_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('codigo', 'like', "%{$search}%");
            });
        }

        if ($request->filled('filter')) {
            $filter = $request->input('filter');
            if ($filter === 'recent') {
                $query->orderBy('fecha_ingreso', 'desc');
            } elseif ($filter === 'price_low_high') {
                $query->leftJoin('stock_almacens', 'productos.idProducto', '=', 'stock_almacens.idProducto')
                      ->orderByRaw('COALESCE(stock_almacens.precio_venta, 0) ASC');
            } elseif ($filter === 'price_high_low') {
                $query->leftJoin('stock_almacens', 'productos.idProducto', '=', 'stock_almacens.idProducto')
                      ->orderByRaw('COALESCE(stock_almacens.precio_venta, 0) DESC');
            }
        }

        $productos = $query->paginate(10)->withQueryString();

        return inertia('Welcome', [
            'productos' => $productos,
            'search' => $request->input('search', ''),
            'filter' => $request->input('filter', ''),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Producto $producto)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Producto $producto)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Producto $producto)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Producto $producto)
    {
        //
    }
}
