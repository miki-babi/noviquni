<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TelegramCommand;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TelegramCommandPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TelegramCommand');
    }

    public function view(AuthUser $authUser, TelegramCommand $telegramCommand): bool
    {
        return $authUser->can('View:TelegramCommand');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TelegramCommand');
    }

    public function update(AuthUser $authUser, TelegramCommand $telegramCommand): bool
    {
        return $authUser->can('Update:TelegramCommand');
    }

    public function delete(AuthUser $authUser, TelegramCommand $telegramCommand): bool
    {
        return $authUser->can('Delete:TelegramCommand');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TelegramCommand');
    }

    public function restore(AuthUser $authUser, TelegramCommand $telegramCommand): bool
    {
        return $authUser->can('Restore:TelegramCommand');
    }

    public function forceDelete(AuthUser $authUser, TelegramCommand $telegramCommand): bool
    {
        return $authUser->can('ForceDelete:TelegramCommand');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TelegramCommand');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TelegramCommand');
    }

    public function replicate(AuthUser $authUser, TelegramCommand $telegramCommand): bool
    {
        return $authUser->can('Replicate:TelegramCommand');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TelegramCommand');
    }
}
