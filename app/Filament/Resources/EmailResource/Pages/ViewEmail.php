<?php

namespace App\Filament\Resources\EmailResource\Pages;

use App\Filament\Resources\EmailResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;

class ViewEmail extends ViewRecord
{
    protected static string $resource = EmailResource::class;

    public function infolist(Infolist $infolist): Infolist{
        return $infolist
            ->schema([
                Section::make('邮箱信息')
                    ->schema([
                        TextEntry::make('email'),
                        TextEntry::make('email_uri'),
                        TextEntry::make('status')
                            ->formatStateUsing(fn ($state) => $state->label()),
                    ]),
                Section::make('邮箱日志')
                    ->schema([

                        TextEntry::make('logs')
                            ->label('所有日志记录')
                            ->columnSpanFull()
                            ->view('filament.email-logs-detail-view'),
                    ]),
            ]);
    }
}
