@props([
    'user' => null,
])

@php
    /** @var \App\Models\User|null $resolvedUser */
    $resolvedUser = $user ?? auth()->user();
    $promptCount = (int) ($resolvedUser?->home_screen_prompt_count ?? 0);
    $alreadyAdded = filled($resolvedUser?->home_screen_added_at);
    $maxPrompts = \App\Http\Controllers\Telegram\MiniApp\HomeScreenController::MaxPrompts;
    $eligible = $resolvedUser !== null && ! $alreadyAdded && $promptCount < $maxPrompts;
@endphp

<script>
    document.addEventListener('alpine:init', () => {
        if (window.Alpine?.store('homeScreenPrompt')) {
            return;
        }

        Alpine.store('homeScreenPrompt', {
            open: false,
            dismiss() {
                this.open = false;
            },
            add() {
                const tg = window.Telegram?.WebApp;

                if (tg && typeof tg.addToHomeScreen === 'function') {
                    try {
                        tg.addToHomeScreen();
                    } catch (e) {}
                }

                this.open = false;
            },
        });
    });
</script>

@if ($eligible)
    <div
        id="tg-home-screen-prompt"
        class="hidden"
        data-tg-home-screen-eligible
        data-home-screen-prompt-count="{{ $promptCount }}"
        data-home-screen-added="0"
        data-home-screen-max="{{ $maxPrompts }}"
        data-home-screen-prompted-url="{{ route('tg.home-screen.prompted') }}"
        data-home-screen-added-url="{{ route('tg.home-screen.added') }}"
    ></div>

    <script>
        (function () {
            const root = document.getElementById('tg-home-screen-prompt');

            if (! root) {
                return;
            }

            const sessionKey = 'tg_home_screen_prompted_session';
            const maxPrompts = Number(root.dataset.homeScreenMax || 3);
            let promptCount = Number(root.dataset.homeScreenPromptCount || 0);

            if (promptCount >= maxPrompts) {
                return;
            }

            if (window.sessionStorage?.getItem(sessionKey) === '1') {
                return;
            }

            const tg = window.Telegram?.WebApp;

            if (! tg || typeof tg.checkHomeScreenStatus !== 'function') {
                return;
            }

            const csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';

            function postJson(url) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).catch(() => null);
            }

            function markAdded() {
                root.dataset.homeScreenAdded = '1';
                postJson(root.dataset.homeScreenAddedUrl);
            }

            function showPrompt() {
                if (window.sessionStorage?.getItem(sessionKey) === '1') {
                    return;
                }

                if (promptCount >= maxPrompts || root.dataset.homeScreenAdded === '1') {
                    return;
                }

                window.sessionStorage?.setItem(sessionKey, '1');
                promptCount += 1;
                root.dataset.homeScreenPromptCount = String(promptCount);
                postJson(root.dataset.homeScreenPromptedUrl);

                const openModal = () => {
                    if (window.Alpine?.store('homeScreenPrompt')) {
                        Alpine.store('homeScreenPrompt').open = true;
                    }
                };

                if (window.Alpine) {
                    openModal();
                } else {
                    document.addEventListener('alpine:initialized', openModal, { once: true });
                }
            }

            if (typeof tg.onEvent === 'function') {
                tg.onEvent('homeScreenAdded', markAdded);
            }

            try {
                tg.checkHomeScreenStatus((status) => {
                    if (status === 'added') {
                        markAdded();

                        return;
                    }

                    if (status === 'unsupported') {
                        return;
                    }

                    if (status === 'missed' || status === 'unknown') {
                        showPrompt();
                    }
                });
            } catch (e) {}
        })();
    </script>
@endif
