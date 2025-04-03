<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProxyIpStatisticResource\Pages;
use App\Filament\Resources\ProxyIpStatisticResource\RelationManagers;
use App\Models\ProxyIpStatistic;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Support\Facades\DB;

class ProxyIpStatisticResource extends Resource
{
    protected static ?string $model = ProxyIpStatistic::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = '代理IP统计';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('ip_uri')
                    ->maxLength(45),
                Forms\Components\TextInput::make('real_ip')
                    ->maxLength(45),
                Forms\Components\TextInput::make('proxy_provider')
                    ->maxLength(255),
                Forms\Components\TextInput::make('country_code')
                    ->maxLength(2),
                Forms\Components\Toggle::make('is_success')
                    ->required(),
                Forms\Components\Select::make('email_id')
                    ->relationship('email', 'id'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ip_uri')
                    ->searchable(),
                Tables\Columns\TextColumn::make('real_ip')
                    ->searchable(),
                Tables\Columns\TextColumn::make('proxy_provider')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country_code')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_success')
                    ->label('是否成功')
                    ->boolean(),
                Tables\Columns\TextColumn::make('email.email')
                    ->label('邮箱')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_success')
                    ->label('状态')
                    ->boolean()
                    ->trueLabel('成功')
                    ->falseLabel('失败')
                    ->native(false),
                SelectFilter::make('proxy_provider')
                    ->label('代理提供商')
                    ->options(
                        fn () => ProxyIpStatistic::query()
                            ->select('proxy_provider')
                            ->whereNotNull('proxy_provider')
                            ->distinct()
                            ->pluck('proxy_provider', 'proxy_provider')
                            ->all()
                    )
                    ->native(false),
                Filter::make('duplicate_ip')
                    ->label('仅显示重复IP')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('real_ip', function ($query) {
                            $query->select('real_ip')
                                ->from('proxy_ip_statistics')
                                ->groupBy('real_ip')
                                ->having(DB::raw('COUNT(real_ip)'), '>', 1);
                        })
                    )
                    ->indicator('重复IP')
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListProxyIpStatistics::route('/'),
            'create' => Pages\CreateProxyIpStatistic::route('/create'),
            'view' => Pages\ViewProxyIpStatistic::route('/{record}'),
            'edit' => Pages\EditProxyIpStatistic::route('/{record}/edit'),
        ];
    }
}
