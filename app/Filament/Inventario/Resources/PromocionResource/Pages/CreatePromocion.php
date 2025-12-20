<?php

namespace App\Filament\Inventario\Resources\PromocionResource\Pages;

use App\Filament\Inventario\Resources\PromocionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePromocion extends CreateRecord
{
    protected static string $resource = PromocionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['url_imagen'])) {
            $bucketUrl = 'https://' . env('AWS_BUCKET') . '.s3.' . env('AWS_DEFAULT_REGION') . '.amazonaws.com/';
            $data['url_imagen'] = $bucketUrl . $data['url_imagen'];
        }
        return $data;
    }
}
