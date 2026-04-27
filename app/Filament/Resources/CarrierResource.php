<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CarrierResource\Pages;
use App\Models\Carrier;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class CarrierResource extends Resource
{
    protected static ?string $model = Carrier::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-truck';

    protected static string | \UnitEnum | null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Account Association')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('dispatcher_id')
                            ->relationship('dispatcher', 'name')
                            ->label('Assigned Dispatcher')
                            ->searchable(),
                    ])->columns(2),

                Section::make('Carrier Details')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('remote_id')
                            ->label('Cloud ID')
                            ->disabled(),
                    ])->columns(2),

                Section::make('Preferences')
                    ->schema([
                        Forms\Components\TextInput::make('preferred_origin'),
                        Forms\Components\TextInput::make('preferred_destination'),
                        Forms\Components\TextInput::make('preferred_equipment'),
                        Forms\Components\TextInput::make('min_rate')
                            ->numeric()
                            ->prefix('$'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Carrier Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('dispatcher.name')
                    ->label('Dispatcher')
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
                Filter::make('unassigned')
                    ->label('Pending Assignment')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('dispatcher_id')),
            ])
            ->actions([
                Actions\Action::make('assign_dispatcher')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('dispatcher_id')
                            ->label('Select Dispatcher')
                            ->options(fn () => User::query()
                                ->where('role', 'dispatcher')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (Carrier $record, array $data): void {
                        $record->update([
                            'dispatcher_id' => $data['dispatcher_id'],
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Dispatcher Assigned')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('unassign_dispatcher')
                    ->label('Unassign')
                    ->icon('heroicon-o-user-minus')
                    ->color('danger')
                    ->visible(fn (Carrier $record): bool => !empty($record->dispatcher_id))
                    ->requiresConfirmation()
                    ->modalHeading('Unassign Dispatcher')
                    ->modalDescription('Are you sure you want to unassign this carrier from their dispatcher?')
                    ->action(function (Carrier $record): void {
                        $record->update(['dispatcher_id' => null]);

                        \Filament\Notifications\Notification::make()
                            ->title('Dispatcher Unassigned')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListCarriers::route('/'),
            'create' => Pages\CreateCarrier::route('/create'),
        ];
    }
}
