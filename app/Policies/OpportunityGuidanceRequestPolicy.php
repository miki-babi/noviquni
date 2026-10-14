<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OpportunityGuidanceRequest;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class OpportunityGuidanceRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OpportunityGuidanceRequest');
    }

    public function view(AuthUser $authUser, OpportunityGuidanceRequest $opportunityGuidanceRequest): bool
    {
        return $authUser->can('View:OpportunityGuidanceRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OpportunityGuidanceRequest');
    }

    public function update(AuthUser $authUser, OpportunityGuidanceRequest $opportunityGuidanceRequest): bool
    {
        return $authUser->can('Update:OpportunityGuidanceRequest');
    }

    public function delete(AuthUser $authUser, OpportunityGuidanceRequest $opportunityGuidanceRequest): bool
    {
        return $authUser->can('Delete:OpportunityGuidanceRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OpportunityGuidanceRequest');
    }

    public function restore(AuthUser $authUser, OpportunityGuidanceRequest $opportunityGuidanceRequest): bool
    {
        return $authUser->can('Restore:OpportunityGuidanceRequest');
    }

    public function forceDelete(AuthUser $authUser, OpportunityGuidanceRequest $opportunityGuidanceRequest): bool
    {
        return $authUser->can('ForceDelete:OpportunityGuidanceRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OpportunityGuidanceRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OpportunityGuidanceRequest');
    }

    public function replicate(AuthUser $authUser, OpportunityGuidanceRequest $opportunityGuidanceRequest): bool
    {
        return $authUser->can('Replicate:OpportunityGuidanceRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OpportunityGuidanceRequest');
    }
}
