<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('botConfirm', {
            open: false,
            href: '',
            title: '',
            message: '',
            ask({ href, title, message }) {
                this.href = href || '';
                this.title = title || '';
                this.message = message || '';
                this.open = true;
            },
            cancel() {
                this.open = false;
            },
            confirm() {
                const href = this.href;
                this.open = false;

                if (! href) {
                    return;
                }

                if (typeof window.tgOpenBotLink === 'function') {
                    const shouldNavigate = window.tgOpenBotLink(href);

                    if (shouldNavigate === true) {
                        window.location.href = href;
                    }

                    return;
                }

                window.location.href = href;
            },
        });
    });
</script>
