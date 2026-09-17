<script>
    (function () {
        const root = document.documentElement;
        const tg = window.Telegram?.WebApp;
        const isDark = root.classList.contains('dark');

        root.style.colorScheme = isDark ? 'dark' : 'light';

        if (! tg) {
            return;
        }

        tg.ready();
        tg.expand();

        const headerBg = isDark ? '#0e121b' : '#f4f6fa';

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
