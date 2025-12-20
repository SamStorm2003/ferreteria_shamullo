<x-filament-panels::page>
    <div class="p-4">
        <h2 class="text-xl font-bold mb-4">Generar Reporte de Facturas</h2>

        <form wire:submit.prevent="generateReport" class="space-y-4">
            <div>
                <label for="fecha_inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-400">Fecha de Inicio (Opcional)</label>
                <x-filament::input type="date" wire:model="fecha_inicio" id="fecha_inicio" class="block w-full mt-1" />
            </div>

            <x-filament::button type="submit">
                Generar Reporte Excel Api
            </x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
