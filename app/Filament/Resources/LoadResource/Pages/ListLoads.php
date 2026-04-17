<?php

namespace App\Filament\Resources\LoadResource\Pages;

use App\Filament\Resources\LoadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoads extends ListRecords
{
    protected static string $resource = LoadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
