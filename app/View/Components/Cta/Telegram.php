<?php

namespace App\View\Components\Cta;

use App\Services\TelegramDeepLink;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Telegram extends Component
{
    public string $href;

    public function __construct(
        public string $label = 'Open in Telegram',
        public string $payload = 'web',
        public string $variant = 'primary',
        ?TelegramDeepLink $deepLink = null,
    ) {
        $this->href = ($deepLink ?? app(TelegramDeepLink::class))->url($payload);
    }

    public function render(): View|Closure|string
    {
        return view('components.cta.telegram');
    }
}
