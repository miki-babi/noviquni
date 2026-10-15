@props(['name'])

@php
    $class = 'h-5 w-5';
@endphp

@switch($name)
    @case('question')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75A3.75 3.75 0 1 1 12 13.5v.75m0 3h.008v.008H12V17.25z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
        </svg>
        @break
    @case('list')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008v.008H3.75V6.75zm0 5.25h.008v.008H3.75V12zm0 5.25h.008v.008H3.75v-.008z" />
        </svg>
        @break
    @case('bolt')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5 14.25 2.25v8.25h6L9.75 21.75V13.5H3.75z" />
        </svg>
        @break
    @case('timer')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3.75 2.25M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z" />
        </svg>
        @break
    @case('shield')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.5 7.5 3.75v5.25c0 4.125-3 7.5-7.5 9-4.5-1.5-7.5-4.875-7.5-9V6L12 2.25z" />
        </svg>
        @break
    @case('calendar')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 6h13.5A1.5 1.5 0 0 1 20.25 7.5v11.25A1.5 1.5 0 0 1 18.75 20.25H5.25A1.5 1.5 0 0 1 3.75 18.75V7.5A1.5 1.5 0 0 1 5.25 6z" />
        </svg>
        @break
    @case('layers')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m12 3.75 8.25 4.5L12 12.75 3.75 8.25 12 3.75zm0 7.5 8.25 4.5L12 20.25l-8.25-4.5L12 11.25z" />
        </svg>
        @break
    @case('target')
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zm0-4.5a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9zm0-3a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
        </svg>
        @break
    @default
        <svg {{ $attributes->class($class) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <circle cx="12" cy="12" r="8" />
        </svg>
@endswitch
