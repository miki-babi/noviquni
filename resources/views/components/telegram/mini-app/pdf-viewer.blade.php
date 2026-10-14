@props([
    'pdfs' => [],
])

@if ($pdfs !== [])
    <div {{ $attributes->class('') }} data-pdf-viewer-root>
        @foreach ($pdfs as $pdf)
            <div
                class="flex h-full min-h-[100dvh] flex-col bg-black/5"
                data-pdf-viewer
                data-pdf-url="{{ $pdf['url'] }}"
                data-pdf-name="{{ $pdf['name'] }}"
            >
                <div
                    class="min-h-0 flex-1 space-y-2 overflow-auto pt-[env(safe-area-inset-top,0px)] pb-[env(safe-area-inset-bottom,0px)]"
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

        async function mountViewer(root) {
            const url = root.dataset.pdfUrl;
            const stage = root.querySelector('[data-pdf-pages]');
            const error = root.querySelector('[data-pdf-error]');

            if (!url || !stage) {
                return;
            }

            try {
                const pdfDoc = await pdfjsLib.getDocument({ url, withCredentials: true }).promise;
                const width = Math.max(stage.clientWidth || window.innerWidth || 320, 280);
                const outputScale = Math.min(window.devicePixelRatio || 1, 3);

                for (let pageNumber = 1; pageNumber <= pdfDoc.numPages; pageNumber++) {
                    const page = await pdfDoc.getPage(pageNumber);
                    const viewportBase = page.getViewport({ scale: 1 });
                    const scale = width / viewportBase.width;
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    const context = canvas.getContext('2d');

                    canvas.className = 'mx-auto block max-w-full bg-white shadow-sm';
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
                error?.classList.remove('hidden');
            }
        }

        launchPdfFullscreen();

        document.querySelectorAll('[data-pdf-viewer]').forEach((root) => {
            mountViewer(root);
        });
    </script>
@endif
