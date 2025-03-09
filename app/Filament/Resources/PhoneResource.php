<?php

namespace App\Filament\Resources;


use App\Filament\Resources\PhoneResource\Pages;
use App\Models\Phone;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;

class PhoneResource extends Resource
{
    protected static ?string $model = Phone::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = '手机管理';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                PhoneInput::make('phone')
                    ->required()
                    ->unique(ignorable: fn (?Model $record): ?Model => $record)
                    ->helperText('请选择国际区号并输入电话号码')
                    ->displayNumberFormat(PhoneInputNumberType::E164)
                    ->defaultCountry('US'),

                Forms\Components\TextInput::make('phone_address')
                    ->required()
                    ->url()
                    ->prefix('https://')
                    ->helperText('请输入有效的URL地址'),

                Forms\Components\Select::make('status')
                    ->options(Phone::STATUS)
                    ->default('normal')
                    ->required(),

                Forms\Components\TextInput::make('country_code')->default('')->readOnly(),
                Forms\Components\TextInput::make('country_dial_code')->default('')->readOnly(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                PhoneColumn::make('phone')
                    ->displayFormat(PhoneInputNumberType::E164)
                    ->searchable(),

                Tables\Columns\TextColumn::make('phone_address')
                    ->toggleable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('country_code')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Phone::STATUS[$state] ?? $state)
                    ->color(fn (string $state): string => Phone::STATUS_COLOR[$state] ?? 'secondary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('Y-m-d H:i:s')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                ->options(Phone::STATUS)
                ->placeholder('选择状态'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPhones::route('/'),
            'create' => Pages\CreatePhone::route('/create'),
            'edit' => Pages\EditPhone::route('/{record}/edit'),
        ];
    }
}
