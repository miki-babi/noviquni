@props([
    'pdfs' => [],
])

@if ($pdfs !== [])
    <div {{ $attributes->class('') }} data-pdf-viewer-root>
        @foreach ($pdfs as $pdf)
            <div
                class="relative flex h-full min-h-[100dvh] flex-col bg-black/5"
                data-pdf-viewer
                data-pdf-url="{{ $pdf['url'] }}"
                data-pdf-name="{{ $pdf['name'] }}"
            >
                <div
                    class="min-h-0 flex-1 touch-pan-x touch-pan-y space-y-2 overflow-auto pt-[env(safe-area-inset-top,0px)] pb-[4.5rem]"
                    data-pdf-pages
                >
                    <p class="hidden px-4 py-10 text-center text-sm text-text-secondary" data-pdf-error>Unable to load this PDF.</p>
                </div>

                <div
                    class="absolute inset-x-0 bottom-0 z-10 flex items-center justify-center gap-3 border-t border-border-light bg-[var(--tg-header-bg)] px-4 pt-2 pb-[max(0.5rem,env(safe-area-inset-bottom))]"
                    data-pdf-zoom-bar
                >
                    <button type="button" class="tg-btn tg-btn--ghost min-w-11 text-lg leading-none" data-pdf-zoom-out aria-label="Zoom out">−</button>
                    <button type="button" class="min-w-14 text-center text-sm tabular-nums text-text-secondary" data-pdf-zoom-label aria-label="Reset zoom">100%</button>
                    <button type="button" class="tg-btn tg-btn--ghost min-w-11 text-lg leading-none" data-pdf-zoom-in aria-label="Zoom in">+</button>
                </div>
            </div>
        @endforeach
    </div>

    <script type="module">
        import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

        const MIN_ZOOM = 0.5;
        const MAX_ZOOM = 3;
        const ZOOM_STEP = 0.25;

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
            const zoomIn = root.querySelector('[data-pdf-zoom-in]');
            const zoomOut = root.querySelector('[data-pdf-zoom-out]');
            const zoomLabel = root.querySelector('[data-pdf-zoom-label]');

            if (! url || ! stage) {
                return;
            }

            let pdfDoc = null;
            let zoom = 1;
            let rendering = false;
            let renderToken = 0;
            let pinchStartDistance = 0;
            let pinchStartZoom = 1;
            let pinchPreviewZoom = 1;

            function updateZoomUi() {
                if (zoomLabel) {
                    zoomLabel.textContent = `${Math.round(zoom * 100)}%`;
                }

                if (zoomOut) {
                    zoomOut.disabled = zoom <= MIN_ZOOM;
                }

                if (zoomIn) {
                    zoomIn.disabled = zoom >= MAX_ZOOM;
                }
            }

            async function renderPages() {
                if (! pdfDoc || rendering) {
                    return;
                }

                rendering = true;
                const token = ++renderToken;

                try {
                    stage.querySelectorAll('[data-pdf-canvas]').forEach((node) => node.remove());

                    const baseWidth = Math.max(stage.clientWidth || window.innerWidth || 320, 280);
                    const width = baseWidth * zoom;
                    const outputScale = Math.min(window.devicePixelRatio || 1, 3);

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

                        canvas.className = 'mx-auto block bg-white shadow-sm';
                        canvas.width = Math.floor(viewport.width * outputScale);
                        canvas.height = Math.floor(viewport.height * outputScale);
                        canvas.style.width = `${Math.floor(viewport.width)}px`;
                        canvas.style.height = `${Math.floor(viewport.height)}px`;
                        canvas.setAttribute('data-pdf-canvas', String(pageNumber));

                        stage.appendChild(canvas);

                        const renderContext = {
                            canvasContext: context,
                            viewport,
                        };

                        if (outputScale !== 1) {
                            renderContext.transform = [outputScale, 0, 0, outputScale, 0, 0];
                        }

                        await page.render(renderContext).promise;
                    }
                } catch (e) {
                    if (token === renderToken) {
                        error?.classList.remove('hidden');
                    }
                } finally {
                    if (token === renderToken) {
                        rendering = false;
                        updateZoomUi();
                    }
                }
            }

            async function setZoom(nextZoom) {
                const clamped = clampZoom(nextZoom);

                if (clamped === zoom && stage.querySelector('[data-pdf-canvas]')) {
                    updateZoomUi();

                    return;
                }

                zoom = clamped;
                updateZoomUi();
                await renderPages();
            }

            try {
                pdfDoc = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                updateZoomUi();
                await renderPages();
            } catch (e) {
                error?.classList.remove('hidden');

                return;
            }

            zoomIn?.addEventListener('click', () => {
                setZoom(zoom + ZOOM_STEP);
            });

            zoomOut?.addEventListener('click', () => {
                setZoom(zoom - ZOOM_STEP);
            });

            zoomLabel?.addEventListener('click', () => {
                setZoom(1);
            });

            stage.addEventListener('touchstart', (event) => {
                if (event.touches.length !== 2) {
                    return;
                }

                pinchStartDistance = touchDistance(event.touches);
                pinchStartZoom = zoom;
                pinchPreviewZoom = zoom;
            }, { passive: true });

            stage.addEventListener('touchmove', (event) => {
                if (event.touches.length !== 2 || pinchStartDistance <= 0) {
                    return;
                }

                event.preventDefault();

                const ratio = touchDistance(event.touches) / pinchStartDistance;
                pinchPreviewZoom = clampZoom(pinchStartZoom * ratio);

                if (zoomLabel) {
                    zoomLabel.textContent = `${Math.round(pinchPreviewZoom * 100)}%`;
                }
            }, { passive: false });

            const endPinch = () => {
                if (pinchStartDistance <= 0) {
                    return;
                }

                pinchStartDistance = 0;
                setZoom(pinchPreviewZoom);
            };

            stage.addEventListener('touchend', endPinch, { passive: true });
            stage.addEventListener('touchcancel', endPinch, { passive: true });
        }

        launchPdfFullscreen();

        document.querySelectorAll('[data-pdf-viewer]').forEach((root) => {
            mountViewer(root);
        });
    </script>
@endif
