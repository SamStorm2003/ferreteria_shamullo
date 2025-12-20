<?php

namespace App\Filament\Vendedor\Resources;

use App\Filament\Vendedor\Resources\FacturaResource\Pages;
use App\Filament\Vendedor\Resources\FacturaResource\RelationManagers;
use App\Models\Factura;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\User;
use App\Models\ClienteExterno;

class FacturaResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';

    protected static ?string $navigationLabel = 'Facturas';
    protected static ?string $pluralLabel = 'Facturas';
    protected static ?string $navigationGroup = 'Ventas';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['idVenta', 'numero_factura', 'nit_cliente', 'razon_social_cliente', 'total'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idVenta')
                    ->label('ID Venta')
                    ->formatStateUsing(fn($state) => "Venta #{$state}")
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('numero_factura')
                    ->label('Nº Factura')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('nit_cliente')
                    ->label('NIT Cliente')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('razon_social_cliente')
                    ->label('Razón Social')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total (Bs)')
                    ->money('BOB', true)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                //Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFacturas::route('/'),
            //      'create' => Pages\CreateFactura::route('/create'),
            //     'edit' => Pages\EditFactura::route('/{record}/edit'),
        ];
    }
}
