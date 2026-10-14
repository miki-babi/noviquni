@props([
    'pdfs' => [],
])

@if ($pdfs !== [])
    <div {{ $attributes->class('') }} data-pdf-viewer-root>
        @foreach ($pdfs as $pdf)
            <div
                class="flex h-[calc(100dvh-3.75rem)] flex-col bg-surface-gray/30"
                data-pdf-viewer
                data-pdf-url="{{ $pdf['url'] }}"
                data-pdf-name="{{ $pdf['name'] }}"
            >
                <div class="min-h-0 flex-1 overflow-auto bg-black/5">
                    <canvas class="mx-auto block max-w-full" data-pdf-canvas></canvas>
                    <p class="hidden px-4 py-10 text-center text-sm text-text-secondary" data-pdf-error>Unable to load this PDF.</p>
                </div>

                <div class="shrink-0 border-t border-border-light bg-[var(--tg-header-bg)] px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <p class="mb-2 truncate text-center text-xs text-text-secondary">{{ $pdf['name'] }}</p>
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" class="tg-btn tg-btn--ghost min-w-20 text-sm" data-pdf-prev disabled>Prev</button>
                        <span class="text-sm text-text-secondary tabular-nums" data-pdf-page>1 / 1</span>
                        <button type="button" class="tg-btn tg-btn--ghost min-w-20 text-sm" data-pdf-next disabled>Next</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script type="module">
        import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

        async function mountViewer(root) {
            const url = root.dataset.pdfUrl;
            const canvas = root.querySelector('[data-pdf-canvas]');
            const pageLabel = root.querySelector('[data-pdf-page]');
            const prev = root.querySelector('[data-pdf-prev]');
            const next = root.querySelector('[data-pdf-next]');
            const error = root.querySelector('[data-pdf-error]');
            const stage = canvas?.parentElement;

            if (!url || !canvas) {
                return;
            }

            let pdfDoc = null;
            let pageNumber = 1;
            let rendering = false;

            async function renderPage(num) {
                if (!pdfDoc || rendering) {
                    return;
                }

                rendering = true;

                try {
                    const page = await pdfDoc.getPage(num);
                    const viewportBase = page.getViewport({ scale: 1 });
                    const width = Math.max(stage?.clientWidth || canvas.parentElement?.clientWidth || 320, 280);
                    const scale = width / viewportBase.width;
                    const viewport = page.getViewport({ scale });
                    const context = canvas.getContext('2d');

                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    await page.render({ canvasContext: context, viewport }).promise;

                    pageLabel.textContent = `${num} / ${pdfDoc.numPages}`;
                    prev.disabled = num <= 1;
                    next.disabled = num >= pdfDoc.numPages;
                } catch (e) {
                    error?.classList.remove('hidden');
                    canvas.classList.add('hidden');
                } finally {
                    rendering = false;
                }
            }

            try {
                pdfDoc = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                pageNumber = 1;
                await renderPage(pageNumber);
            } catch (e) {
                error?.classList.remove('hidden');
                canvas.classList.add('hidden');

                return;
            }

            prev?.addEventListener('click', () => {
                if (pageNumber <= 1) {
                    return;
                }

                pageNumber -= 1;
                renderPage(pageNumber);
            });

            next?.addEventListener('click', () => {
                if (!pdfDoc || pageNumber >= pdfDoc.numPages) {
                    return;
                }

                pageNumber += 1;
                renderPage(pageNumber);
            });
        }

        document.querySelectorAll('[data-pdf-viewer]').forEach((root) => {
            mountViewer(root);
        });
    </script>
@endif
