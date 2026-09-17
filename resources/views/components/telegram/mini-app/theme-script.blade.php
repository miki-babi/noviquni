<script>
    (function () {
        const root = document.documentElement;

        function applyTelegramTheme() {
            const tg = window.Telegram?.WebApp;
            if (! tg) {
                return;
            }

            tg.ready();
            tg.expand();

            const scheme = tg.colorScheme === 'dark' ? 'dark' : 'light';
            root.classList.toggle('dark', scheme === 'dark');
            root.style.colorScheme = scheme;

            const params = tg.themeParams || {};
            const map = {
                bg_color: '--tg-bg',
                secondary_bg_color: '--tg-secondary-bg',
                section_bg_color: '--tg-section-bg',
                text_color: '--tg-text',
                hint_color: '--tg-hint',
                link_color: '--tg-link',
                button_color: '--tg-button',
                button_text_color: '--tg-button-text',
                header_bg_color: '--tg-header-bg',
                destructive_text_color: '--tg-destructive',
                section_separator_color: '--tg-separator',
            };

            Object.keys(map).forEach(function (key) {
                if (params[key]) {
                    root.style.setProperty(map[key], params[key]);
                }
            });

            if (params.section_bg_color && ! params.header_bg_color) {
                root.style.setProperty('--tg-header-bg', params.section_bg_color);
            }

            if (typeof tg.setHeaderColor === 'function') {
                try {
                    tg.setHeaderColor(params.header_bg_color || params.bg_color || (scheme === 'dark' ? '#1c1c1d' : '#ffffff'));
                } catch (e) {}
            }

            if (typeof tg.setBackgroundColor === 'function') {
                try {
                    tg.setBackgroundColor(params.bg_color || params.secondary_bg_color || (scheme === 'dark' ? '#0f0f0f' : '#efeff4'));
                } catch (e) {}
            }
        }

        applyTelegramTheme();

        if (window.Telegram?.WebApp?.onEvent) {
            window.Telegram.WebApp.onEvent('themeChanged', applyTelegramTheme);
        }
    })();
</script>
