@props(['backUrl' => null])

<script>
    (function () {
        const backUrl = @js($backUrl);
        const webApp = window.Telegram?.WebApp;

        if (! webApp?.BackButton) {
            return;
        }

        if (backUrl) {
            webApp.BackButton.show();
            webApp.BackButton.onClick(function () {
                window.location.href = backUrl;
            });
        } else {
            webApp.BackButton.hide();
        }
    })();
</script>
