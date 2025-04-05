<?php

namespace App\Filament\Resources;

use App\Enums\UserAgentStatus;
use App\Filament\Resources\UserAgentResource\Pages;
use App\Models\UserAgent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserAgentResource extends Resource
{
    protected static ?string $model = UserAgent::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationLabel = '用户代理';
    
    protected static ?string $modelLabel = '用户代理';
    
    protected static ?string $pluralModelLabel = '用户代理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                    
                Textarea::make('user_agent')
                    ->label('UserAgent')
                    ->required()
                    ->columnSpanFull(),
                    
                Select::make('status')
                    ->label('状态')
                    ->options(UserAgentStatus::labels())
                    ->required()
                    ->default(UserAgentStatus::ACTIVE->value),
                    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                    
                TextColumn::make('user_agent')
                    ->label('UserAgent字符串')
                    ->searchable(),
                    
               
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (UserAgentStatus $state): string => $state->color())
                    ->formatStateUsing(fn (UserAgentStatus $state): string => $state->label()),
                    
              
                    
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(UserAgentStatus::labels()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
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
            'index' => Pages\ListUserAgents::route('/'),
            'create' => Pages\CreateUserAgent::route('/create'),
            'edit' => Pages\EditUserAgent::route('/{record}/edit'),
            'view' => Pages\ViewUserAgent::route('/{record}'),
        ];
    }
} 