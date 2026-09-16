@props([
    'payload',
])

@php
    $summary = $payload['summary'] ?? null;
    $definitions = is_array($payload['keyDefinitions'] ?? null) ? $payload['keyDefinitions'] : [];
    $principles = is_array($payload['corePrinciples'] ?? null) ? $payload['corePrinciples'] : [];
    $mistakes = is_array($payload['commonMistakes'] ?? null) ? $payload['commonMistakes'] : [];
    $formulas = is_array($payload['keyFormulas'] ?? null) ? $payload['keyFormulas'] : [];
@endphp

<div class="space-y-8">
    @if (filled($summary))
        <x-ui.card>
            <h2 class="text-xl font-bold text-text-primary">Summary</h2>
            <p class="mt-3 text-text-secondary">{{ $summary }}</p>
        </x-ui.card>
    @endif

    @if ($definitions !== [])
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-text-primary">Key definitions</h2>
            <ul class="space-y-3">
                @foreach ($definitions as $definition)
                    @continue(! is_array($definition))
                    <li>
                        <x-ui.card>
                            <p class="font-semibold text-text-primary">{{ $definition['term'] ?? '' }}</p>
                            <p class="mt-1 text-text-secondary">{{ $definition['definition'] ?? '' }}</p>
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($principles !== [])
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-text-primary">Core principles</h2>
            <ul class="list-disc space-y-2 pl-5 text-text-secondary">
                @foreach ($principles as $principle)
                    @continue(! is_string($principle) || blank($principle))
                    <li>{{ $principle }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($mistakes !== [])
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-text-primary">Common mistakes</h2>
            <ul class="list-disc space-y-2 pl-5 text-text-secondary">
                @foreach ($mistakes as $mistake)
                    @continue(! is_string($mistake) || blank($mistake))
                    <li>{{ $mistake }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($formulas !== [])
        <section class="space-y-3">
            <h2 class="text-xl font-bold text-text-primary">Key formulas</h2>
            <ul class="space-y-3">
                @foreach ($formulas as $formula)
                    @continue(! is_array($formula))
                    <li>
                        <x-ui.card>
                            <code class="rounded bg-surface-gray px-2 py-1 text-sm text-primary-300">{{ $formula['formula'] ?? '' }}</code>
                            @if (filled($formula['description'] ?? null))
                                <p class="mt-2 text-text-secondary">{{ $formula['description'] }}</p>
                            @endif
                        </x-ui.card>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
