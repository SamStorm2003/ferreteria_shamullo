<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\WorkOS\Http\Middleware\ValidateSessionWithWorkOS;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductoController;

Route::get('/', fn() => Inertia::render('Welcome'));

Route::middleware([
    'auth',
    ValidateSessionWithWorkOS::class,
])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';


Route::get('/reportes/inventario/{tipo}', [ReportController::class, 'generarReporteInventario'])->name('reportes.inventario');

Route::get('/download-invoice/{idVenta}', [InvoiceController::class, 'download'])->name('download.invoice');

//rutas vue
Route::get('/', [ProductoController::class, 'index'])->name('Welcome');
    