<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Opportunity;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OpportunityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Opportunity');
    }

    public function view(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->can('View:Opportunity');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Opportunity');
    }

    public function update(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->can('Update:Opportunity');
    }

    public function delete(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->can('Delete:Opportunity');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Opportunity');
    }

    public function restore(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->can('Restore:Opportunity');
    }

    public function forceDelete(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->can('ForceDelete:Opportunity');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Opportunity');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Opportunity');
    }

    public function replicate(AuthUser $authUser, Opportunity $opportunity): bool
    {
        return $authUser->can('Replicate:Opportunity');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Opportunity');
    }
}
