@props([
    'resource',
    'copy',
    'confirmTitle' => null,
])

@php
    $title = $confirmTitle ?? $resource->title;
@endphp

@if ($resource->opensInMiniApp())
    <a href="{{ $resource->miniAppUrl() }}" {{ $attributes }}>
        {{ $slot }}
    </a>
@else
    <x-telegram.mini-app.bot-link
        :href="app(\App\Services\TelegramDeepLink::class)->forResource($resource->id)"
        :confirm-title="$copy->get('bot_confirm.title')"
        :confirm-message="$copy->get('bot_confirm.resource', ['title' => $title])"
        {{ $attributes }}
    >
        {{ $slot }}
    </x-telegram.mini-app.bot-link>
@endif
