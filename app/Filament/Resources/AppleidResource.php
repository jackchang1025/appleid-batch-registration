<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppleidResource\Pages;
use App\Models\Appleid;
use App\Models\Email;
use App\Models\Phone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\EmailStatus;
class AppleidResource extends Resource
{
    protected static ?string $model = Appleid::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = 'appleid 管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('emails')
                    ->label('选择邮箱')
                    ->options(Email::where('status', EmailStatus::AVAILABLE)->pluck('email', 'email'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('选择需要注册 Apple ID 的邮箱'),
            ]);
    }

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
//            'edit' => Pages\EditAppleid::route('/{record}/edit'),
        ];
    }
}
