<x-telegram.mini-app.layout
    :copy="$copy"
    :active-nav="$activeNav"
    :title="$resource->title"
    :back-url="$resource->course ? route('tg.courses.show', $resource->course) : route('tg.browse')"
>
    <div class="study-page">
        <x-telegram.mini-app.premium-landing
            :copy="$copy"
            :price="$price"
            :payment-bot-url="$paymentBotUrl"
            :required="$required"
            :progress="$progress"
            :urgency="$urgency"
            :resource="$resource"
            :is-premium="false"
        />
    </div>
</x-telegram.mini-app.layout>
