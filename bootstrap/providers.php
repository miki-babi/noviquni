<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FilamentShieldServiceProvider::class,
];
