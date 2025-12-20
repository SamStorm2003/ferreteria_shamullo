<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AlmacenResource\Pages;
use App\Filament\Resources\AlmacenResource\RelationManagers;
use App\Models\Almacen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AlmacenResource extends Resource
{
    protected static ?string $model = Almacen::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Inventario';
    protected static ?string $navigationLabel = 'Almacénes';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre del Almacén')
                    ->required()
                    ->maxLength(100)
                    ->unique(Almacen::class, 'nombre', ignoreRecord: true),

                Forms\Components\TextInput::make('ubicacion')
                    ->label('Ubicación')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idAlmacen')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre del Almacén')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ubicacion')
                    ->label('Ubicación')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('fecha_registro')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->filters([
                //
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
            'index' => Pages\ListAlmacens::route('/'),
            'create' => Pages\CreateAlmacen::route('/create'),
            'edit' => Pages\EditAlmacen::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
