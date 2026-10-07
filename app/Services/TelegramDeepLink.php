<?php

namespace App\Services;

use App\Models\User;

class TelegramDeepLink
{
    public function url(string $payload = 'web'): string
    {
        $bot = config('services.telegram.bot_username', 'noviquni_bot');
        $start = $this->sanitizePayload($payload);

        return "https://t.me/{$bot}?start={$start}";
    }

    public function forBait(): string
    {
        return $this->url('bait');
    }

    public function forResource(int $resourceId): string
    {
        return $this->url('resource_'.$resourceId);
    }

    public function forCourse(string $courseSlug): string
    {
        return $this->url('course_'.$courseSlug);
    }

    public function forOpportunity(int $opportunityId, User $sharer): string
    {
        return $this->url('opp_'.$opportunityId.'_'.$sharer->referral_code);
    }

    /**
     * @return array{opportunity_id: int, referral_code: string}|null
     */
    public function parseOpportunitySharePayload(?string $payload): ?array
    {
        if (! filled($payload) || ! preg_match('/^opp_(\d+)_([A-Za-z0-9]+)$/', $payload, $matches)) {
            return null;
        }

        return [
            'opportunity_id' => (int) $matches[1],
            'referral_code' => $matches[2],
        ];
    }

    protected function sanitizePayload(string $payload): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $payload) ?? 'web';

        return $sanitized !== '' ? $sanitized : 'web';
    }
}
