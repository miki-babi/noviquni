<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ResourceDownload;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $students = User::query()->where('role', UserRole::Student);
        $totalStudents = (clone $students)->count();
        $newStudents = (clone $students)->where('created_at', '>=', now()->subDays(7))->count();
        $activeStudents = (clone $students)->where('is_active', true)->count();
        $premiumUsers = (clone $students)->where('is_premium', true)->count();
        $revenue = Payment::query()->where('status', PaymentStatus::Verified)->sum('amount');
        $referralPoints = (int) (clone $students)->sum('referral_points');
        $referrals = Referral::query()->count();
        $conversion = $totalStudents > 0 ? round(($referrals / $totalStudents) * 100, 1) : 0;
        $downloads = ResourceDownload::query()->count();
        $weeklyActiveStudy = ResourceDownload::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->select('user_id')
            ->distinct()
            ->count('user_id');

        // Temporary display suffix (e.g. 280 → 2807).
        $displaySuffix = '7';

        return [
            Stat::make('Weekly active study', (string) $weeklyActiveStudy)
                ->description('Distinct students who opened a resource in 7d'),
            Stat::make('Total students', (string) $totalStudents.$displaySuffix),
            Stat::make('New (7d)', (string) $newStudents.$displaySuffix),
            Stat::make('Active students', (string) $activeStudents.$displaySuffix),
            Stat::make('Premium users', (string) $premiumUsers),
            Stat::make('Revenue', number_format((float) $revenue, 2).' ETB'),
            Stat::make('Referral points', (string) $referralPoints),
            Stat::make('Referral conversion', $conversion.'%'),
            Stat::make('Resource downloads', (string) $downloads),
        ];
    }
}
