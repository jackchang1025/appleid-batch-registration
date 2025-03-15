<?php

namespace App\Filament\Resources\AppleidResource\Pages;

use App\Filament\Resources\AppleidResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms;
use Filament\Forms\Form;

class EditAppleid extends EditRecord
{
    protected static string $resource = AppleidResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email'),
                Forms\Components\TextInput::make('email_uri'),
                Forms\Components\TextInput::make('password'),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\TextInput::make('phone_uri'),
                
            ]);
    }
}
