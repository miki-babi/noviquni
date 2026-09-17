<script>
    (function () {
        const root = document.documentElement;
        const tg = window.Telegram?.WebApp;

        root.classList.add('dark');
        root.style.colorScheme = 'dark';

        if (! tg) {
            return;
        }

        tg.ready();
        tg.expand();

        if (typeof tg.setHeaderColor === 'function') {
            try {
                tg.setHeaderColor('#0e121b');
            } catch (e) {}
        }

        if (typeof tg.setBackgroundColor === 'function') {
            try {
                tg.setBackgroundColor('#0e121b');
            } catch (e) {}
        }
    })();
</script>
