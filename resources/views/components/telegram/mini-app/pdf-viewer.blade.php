@props([
    'pdfs' => [],
])

@if ($pdfs !== [])
    <div {{ $attributes->class('') }} data-pdf-viewer-root>
        @foreach ($pdfs as $pdf)
            <div
                class="flex h-[calc(100dvh-3.75rem)] flex-col bg-black/5"
                data-pdf-viewer
                data-pdf-url="{{ $pdf['url'] }}"
                data-pdf-name="{{ $pdf['name'] }}"
            >
                <div class="min-h-0 flex-1 space-y-2 overflow-auto" data-pdf-pages>
                    <p class="hidden px-4 py-10 text-center text-sm text-text-secondary" data-pdf-error>Unable to load this PDF.</p>
                </div>
            </div>
        @endforeach
    </div>

    <script type="module">
        import * as pdfjsLib from 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.min.mjs';

        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/pdf.worker.min.mjs';

        async function mountViewer(root) {
            const url = root.dataset.pdfUrl;
            const stage = root.querySelector('[data-pdf-pages]');
            const error = root.querySelector('[data-pdf-error]');

            if (!url || !stage) {
                return;
            }

            try {
                const pdfDoc = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                const width = Math.max(stage.clientWidth || 320, 280);

                for (let pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                    const page = await pdfDoc.getPage(pageNumber);
                    const viewportBase = page.getViewport({ scale: 1 });
                    const scale = width / viewportBase.width;
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');

                    canvas.className = 'mx-auto block max-w-full bg-white shadow-sm';
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;
                    canvas.setAttribute('data-pdf-canvas', String(pageNumber));

                    stage.appendChild(canvas);

                    await page.render({ canvasContext: context, viewport }).promise;
                }
            } catch (e) {
                error?.classList.remove('hidden');
            }
        }

        document.querySelectorAll('[data-pdf-viewer]').forEach((root) => {
            mountViewer(root);
        });
    </script>
@endif
