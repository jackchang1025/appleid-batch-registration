<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppleidResource\Pages;
use App\Models\Appleid;
use App\Models\Email;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\EmailStatus;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
class AppleidResource extends Resource
{
    protected static ?string $model = Appleid::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = 'appleid 管理';


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('email_uri'),
                Tables\Columns\TextColumn::make('password'),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('phone_rui'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    BulkAction::make('export_appleids')
                        ->label('批量导出')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records,Table $table) {

                            $content = '';
                            
                            foreach ($records as $record) {
                                /** @var Appleid $record */
                                $content .= "{$record->email}----{$record->password}----{$record->phone}----{$record->phone_uri}\n";
                            }
                            
                            return response()->streamDownload(function () use ($content) {
                                echo $content;
                            }, 'appleids_export_' . now()->format('YmdHis') . '.txt');
                        }),
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
            'index' => Pages\ListAppleids::route('/'),
            'create' => Pages\CreateAppleid::route('/create'),
            'edit' => Pages\EditAppleid::route('/{record}/edit'),
        ];
    }
}
