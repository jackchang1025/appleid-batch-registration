<?php

use App\Providers\ProxyProvider;

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    ProxyProvider::class
];
