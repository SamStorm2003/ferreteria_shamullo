<?php

namespace App\Filament\Inventario\Resources;

use App\Filament\Inventario\Resources\PromocionResource\Pages;
use App\Filament\Inventario\Resources\PromocionResource\RelationManagers;
use App\Models\Promocion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PromocionResource extends Resource
{
    protected static ?string $model = Promocion::class;

    protected static ?string $navigationIcon = 'heroicon-o-rocket-launch';
    protected static ?string $navigationLabel = 'Promociones';
    protected static ?string $pluralLabel = 'Promociones';
    protected static ?string $navigationGroup = 'Inventario';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'descripcion', 'estado'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información de la Promoción')
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción')
                            ->required()
                            ->rows(4),
                        Forms\Components\Select::make('idProducto')
                            ->label('Producto')
                            ->relationship('producto', 'nombre')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('descuento')
                            ->label('Descuento (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'),
                        Forms\Components\FileUpload::make('url_imagen')
                            ->label('Imagen de la Promoción')
                            ->disk('s3')
                            ->directory('anuncios')
                            ->image()
                            ->visibility('public')
                            ->maxSize(4096)
                            ->imageEditor()
                            ->imageEditorMode(2)
                            ->columnSpan('full')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])
                            ->maxFiles(1)
                            ->multiple(false)
                            ->nullable()
                            ->rules(['nullable', 'image', 'max:4096']),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Vigencia y Estado')
                    ->schema([
                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Fecha de Inicio')
                            ->required()
                            ->default(today())
                            ->minDate(today()),
                        Forms\Components\DatePicker::make('fecha_fin')
                            ->label('Fecha de Fin')
                            ->required()
                            ->minDate(today())
                            ->afterOrEqual('fecha_inicio'),
                        Forms\Components\Select::make('estado')
                            ->label('Estado')
                            ->options([
                                'activa' => 'Activa',
                                'inactiva' => 'Inactiva',
                            ])
                            ->default('activa')
                            ->required(),
                    ])
                    ->columns(3),
                Forms\Components\Placeholder::make('imagen_actual')
                    ->label('Imagen Actual')
                    ->content(function ($record) {
                        if ($record && $record->url_imagen) {
                            return new \Illuminate\Support\HtmlString('<img src="' . $record->url_imagen . '" alt="Imagen actual" class="max-w-xs rounded-lg shadow">');
                        }
                        return 'Sin imagen disponible';
                    })
                    ->columnSpan('full')
                    ->visible(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\EditRecord),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('idPromocion')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('producto.nombre')
                    ->label('Producto')
                    ->sortable()
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('descuento')
                    ->label('Descuento (%)')
                    ->formatStateUsing(fn($state) => number_format($state, 2) . '%')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Total Descuentos')
                            ->formatStateUsing(fn($state) => number_format($state, 2) . '%'),
                    ]),
                Tables\Columns\ImageColumn::make('url_imagen')
                    ->label('Imagen')
                    ->width(100)
                    ->height(100)
                    ->defaultImageUrl(url('path/to/placeholder-image.jpg')),
                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Fecha Inicio')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Fecha Fin')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'activa' => 'success',
                        'inactiva' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'activa' => 'Activa',
                        'inactiva' => 'Inactiva',
                    ]),
                Tables\Filters\Filter::make('fechas')
                    ->form([
                        Forms\Components\DatePicker::make('fecha_inicio')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('fecha_fin')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['fecha_inicio'], fn($query, $date) => $query->whereDate('fecha_inicio', '>=', $date))
                            ->when($data['fecha_fin'], fn($query, $date) => $query->whereDate('fecha_fin', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['fecha_inicio']) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['fecha_inicio'])->format('d/m/Y');
                        }
                        if ($data['fecha_fin']) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['fecha_fin'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
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
            'index' => Pages\ListPromocions::route('/'),
            'create' => Pages\CreatePromocion::route('/create'),
            'edit' => Pages\EditPromocion::route('/{record}/edit'),
        ];
    }
}
