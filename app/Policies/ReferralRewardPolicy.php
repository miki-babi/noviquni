<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReferralReward;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ReferralRewardPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReferralReward');
    }

    public function view(AuthUser $authUser, ReferralReward $referralReward): bool
    {
        return $authUser->can('View:ReferralReward');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReferralReward');
    }

    public function update(AuthUser $authUser, ReferralReward $referralReward): bool
    {
        return $authUser->can('Update:ReferralReward');
    }

    public function delete(AuthUser $authUser, ReferralReward $referralReward): bool
    {
        return $authUser->can('Delete:ReferralReward');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReferralReward');
    }

    public function restore(AuthUser $authUser, ReferralReward $referralReward): bool
    {
        return $authUser->can('Restore:ReferralReward');
    }

    public function forceDelete(AuthUser $authUser, ReferralReward $referralReward): bool
    {
        return $authUser->can('ForceDelete:ReferralReward');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReferralReward');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReferralReward');
    }

    public function replicate(AuthUser $authUser, ReferralReward $referralReward): bool
    {
        return $authUser->can('Replicate:ReferralReward');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReferralReward');
    }
}
