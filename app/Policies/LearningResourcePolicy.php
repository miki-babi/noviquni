<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LearningResource;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LearningResourcePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LearningResource');
    }

    public function view(AuthUser $authUser, LearningResource $learningResource): bool
    {
        return $authUser->can('View:LearningResource');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LearningResource');
    }

    public function update(AuthUser $authUser, LearningResource $learningResource): bool
    {
        return $authUser->can('Update:LearningResource');
    }

    public function delete(AuthUser $authUser, LearningResource $learningResource): bool
    {
        return $authUser->can('Delete:LearningResource');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LearningResource');
    }

    public function restore(AuthUser $authUser, LearningResource $learningResource): bool
    {
        return $authUser->can('Restore:LearningResource');
    }

    public function forceDelete(AuthUser $authUser, LearningResource $learningResource): bool
    {
        return $authUser->can('ForceDelete:LearningResource');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LearningResource');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LearningResource');
    }

    public function replicate(AuthUser $authUser, LearningResource $learningResource): bool
    {
        return $authUser->can('Replicate:LearningResource');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LearningResource');
    }
}
