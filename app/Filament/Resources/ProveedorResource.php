<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProveedorResource\Pages;
use App\Filament\Resources\ProveedorResource\RelationManagers;
use App\Models\Proveedor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Exports\ProveedorExporter;
use Filament\Tables\Actions\ExportAction;

class ProveedorResource extends Resource
{
    protected static ?string $model = Proveedor::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Proveedores';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'contacto', 'telefono', 'correo', 'direccion'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(100)
                    ->unique(Proveedor::class, 'nombre', ignoreRecord: true),

                Forms\Components\TextInput::make('contacto')
                    ->label('Persona de Contacto')
                    ->maxLength(100)
                    ->required(),

                Forms\Components\TextInput::make('telefono')
                    ->label('Teléfono')
                    ->maxLength(20)
                    ->regex('/^[0-9]{7,20}$/')
                    ->required(),

                Forms\Components\TextInput::make('correo')
                    ->label('Correo')
                    ->email()
                    ->maxLength(100)
                    ->required()
                    ->unique(Proveedor::class, 'correo', ignoreRecord: true),

                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->maxLength(255)
                    ->required(),

                Forms\Components\Toggle::make('estado')
                    ->label('Estado')
                    ->default('activo')
                    ->formatStateUsing(fn($state) => $state === 'activo')
            ])
            ->columns([
                'sm' => 1,
                'md' => 2,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idProveedor')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('contacto')
                    ->label('Contacto')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('direccion')
                    ->label('Dirección')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('correo')
                    ->label('Correo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->colors([
                        'success' => 'activo',
                        'danger' => 'inactivo',
                    ]),

                Tables\Columns\TextColumn::make('fecha_registro')
                    ->label('Fecha de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'activo' => 'Activo',
                        'inactivo' => 'Inactivo',
                    ]),

                Tables\Filters\Filter::make('fecha_registro')
                    ->label('Fecha de Registro')
                    ->form([
                        Forms\Components\DatePicker::make('desde')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['desde'], fn($q) => $q->whereDate('fecha_registro', '>=', $data['desde']))
                            ->when($data['hasta'], fn($q) => $q->whereDate('fecha_registro', '<=', $data['hasta']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Exportar Proveedores')
                    ->exporter(ProveedorExporter::class),
            ])
            ->defaultSort('idProveedor', 'desc');
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
            'index' => Pages\ListProveedors::route('/'),
            'create' => Pages\CreateProveedor::route('/create'),
            'edit' => Pages\EditProveedor::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
