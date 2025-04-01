<?php

namespace App\Filament\Resources;


use App\Filament\Resources\PhoneResource\Pages;
use App\Models\Phone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
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

                Tables\Columns\TextColumn::make('country_code_alpha3')
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

                BulkAction::make('export_phones')
                        ->label('批量导出')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records,Table $table) {

                            $content = '';

                            foreach ($records as $record) {
                                /** @var Phone $record */
                                $content .= "{$record->phone}----{$record->phone_address}\n";
                            }

                            return response()->streamDownload(function () use ($content) {
                                echo $content;
                            }, 'phones_export_' . now()->format('YmdHis') . '.txt');
                        }),
                BulkAction::make('update_status')
                    ->label('批量修改状态')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('状态')
                            ->options(Phone::STATUS)
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {

                        $count = 0;
                        /** @var Phone $record */
                        foreach ($records as $record) {
                            try {

                                $record->update([
                                    'status' => $data['status'],
                                ]);

                                $count++;

                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('状态更新失败')
                                    ->body("{$record->phone} 状态更新失败 {$e->getMessage()}")
                                    ->warning()
                                    ->send();
                            }
                        }

                        Notification::make()
                            ->title("批量更新状态完成")
                            ->body("成功更新 {$count} 条数据")
                            ->success()
                            ->send();
                    }),
            ])->defaultSort('id', 'desc');
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
