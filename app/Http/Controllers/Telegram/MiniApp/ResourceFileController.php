<?php

namespace App\Http\Controllers\Telegram\MiniApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Telegram\MiniApp\Concerns\OpensMiniAppResource;
use App\Models\LearningResource;
use App\Services\PremiumService;
use App\Services\SettingsService;
use App\Services\TelegramService;
use App\Support\LearningResourceFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceFileController extends Controller
{
    use OpensMiniAppResource;

    public function show(
        LearningResource $resource,
        int $index,
        PremiumService $premium,
        SettingsService $settings,
        TelegramService $telegram,
    ): StreamedResponse|Response|RedirectResponse|View {
        $access = $this->authorizeMiniAppResource($resource, $premium, $settings, recordOpen: false);

        if ($access instanceof RedirectResponse || $access instanceof View) {
            return $access;
        }

        $attachments = $resource->pdfAttachments();
        $attachment = $attachments[$index] ?? null;

        abort_if($attachment === null, 404);

        $filename = $attachment['name'];

        if ($attachment['source'] === 'disk') {
            $path = $attachment['key'];
            $disk = Storage::disk(LearningResourceFiles::diskName());

            abort_unless($disk->exists($path) && LearningResourceFiles::isPdfPath($path), 404);

            return $disk->response($path, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        $contents = $telegram->downloadFileContents($attachment['key']);

        abort_if($contents === null || $contents === '', 404);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
        ]);
    }
}
