@props([
    'pdfs' => [],
])

@if ($pdfs !== [])
    <div {{ $attributes->class('') }} data-pdf-viewer-root>
        @foreach ($pdfs as $pdf)
            <div
                class="relative flex h-full min-h-[100dvh] min-h-[100svh] w-full flex-col bg-black/5"
                data-pdf-viewer
                data-pdf-url="{{ $pdf['url'] }}"
                data-pdf-name="{{ $pdf['name'] }}"
            >
                <div
                    class="min-h-0 w-full flex-1 touch-pan-x touch-pan-y space-y-2 overflow-auto pt-[env(safe-area-inset-top,0px)] pb-[env(safe-area-inset-bottom,0px)]"
                    data-pdf-pages
                >
                    <p class="hidden px-4 py-10 text-center text-sm text-text-secondary" data-pdf-error>Unable to load this PDF.</p>
                </div>
            </div>
        @endforeach
    </div>

    <script type="module">
        import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

        const MIN_ZOOM = 0.5;
        const MAX_ZOOM = 3;
        const SHARP_RENDER_DELAY_MS = 180;
        const LAYOUT_REFLOW_DELAY_MS = 120;

        function launchPdfFullscreen() {
            document.documentElement.classList.add('tg-pdf-fullscreen');
            document.body.classList.add('tg-pdf-fullscreen');

            const tg = window.Telegram?.WebApp;

            if (! tg) {
                return;
            }

            try {
                tg.ready();
            } catch (e) {}

            try {
                tg.expand();
            } catch (e) {}

            if (typeof tg.requestFullscreen === 'function') {
                try {
                    tg.requestFullscreen();
                } catch (e) {}
            }

            // Allow portrait <-> landscape while reading PDFs.
            if (typeof tg.unlockOrientation === 'function') {
                try {
                    tg.unlockOrientation();
                } catch (e) {}
            }

            if (typeof tg.disableVerticalSwipes === 'function') {
                try {
                    tg.disableVerticalSwipes();
                } catch (e) {}
            }
        }

        function clampZoom(zoom) {
            return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round(zoom * 100) / 100));
        }

        function touchDistance(touches) {
            const [a, b] = touches;

            return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
        }

        async function mountViewer(root) {
            const url = root.dataset.pdfUrl;
            const stage = root.querySelector('[data-pdf-pages]');
            const error = root.querySelector('[data-pdf-error]');

            if (! url || ! stage) {
                return;
            }

            let pdfDoc = null;
            let zoom = 1;
            let renderedZoom = 1;
            let layoutWidth = 0;
            let renderToken = 0;
            let sharpTimer = null;
            let layoutTimer = null;
            let pinchStartDistance = 0;
            let pinchStartZoom = 1;

            function measureLayoutWidth() {
                return Math.max(stage.clientWidth || window.innerWidth || 320, 280);
            }

            function applyLiveZoom() {
                const factor = zoom / renderedZoom;

                stage.querySelectorAll('[data-pdf-canvas]').forEach((canvas) => {
                    const baseWidth = Number(canvas.dataset.baseWidth || 0);
                    const baseHeight = Number(canvas.dataset.baseHeight || 0);

                    if (! baseWidth || ! baseHeight) {
                        return;
                    }

                    canvas.style.width = `${Math.floor(baseWidth * factor)}px`;
                    canvas.style.height = `${Math.floor(baseHeight * factor)}px`;
                });
            }

            function scheduleSharpRender() {
                if (sharpTimer !== null) {
                    window.clearTimeout(sharpTimer);
                }

                sharpTimer = window.setTimeout(() => {
                    sharpTimer = null;
                    renderPages(zoom);
                }, SHARP_RENDER_DELAY_MS);
            }

            function scheduleLayoutReflow() {
                if (layoutTimer !== null) {
                    window.clearTimeout(layoutTimer);
                }

                layoutTimer = window.setTimeout(() => {
                    layoutTimer = null;

                    const nextWidth = measureLayoutWidth();

                    if (Math.abs(nextWidth - layoutWidth) < 8) {
                        return;
                    }

                    layoutWidth = nextWidth;
                    renderPages(zoom);
                }, LAYOUT_REFLOW_DELAY_MS);
            }

            async function renderPages(targetZoom) {
                if (! pdfDoc) {
                    return;
                }

                const token = ++renderToken;
                const zoomAtRender = clampZoom(targetZoom);
                const baseWidth = measureLayoutWidth();

                layoutWidth = baseWidth;

                try {
                    const width = baseWidth * zoomAtRender;
                    const outputScale = Math.min(window.devicePixelRatio || 1, 3);
                    const nextCanvases = [];

                    for (let pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                        if (token !== renderToken) {
                            return;
                        }

                        const page = await pdfDoc.getPage(pageNumber);
                        const viewportBase = page.getViewport({ scale: 1 });
                        const scale = width / viewportBase.width;
                        const viewport = page.getViewport({ scale });
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        const cssWidth = Math.floor(viewport.width);
                        const cssHeight = Math.floor(viewport.height);

                        canvas.className = 'mx-auto block bg-white shadow-sm';
                        canvas.width = Math.floor(viewport.width * outputScale);
                        canvas.height = Math.floor(viewport.height * outputScale);
                        canvas.style.width = `${cssWidth}px`;
                        canvas.style.height = `${cssHeight}px`;
                        canvas.dataset.baseWidth = String(cssWidth);
                        canvas.dataset.baseHeight = String(cssHeight);
                        canvas.setAttribute('data-pdf-canvas', String(pageNumber));

                        const renderContext = {
                            canvasContext: context,
                            viewport,
                        };

                        if (outputScale !== 1) {
                            renderContext.transform = [outputScale, 0, 0, outputScale, 0, 0];
                        }

                        await page.render(renderContext).promise;

                        if (token !== renderToken) {
                            return;
                        }

                        nextCanvases.push(canvas);
                    }

                    if (token !== renderToken) {
                        return;
                    }

                    stage.querySelectorAll('[data-pdf-canvas]').forEach((node) => node.remove());
                    nextCanvases.forEach((canvas) => stage.appendChild(canvas));

                    renderedZoom = zoomAtRender;

                    if (zoom !== renderedZoom) {
                        applyLiveZoom();
                        scheduleSharpRender();
                    }
                } catch (e) {
                    if (token === renderToken) {
                        error?.classList.remove('hidden');
                    }
                }
            }

            function setZoom(nextZoom, { sharp = true } = {}) {
                zoom = clampZoom(nextZoom);
                applyLiveZoom();

                if (sharp) {
                    scheduleSharpRender();
                }
            }

            try {
                pdfDoc = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                await renderPages(1);
            } catch (e) {
                error?.classList.remove('hidden');

                return;
            }

            stage.addEventListener('touchstart', (event) => {
                if (event.touches.length !== 2) {
                    return;
                }

                pinchStartDistance = touchDistance(event.touches);
                pinchStartZoom = zoom;
            }, { passive: true });

            stage.addEventListener('touchmove', (event) => {
                if (event.touches.length !== 2 || pinchStartDistance <= 0) {
                    return;
                }

                event.preventDefault();

                const ratio = touchDistance(event.touches) / pinchStartDistance;
                setZoom(pinchStartZoom * ratio, { sharp: false });
            }, { passive: false });

            const endPinch = () => {
                if (pinchStartDistance <= 0) {
                    return;
                }

                pinchStartDistance = 0;
                scheduleSharpRender();
            };

            stage.addEventListener('touchend', endPinch, { passive: true });
            stage.addEventListener('touchcancel', endPinch, { passive: true });

            window.addEventListener('resize', scheduleLayoutReflow);
            window.addEventListener('orientationchange', scheduleLayoutReflow);
            window.visualViewport?.addEventListener('resize', scheduleLayoutReflow);

            const tg = window.Telegram?.WebApp;

            if (tg && typeof tg.onEvent === 'function') {
                tg.onEvent('viewportChanged', scheduleLayoutReflow);
                tg.onEvent('fullscreenChanged', scheduleLayoutReflow);
                tg.onEvent('safeAreaChanged', scheduleLayoutReflow);
                tg.onEvent('contentSafeAreaChanged', scheduleLayoutReflow);
            }

            if (typeof ResizeObserver === 'function') {
                const observer = new ResizeObserver(scheduleLayoutReflow);
                observer.observe(stage);
            }
        }

        launchPdfFullscreen();

        document.querySelectorAll('[data-pdf-viewer]').forEach((root) => {
            mountViewer(root);
        });
    </script>
@endif
