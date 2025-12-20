<?php

namespace App\Filament\Vendedor\Widgets;

use Filament\Widgets\Widget;

class ReferenciaVen extends Widget
{
    protected static string $view = 'filament.vendedor.widgets.referencia-ven';
    protected static ?int $sort = 6;
    protected static bool $isLazy = false;
    protected int|string|array $columnSpan = 'full';
}
