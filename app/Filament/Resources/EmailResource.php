<?php

namespace App\Filament\Resources;

use App\Services\CountryLanguageService;
use App\Enums\EmailStatus;
use App\Filament\Resources\EmailResource\Pages;
use App\Jobs\RegisterAppleIdJob;
use App\Models\Email;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Filament\Forms\Components\Toggle;
use App\Services\Phone\PhoneDepositoryFacroty;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\View;
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

                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

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
                BulkAction::make('export_emails')
                ->label('批量导出')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(function (Collection $records,Table $table) {

                    $content = '';

                    foreach ($records as $record) {
                        /** @var Email $record */
                        $content .= "{$record->email}----{$record->email_uri}\n";
                    }

                    return response()->streamDownload(function () use ($content) {
                        echo $content;
                    }, 'emails_export_' . now()->format('YmdHis') . '.txt');
                }),

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
                        ->options(CountryLanguageService::labels('zh-CN'))
                        ->optionsLimit(300)
                        ->helperText('选择需要注册 Apple ID 的国家')
                        ->live(),

                    Forms\Components\Select::make('phone_repository')
                        ->label('手机号来源')
                        ->required()
                        ->searchable()
                        ->options([
                            'database' => '数据库',
                            'five_sim' => '5sim',
                        ])
                        ->helperText('选择需要注册 Apple ID 的手机号来源')
                        ->live(),

                    // Card::make()
                    //     ->visible(fn (Forms\Get $get): bool => $get('phone_repository'))
                    //     ->schema([
                    //         Placeholder::make('product_info')
                    //             ->label('产品列表和价格信息')
                    //             ->content(function (Forms\Get $get) {
                    //                 $country = $get('country');
                    //                 $phoneRepository = $get('phone_repository');
                                    
                    //                 if (!$country || !$phoneRepository) {
                    //                     return '请先选择国家和手机号来源';
                    //                 }
                                    
                    //                 try {
                    //                     $phoneDepository = app(PhoneDepositoryFacroty::class)->make($phoneRepository);
                    //                     $products = $phoneDepository->getProducts($country);
                                        
                    //                     if (empty($products)) {
                    //                         return '没有可用的产品';
                    //                     }
                                        
                    //                     // 格式化显示产品信息
                    //                     $html = '<div class="overflow-x-auto">';
                    //                     $html .= '<table class="min-w-full divide-y divide-gray-200">';
                    //                     $html .= '<thead><tr>';
                    //                     $html .= '<th class="px-4 py-2 text-left">产品名称</th>';
                    //                     $html .= '<th class="px-4 py-2 text-left">类别</th>';
                    //                     $html .= '<th class="px-4 py-2 text-left">数量</th>';
                    //                     $html .= '<th class="px-4 py-2 text-left">价格</th>';
                    //                     $html .= '</tr></thead>';
                    //                     $html .= '<tbody>';
                                        
                    //                     foreach ($products as $name => $data) {
                    //                         $html .= '<tr>';
                    //                         $html .= '<td class="px-4 py-2">' . htmlspecialchars($name) . '</td>';
                    //                         $html .= '<td class="px-4 py-2">' . htmlspecialchars($data['Category'] ?? 'N/A') . '</td>';
                    //                         $html .= '<td class="px-4 py-2">' . htmlspecialchars($data['Qty'] ?? 0) . '</td>';
                    //                         $html .= '<td class="px-4 py-2">' . htmlspecialchars($data['Price'] ?? 0) . '</td>';
                    //                         $html .= '</tr>';
                    //                     }
                                        
                    //                     $html .= '</tbody></table></div>';
                    //                     return $html;
                    //                 } catch (\Exception $e) {
                    //                     return '获取产品信息失败: ' . $e->getMessage();
                    //                 }
                    //             }),
                    //     ]),
                    
                    // Forms\Components\Select::make('product')
                    //     ->label('服务类型')
                    //     ->options([
                    //         'apple' => 'Apple',
                    //         'telegram' => 'Telegram',
                    //         'whatsapp' => 'WhatsApp',
                    //         'google' => 'Google',
                    //         'facebook' => 'Facebook',
                    //         'instagram' => 'Instagram',
                    //     ])
                    //     ->default('apple')
                    //     ->visible(fn (Forms\Get $get): bool => $get('phone_repository') === 'five_sim')
                    //     ->helperText('选择需要使用的5sim服务类型'),
                      
                    Toggle::make('is_random_user_agent')
                        ->label('是否随机生成 User Agent')
                        ->default(false),
                ])
                ->action(function ($records, array $data) {

                    $count = 0;

                    foreach ($records as $record) {
                        try {

                            /** @var Email $record */
                            if ($record->status->value === EmailStatus::AVAILABLE->value || $record->status->value === EmailStatus::FAILED->value){
                                $count++;

                                RegisterAppleIdJob::dispatch(
                                    $record,
                                    $data['country'],
                                    $data['is_random_user_agent'],
                                    $data['phone_repository'],
                                    $data['product'] ?? 'apple'
                                );
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
