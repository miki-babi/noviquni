<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait RequiresSuperAdmin
{
    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isSuperAdmin();
    }
}
