<?php

namespace App\Filament\Resources;

use App\Enums\EmailLogStatus;
use App\Filament\Resources\EmailLogResource\Pages;
use App\Models\EmailLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Novadaemon\FilamentPrettyJson\PrettyJson;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';


    protected static ?string $label = '邮箱注册日志';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->label('邮箱地址')
                    ->disabled(),



                Forms\Components\Textarea::make('message')
                    ->label('消息')
                    ->disabled(),

                    PrettyJson::make('data')
                    ->label('其他数据'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('message')
                    ->label('消息')
                    ->limit(50)
                    ->searchable()
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('email')
                    ->label('按邮箱筛选')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(function () {
                        return EmailLog::query()
                            ->select('email')
                            ->distinct()
                            ->pluck('email', 'email')
                            ->toArray();
                    })
                    ->indicateUsing(function (array $state): array {
                        if (empty($state['values'])) {
                            return [];
                        }

                        return array_map(
                            fn (string $email) => "邮箱: {$email}",
                            $state['values'],
                        );
                    }),

                // 添加消息内容关键字筛选
                Tables\Filters\SelectFilter::make('message_type')
                    ->label('消息类型')
                    ->options(function () {
                        return EmailLog::query()
                            ->groupBy('message')
                            ->select('message')
                            ->pluck('message', 'message')
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->where('message', 'like', "%{$data['value']}%");
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListEmailLogs::route('/'),
            'view' => Pages\ViewEmailLog::route('/{record}'),
        ];
    }
}
