<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Referral;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReferralPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Referral');
    }

    public function view(AuthUser $authUser, Referral $referral): bool
    {
        return $authUser->can('View:Referral');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Referral');
    }

    public function update(AuthUser $authUser, Referral $referral): bool
    {
        return $authUser->can('Update:Referral');
    }

    public function delete(AuthUser $authUser, Referral $referral): bool
    {
        return $authUser->can('Delete:Referral');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Referral');
    }

    public function restore(AuthUser $authUser, Referral $referral): bool
    {
        return $authUser->can('Restore:Referral');
    }

    public function forceDelete(AuthUser $authUser, Referral $referral): bool
    {
        return $authUser->can('ForceDelete:Referral');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Referral');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Referral');
    }

    public function replicate(AuthUser $authUser, Referral $referral): bool
    {
        return $authUser->can('Replicate:Referral');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Referral');
    }
}
