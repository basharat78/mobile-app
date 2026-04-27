<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoadResource\Pages;
use App\Models\Load;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;

class LoadResource extends Resource
{
    protected static ?string $model = Load::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';

    protected static string | \UnitEnum | null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Relationships')
                    ->schema([
                        Forms\Components\Select::make('dispatcher_id')
                            ->relationship('dispatcher', 'name', fn (Builder $query) => $query->where('role', 'dispatcher'))
                            ->label('Dispatcher')
                            ->required()
                            ->searchable(),
                        Forms\Components\Hidden::make('dispatcher_phone'),
                        Forms\Components\Select::make('carrier_id')
                            ->relationship('carrier.user', 'name')
                            ->label('Assigned Carrier')
                            ->searchable(),
                    ])->columns(2),

                Section::make('Route Details')
                    ->schema([
                        Forms\Components\TextInput::make('pickup_location')->required(),
                        Forms\Components\TextInput::make('drop_location')->required(),
                        Forms\Components\TextInput::make('miles')->numeric(),
                        Forms\Components\TextInput::make('deadhead')->numeric(),
                    ])->columns(2),

                Section::make('Financials & Logistics')
                    ->schema([
                        Forms\Components\TextInput::make('rate')->numeric()->prefix('$'),
                        Forms\Components\TextInput::make('rpm')->label('Rate Per Mile')->numeric(),
                        Forms\Components\TextInput::make('weight')->numeric(),
                        Forms\Components\TextInput::make('equipment_type'),
                         Forms\Components\Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'assigned' => 'Assigned',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('broker_name'),
                    ])->columns(2),

                Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('pickup_time'),
                        Forms\Components\DateTimePicker::make('drop_off_time'),
                    ])->columns(2),

                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
                Tables\Columns\TextColumn::make('pickup_location')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('drop_location')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('rate')->money('USD')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'primary' => 'available',
                        'success' => 'assigned',
                        'gray' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('dispatcher.name')
                    ->label('Dispatcher'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                 Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'assigned' => 'Assigned',
                    ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', 'completed'))
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
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
            'index' => Pages\ListLoads::route('/'),
            'create' => Pages\CreateLoad::route('/create'),
            'edit' => Pages\EditLoad::route('/{record}/edit'),
        ];
    }
}
