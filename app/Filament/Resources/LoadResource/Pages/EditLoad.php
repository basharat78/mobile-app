<?php

namespace App\Filament\Resources\LoadResource\Pages;

use App\Filament\Resources\LoadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoad extends EditRecord
{
    protected static string $resource = LoadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
