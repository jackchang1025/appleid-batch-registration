<?php

namespace App\Filament\Resources\EmailResource\RelationManagers;

use App\Models\EmailLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Novadaemon\FilamentPrettyJson\PrettyJson;
class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $recordTitleAttribute = 'message';

    protected static ?string $title = '邮箱日志';

    // public function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([

    //             Forms\Components\TextInput::make('email')
    //                 ->required()
    //                 ->maxLength(255)
    //                 ->label('邮箱'),

    //                 Forms\Components\TextInput::make('created_at')
    //                 ->required()
    //                 ->maxLength(255)
    //                 ->label('创建时间'),

    //             Forms\Components\TextInput::make('message')
    //                 ->required()
    //                 ->maxLength(255)
    //                 ->label('日志信息'),

    //                 PrettyJson::make('data')
    //                 ->label('详细数据'),
    //         ]);
    // }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->label('ID'),
                Tables\Columns\TextColumn::make('message')
                    ->label('日志信息')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalContent(fn (EmailLog $record) => view('filament.email-log-details', ['log' => $record])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
