<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TelegramFileAsset;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TelegramFileAssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:TelegramFileAsset');
    }

    public function view(AuthUser $authUser, TelegramFileAsset $telegramFileAsset): bool
    {
        return $authUser->can('View:TelegramFileAsset');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:TelegramFileAsset');
    }

    public function update(AuthUser $authUser, TelegramFileAsset $telegramFileAsset): bool
    {
        return $authUser->can('Update:TelegramFileAsset');
    }

    public function delete(AuthUser $authUser, TelegramFileAsset $telegramFileAsset): bool
    {
        return $authUser->can('Delete:TelegramFileAsset');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:TelegramFileAsset');
    }

    public function restore(AuthUser $authUser, TelegramFileAsset $telegramFileAsset): bool
    {
        return $authUser->can('Restore:TelegramFileAsset');
    }

    public function forceDelete(AuthUser $authUser, TelegramFileAsset $telegramFileAsset): bool
    {
        return $authUser->can('ForceDelete:TelegramFileAsset');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:TelegramFileAsset');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:TelegramFileAsset');
    }

    public function replicate(AuthUser $authUser, TelegramFileAsset $telegramFileAsset): bool
    {
        return $authUser->can('Replicate:TelegramFileAsset');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:TelegramFileAsset');
    }
}
