<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ChatBot extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $title = 'Ark Intelligence';
    protected static ?string $navigationLabel = 'Ark Intelligence';
    protected static string $view = 'filament.pages.chat-bot';
}
