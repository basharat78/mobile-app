<?php

namespace App\Filament\Widgets;

use App\Models\Carrier;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestCarriersTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Carrier Registrations';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Carrier::query()
                    ->with(['user', 'dispatcher'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Carrier')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('user.phone')
                    ->label('Phone')
                    ->color('gray'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('dispatcher.name')
                    ->label('Dispatcher')
                    ->placeholder('Unassigned')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->since()
                    ->color('gray'),
            ])
            ->paginated(false);
    }
}
