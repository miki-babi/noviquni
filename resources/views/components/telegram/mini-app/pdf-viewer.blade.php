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
                    class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-[var(--tg-bg)] px-6"
                    data-pdf-loader
                >
                    <div
                        class="h-9 w-9 animate-spin rounded-full border-2 border-border-light border-t-text-primary"
                        aria-hidden="true"
                    ></div>
                    <p class="text-sm text-text-secondary" data-pdf-loader-label>Loading content…</p>
                    <p class="hidden text-xs tabular-nums text-text-secondary" data-pdf-loader-progress></p>
                </div>

                <div
                    class="min-h-0 w-full flex-1 touch-pan-x touch-pan-y space-y-2 overflow-auto pt-[env(safe-area-inset-top,0px)] pb-[env(safe-area-inset-bottom,0px)]"
                    data-pdf-pages
                >
                    <p class="hidden px-4 py-10 text-center text-sm text-text-secondary" data-pdf-error>Unable to load this content.</p>
                </div>
            </div>
        @endforeach
    </div>

    <script type="module">
        import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

        const MIN_ZOOM = 0.5;
        const MAX_ZOOM = 3;
        const SHARP_RENDER_DELAY_MS = 80;
        const LAYOUT_REFLOW_DELAY_MS = 120;
        const MAX_CANVAS_EDGE = 4096;

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
            const loader = root.querySelector('[data-pdf-loader]');
            const loaderLabel = root.querySelector('[data-pdf-loader-label]');
            const loaderProgress = root.querySelector('[data-pdf-loader-progress]');

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
            let initialLoadDone = false;

            function setLoaderVisible(visible) {
                if (! loader) {
                    return;
                }

                loader.classList.toggle('hidden', ! visible);
            }

            function setLoaderStatus(label, progressText = null) {
                if (loaderLabel) {
                    loaderLabel.textContent = label;
                }

                if (! loaderProgress) {
                    return;
                }

                if (progressText) {
                    loaderProgress.textContent = progressText;
                    loaderProgress.classList.remove('hidden');
                } else {
                    loaderProgress.textContent = '';
                    loaderProgress.classList.add('hidden');
                }
            }

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
                    renderPages(zoom, { blocking: false });
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
                    renderPages(zoom, { blocking: false });
                }, LAYOUT_REFLOW_DELAY_MS);
            }

            async function renderPages(targetZoom, { blocking = false } = {}) {
                if (! pdfDoc) {
                    return;
                }

                const token = ++renderToken;
                const zoomAtRender = clampZoom(targetZoom);
                const baseWidth = measureLayoutWidth();

                layoutWidth = baseWidth;

                if (blocking) {
                    setLoaderVisible(true);
                    setLoaderStatus('Preparing pages…');
                }

                try {
                    const cssWidthTarget = baseWidth * zoomAtRender;
                    const deviceRatio = Math.min(window.devicePixelRatio || 1, 3);
                    const nextCanvases = [];
                    const totalPages = pdfDoc.numPages;

                    for (let pageNumber = 1; pageNumber <= totalPages; pageNumber++) {
                        if (token !== renderToken) {
                            return;
                        }

                        if (blocking) {
                            setLoaderStatus(
                                'Rendering pages…',
                                `${pageNumber} / ${totalPages}`,
                            );
                        }

                        const page = await pdfDoc.getPage(pageNumber);
                        const viewportBase = page.getViewport({ scale: 1 });
                        const cssScale = cssWidthTarget / viewportBase.width;
                        let pixelRatio = deviceRatio;
                        let viewport = page.getViewport({ scale: cssScale * pixelRatio });

                        // Keep zoomed pages sharp without blowing past GPU canvas limits.
                        const longestEdge = Math.max(viewport.width, viewport.height);

                        if (longestEdge > MAX_CANVAS_EDGE) {
                            pixelRatio = Math.max(1, pixelRatio * (MAX_CANVAS_EDGE / longestEdge));
                            viewport = page.getViewport({ scale: cssScale * pixelRatio });
                        }

                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d', { alpha: false });
                        const cssWidth = Math.floor(viewport.width / pixelRatio);
                        const cssHeight = Math.floor(viewport.height / pixelRatio);

                        canvas.className = 'mx-auto block bg-white shadow-sm';
                        canvas.width = Math.floor(viewport.width);
                        canvas.height = Math.floor(viewport.height);
                        canvas.style.width = `${cssWidth}px`;
                        canvas.style.height = `${cssHeight}px`;
                        canvas.dataset.baseWidth = String(cssWidth);
                        canvas.dataset.baseHeight = String(cssHeight);
                        canvas.setAttribute('data-pdf-canvas', String(pageNumber));

                        if (context) {
                            context.imageSmoothingEnabled = false;
                        }

                        await page.render({
                            canvasContext: context,
                            viewport,
                            intent: 'display',
                        }).promise;

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
                    initialLoadDone = true;
                    setLoaderVisible(false);

                    if (zoom !== renderedZoom) {
                        applyLiveZoom();
                        scheduleSharpRender();
                    }
                } catch (e) {
                    if (token === renderToken) {
                        setLoaderVisible(false);
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

            setLoaderVisible(true);
            setLoaderStatus('Loading content…');

            try {
                const loadingTask = pdfjsLib.getDocument({ url, withCredentials: true });

                loadingTask.onProgress = ({ loaded, total }) => {
                    if (initialLoadDone || ! total || total <= 0) {
                        return;
                    }

                    const percent = Math.min(100, Math.round((loaded / total) * 100));
                    setLoaderStatus('Loading content…', `${percent}%`);
                };

                pdfDoc = await loadingTask.promise;
                await renderPages(1, { blocking: true });
            } catch (e) {
                setLoaderVisible(false);
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

                // Re-render at the final zoom immediately so text stays crisp.
                if (Math.abs(zoom - renderedZoom) >= 0.02) {
                    if (sharpTimer !== null) {
                        window.clearTimeout(sharpTimer);
                        sharpTimer = null;
                    }

                    renderPages(zoom, { blocking: false });
                }
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
