<?php

namespace App\Filament\Resources;

use App\Enums\EmailStatus;
use App\Filament\Resources\EmailResource\Pages;
use App\Filament\Resources\EmailResource\RelationManagers;
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
                    ->required()
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('email_uri'),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->colors(EmailStatus::colors()),
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
                            $count = $records->count();
                            $recordsUpdated = 0;
                            
                            foreach ($records as $record) {
                                try {
                                    $record->update([
                                        'status' => $data['status'],
                                    ]);
                                    
                                    // 创建日志记录
                                    $record->createLog(
                                        "状态从 {$record->status->label()} 修改为 " . EmailStatus::from($data['status'])->label(),
                                        ['old_status' => $record->status->value, 'new_status' => $data['status']]
                                    );
                                    
                                    $recordsUpdated++;
                                } catch (\Exception $e) {
                                    // 处理异常
                                }
                            }
                            
                            Notification::make()
                                ->title('状态更新成功')
                                ->body("已成功更新 {$recordsUpdated}/{$count} 个邮箱的状态")
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LogsRelationManager::class,
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
