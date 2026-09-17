<?php

namespace App\Services;

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

    protected function sanitizePayload(string $payload): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $payload) ?? 'web';

        return $sanitized !== '' ? $sanitized : 'web';
    }
}
