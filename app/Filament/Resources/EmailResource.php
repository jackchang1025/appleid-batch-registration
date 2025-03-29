<?php

namespace App\Filament\Resources;

use App\Enums\EmailStatus;
use App\Filament\Resources\EmailResource\Pages;
use App\Jobs\RegisterAppleIdJob;
use App\Models\Email;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\BulkAction;
use App\Jobs\RegisterAppleIdForBrowserJob;

class EmailResource extends Resource
{
    protected static ?string $model = Email::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = '邮箱管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('email_uri')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(EmailStatus::labels())
                    ->default(EmailStatus::AVAILABLE->value)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_uri')
                ->searchable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->colors(EmailStatus::colors()),

                Tables\Columns\TextColumn::make('created_at'),

                Tables\Columns\TextColumn::make('updated_at'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(EmailStatus::labels())
                    ->placeholder('选择状态'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                // Tables\Actions\Action::make('logs')
                //     ->label('查看日志')
                //     ->icon('heroicon-o-clipboard-document-list')
                //     ->url(fn ($record) => EmailLogResource::getUrl('index', [
                //         'tableFilters[email][value]' => $record->email,
                //     ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('update_status')
                        ->label('批量修改状态')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->form([
                            Forms\Components\Select::make('status')
                                ->label('状态')
                                ->options(EmailStatus::labels())
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {

                            $count = 0;

                            /** @var Email $record */
                            foreach ($records as $record) {
                                try {
                                    $record->update([
                                        'status' => $data['status'],
                                    ]);

                                    $count++;

                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('状态更新失败')
                                        ->body("{$record->email} 状态更新失败 {$e->getMessage()}")
                                        ->warning()
                                        ->send();
                                }
                            }

                            Notification::make()
                            ->title('状态更新成功')
                            ->body("成功更新 {$count} 条数据")
                            ->success()
                            ->send();
                        }),
                    BulkAction::make('register_appleid')
                        ->label('批量注册 Apple ID')
                        ->icon('heroicon-o-user-plus')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('country')
                                ->label('国家')
                                ->required()
                                ->searchable()
                                ->options([
                                    // 'USA' => '美国',
                                    'CAN' => '加拿大',
                                    // 'GBR' => '英国',
                                    // 'AUS' => '澳大利亚',
                                    // 'NZL' => '新西兰',
                                    // 'DEU' => '德国',
                                    // 'FRA' => '法国',
                                    // 'ITA' => '意大利',
                                    // 'ESP' => '西班牙',
                                    // 'JPN' => '日本',
                                    // 'KOR' => '韩国',
                                    // 'TWN' => '台湾',
                                    // 'HKG' => '香港',
                                    // 'MAC' => '澳门',
                                    // 'CHN' => '中国大陆',
                                ])
                                ->default('CAN')
                                ->helperText('选择需要注册 Apple ID 的国家'),
                        ])
                        ->action(function ($records, array $data) {

                            $count = 0;

                            foreach ($records as $record) {
                                try {

                                    /** @var Email $record */
                                    if ($record->status->value === EmailStatus::AVAILABLE->value || $record->status->value === EmailStatus::FAILED->value){
                                        $count++;

                                        RegisterAppleIdJob::dispatch($record,$data['country']);
                                        continue;
                                    }

                                    throw new \RuntimeException("邮箱 {$record->email} 状态 {$record->status->value}，无法注册 Apple ID");

                                } catch (\Exception $e) {
                                    Notification::make()
                                        ->title('Apple ID 注册失败')
                                        ->body("{$record->email} 的 Apple ID 注册失败: {$e->getMessage()}")
                                        ->warning()
                                        ->send();
                                }
                            }

                            $count && Notification::make()
                                ->title("{$count} 个 Apple ID 注册任务已加入队列")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmails::route('/'),
            'create' => Pages\CreateEmail::route('/create'),
            'edit' => Pages\EditEmail::route('/{record}/edit'),
            'view'   => Pages\ViewEmail::route('/{record}'),
        ];
    }
}
