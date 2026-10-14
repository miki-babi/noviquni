<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Semester;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SemesterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Semester');
    }

    public function view(AuthUser $authUser, Semester $semester): bool
    {
        return $authUser->can('View:Semester');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Semester');
    }

    public function update(AuthUser $authUser, Semester $semester): bool
    {
        return $authUser->can('Update:Semester');
    }

    public function delete(AuthUser $authUser, Semester $semester): bool
    {
        return $authUser->can('Delete:Semester');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Semester');
    }

    public function restore(AuthUser $authUser, Semester $semester): bool
    {
        return $authUser->can('Restore:Semester');
    }

    public function forceDelete(AuthUser $authUser, Semester $semester): bool
    {
        return $authUser->can('ForceDelete:Semester');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Semester');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Semester');
    }

    public function replicate(AuthUser $authUser, Semester $semester): bool
    {
        return $authUser->can('Replicate:Semester');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Semester');
    }
}
