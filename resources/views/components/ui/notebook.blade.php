@props([
    'title' => 'noviquni',
    'subtitle' => 'freshman',
    'meta' => 'ethiopia',
])

<div {{ $attributes->class(['animate-notebook-settle relative mx-auto w-full max-w-sm']) }}>
    <div class="relative rounded-xl border-[1.5px] border-charcoal bg-[#6b3f24] p-4 shadow-lg" style="transform: rotate(-6deg);">
        <div class="rounded-lg border border-charcoal/30 bg-[#8a5533] p-6 pt-10">
            <div class="rounded-lg border border-charcoal bg-cream-paper p-3 shadow-subtle">
                <p class="font-gelica text-body-sm lowercase text-cocoa-ink">{{ $title }}</p>
                <p class="mt-1 font-gelica text-caption lowercase text-charcoal/70">class: {{ $subtitle }}</p>
                <p class="font-gelica text-caption lowercase text-charcoal/70">roll: {{ $meta }}</p>
            </div>
            <div class="mt-8 flex items-end justify-between">
                <div class="h-16 w-2 rounded-full bg-sky-sticker"></div>
                <div class="h-2 w-20 rounded-full bg-dew-drop/40"></div>
            </div>
        </div>
    </div>
</div>
