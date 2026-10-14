<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StudyPlan;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StudyPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StudyPlan');
    }

    public function view(AuthUser $authUser, StudyPlan $studyPlan): bool
    {
        return $authUser->can('View:StudyPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StudyPlan');
    }

    public function update(AuthUser $authUser, StudyPlan $studyPlan): bool
    {
        return $authUser->can('Update:StudyPlan');
    }

    public function delete(AuthUser $authUser, StudyPlan $studyPlan): bool
    {
        return $authUser->can('Delete:StudyPlan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StudyPlan');
    }

    public function restore(AuthUser $authUser, StudyPlan $studyPlan): bool
    {
        return $authUser->can('Restore:StudyPlan');
    }

    public function forceDelete(AuthUser $authUser, StudyPlan $studyPlan): bool
    {
        return $authUser->can('ForceDelete:StudyPlan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StudyPlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StudyPlan');
    }

    public function replicate(AuthUser $authUser, StudyPlan $studyPlan): bool
    {
        return $authUser->can('Replicate:StudyPlan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StudyPlan');
    }
}
