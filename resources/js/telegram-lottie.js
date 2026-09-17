import lottie from 'lottie-web';

function prefersReducedMotion() {
    if (window.Telegram?.WebApp?.isReducedMotionEnabled) {
        return true;
    }

    return window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true;
}

function mountLottie(el) {
    if (el.dataset.lottieMounted === '1') {
        return;
    }

    const src = el.dataset.lottieSrc;
    if (!src) {
        return;
    }

    el.dataset.lottieMounted = '1';

    const loop = el.dataset.lottieLoop !== '0';
    const autoplay = el.dataset.lottieAutoplay !== '0' && !prefersReducedMotion();

    const animation = lottie.loadAnimation({
        container: el,
        renderer: 'svg',
        loop: prefersReducedMotion() ? false : loop,
        autoplay,
        path: src,
    });

    if (prefersReducedMotion()) {
        animation.addEventListener('DOMLoaded', () => {
            animation.goToAndStop(0, true);
        });
    }
}

export function initTelegramLottie(root = document) {
    root.querySelectorAll('[data-lottie-src]').forEach(mountLottie);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initTelegramLottie());
} else {
    initTelegramLottie();
}
