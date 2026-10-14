<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Broadcast;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BroadcastPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Broadcast');
    }

    public function view(AuthUser $authUser, Broadcast $broadcast): bool
    {
        return $authUser->can('View:Broadcast');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Broadcast');
    }

    public function update(AuthUser $authUser, Broadcast $broadcast): bool
    {
        return $authUser->can('Update:Broadcast');
    }

    public function delete(AuthUser $authUser, Broadcast $broadcast): bool
    {
        return $authUser->can('Delete:Broadcast');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Broadcast');
    }

    public function restore(AuthUser $authUser, Broadcast $broadcast): bool
    {
        return $authUser->can('Restore:Broadcast');
    }

    public function forceDelete(AuthUser $authUser, Broadcast $broadcast): bool
    {
        return $authUser->can('ForceDelete:Broadcast');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Broadcast');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Broadcast');
    }

    public function replicate(AuthUser $authUser, Broadcast $broadcast): bool
    {
        return $authUser->can('Replicate:Broadcast');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Broadcast');
    }
}
