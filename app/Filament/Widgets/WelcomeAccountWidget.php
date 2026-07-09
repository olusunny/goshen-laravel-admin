<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

class WelcomeAccountWidget extends AccountWidget
{
    protected static ?int $sort = -100;

    protected int|string|array $columnSpan = 'full';
}
