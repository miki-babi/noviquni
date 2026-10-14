<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\University;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UniversityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:University');
    }

    public function view(AuthUser $authUser, University $university): bool
    {
        return $authUser->can('View:University');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:University');
    }

    public function update(AuthUser $authUser, University $university): bool
    {
        return $authUser->can('Update:University');
    }

    public function delete(AuthUser $authUser, University $university): bool
    {
        return $authUser->can('Delete:University');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:University');
    }

    public function restore(AuthUser $authUser, University $university): bool
    {
        return $authUser->can('Restore:University');
    }

    public function forceDelete(AuthUser $authUser, University $university): bool
    {
        return $authUser->can('ForceDelete:University');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:University');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:University');
    }

    public function replicate(AuthUser $authUser, University $university): bool
    {
        return $authUser->can('Replicate:University');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:University');
    }
}
