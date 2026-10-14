@props([
    'pdfs' => [],
])

@if ($pdfs !== [])
    <div {{ $attributes->class('space-y-4') }} data-pdf-viewer-root>
        @foreach ($pdfs as $pdf)
            <div
                class="overflow-hidden rounded-2xl border border-border-light bg-surface-gray/40"
                data-pdf-viewer
                data-pdf-url="{{ $pdf['url'] }}"
                data-pdf-name="{{ $pdf['name'] }}"
            >
                <div class="flex items-center justify-between gap-3 border-b border-border-light px-4 py-3">
                    <p class="min-w-0 truncate text-sm font-medium text-text-primary">{{ $pdf['name'] }}</p>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="tg-btn tg-btn--ghost text-xs" data-pdf-prev disabled>Prev</button>
                        <span class="text-xs text-text-secondary tabular-nums" data-pdf-page>1 / 1</span>
                        <button type="button" class="tg-btn tg-btn--ghost text-xs" data-pdf-next disabled>Next</button>
                    </div>
                </div>
                <div class="max-h-[70vh] overflow-auto bg-black/5 p-2">
                    <canvas class="mx-auto block max-w-full" data-pdf-canvas></canvas>
                    <p class="hidden px-2 py-6 text-center text-sm text-text-secondary" data-pdf-error>Unable to load this PDF.</p>
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
                    const width = Math.max(canvas.parentElement?.clientWidth || 320, 280);
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
