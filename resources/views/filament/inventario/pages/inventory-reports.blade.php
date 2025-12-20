<x-filament-panels::page>
    <div class="space-y-6">
        <form action="{{ route('reportes.inventario', ['tipo' => 'comprasfecha']) }}" method="GET"
            class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="fecha_inicio" class="block text-sm font-semibold text-gray-700 dark:text-white">Fecha de
                    Inicio</label>
                <input type="date" name="fecha_inicio" id="fecha_inicio"
                    value="{{ old('fecha_inicio', now()->toDateString()) }}"
                    class="mt-2 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
            </div>
            <div>
                <label for="fecha_fin" class="block text-sm font-semibold text-gray-700 dark:text-white">Fecha de
                    Fin</label>
                <input type="date" name="fecha_fin" id="fecha_fin"
                    value="{{ old('fecha_fin', now()->toDateString()) }}" max="{{ now()->toDateString() }}"
                    class="mt-2 w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition">
            </div>
            <div class="flex items-end">
                <button type="submit"
                    class="inline-flex items-center justify-center px-6 py-3 bg-primary-600 text-white text-base font-medium rounded-xl hover:bg-primary-700 transition-all shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    <span class="px-2">Generar Reporte de Compras</span>
                </button>
            </div>
        </form>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-400">Botones y Reportes</h2>
        <ul class="list-disc pl-5 text-gray-600 dark:text-gray-300">
            <li><strong>Generar Reporte de Compras</strong>: Reporte de compras en un rango de fechas. Ingresa fechas y
                haz clic. Excel con tres hojas:
                <ul class="list-circle pl-5">
                    <li><strong>Detalles Productos</strong>: Productos (ID, nombre, código, categoría, etc.) y stock.
                        Resumen: total productos, bajo stock.</li>
                    <li><strong>Resumen Compras</strong>: Compras (ID, proveedor, fecha, total) y detalles de productos.
                        Resumen: total compras, monto.</li>
                    <li><strong>Movimientos Inventario</strong>: Movimientos (producto, tipo, cantidad, fecha). Resumen:
                        total movimientos, entradas, salidas.</li>
                </ul>
            </li>
            <li><strong>Reporte Stock Almacenes</strong>: Stock por almacén, sin fechas. Una hoja por almacén:
                <ul class="list-circle pl-5">
                    <li>Almacén, producto, cantidad, costos, estado stock. Resumen: total stock, costos, ganancias.</li>
                </ul>
            </li>
            <li><strong>Reporte Productos</strong>: Lista todos los productos, sin fechas. Una hoja:
                <ul class="list-circle pl-5">
                    <li>Producto, stock, costos, ganancias, estado. Resumen: total stock, costos, ganancias.</li>
                </ul>
            </li>
        </ul>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-400">Cómo Leer los Datos</h2>
        <p class="text-gray-600 dark:text-gray-300">
            - <strong>Bajo Stock</strong>: Menos de 10 unidades, resaltado en amarillo.<br>
            - <strong>Resúmenes</strong>: Totales al final de cada hoja.<br>
            - <strong>Eliminados</strong>: Fechas de eliminación indican registros borrados.<br>
        </p>
    </div>
</x-filament-panels::page>
