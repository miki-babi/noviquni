<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Withdrawal;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class WithdrawalPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Withdrawal');
    }

    public function view(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('View:Withdrawal');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Withdrawal');
    }

    public function update(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('Update:Withdrawal');
    }

    public function delete(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('Delete:Withdrawal');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Withdrawal');
    }

    public function restore(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('Restore:Withdrawal');
    }

    public function forceDelete(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('ForceDelete:Withdrawal');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Withdrawal');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Withdrawal');
    }

    public function replicate(AuthUser $authUser, Withdrawal $withdrawal): bool
    {
        return $authUser->can('Replicate:Withdrawal');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Withdrawal');
    }
}
