<script>
    (function () {
        const root = document.documentElement;
        const tg = window.Telegram?.WebApp;
        const isDark = root.classList.contains('dark');

        root.style.colorScheme = isDark ? 'dark' : 'light';

        window.tgOpenBotLink = function (href) {
            if (! href) {
                return true;
            }

            const webApp = window.Telegram?.WebApp;

            if (! webApp || typeof webApp.openTelegramLink !== 'function') {
                return true;
            }

            webApp.openTelegramLink(href);

            window.setTimeout(function () {
                try {
                    webApp.close();
                } catch (e) {}
            }, 50);

            return false;
        };

        if (! tg) {
            return;
        }

        tg.ready();
        tg.expand();

        const headerBg = isDark ? '#0a0a0a' : '#f4f5f7';

        if (typeof tg.setHeaderColor === 'function') {
            try {
                tg.setHeaderColor(headerBg);
            } catch (e) {}
        }

        if (typeof tg.setBackgroundColor === 'function') {
            try {
                tg.setBackgroundColor(headerBg);
            } catch (e) {}
        }
    })();
</script>
