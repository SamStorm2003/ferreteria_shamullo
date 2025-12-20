<?php

namespace App\Filament\Inventario\Widgets;

use Filament\Widgets\Widget;

class ReferenciaInv extends Widget
{
    protected static string $view = 'filament.inventario.widgets.referencia-inv';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';
}
