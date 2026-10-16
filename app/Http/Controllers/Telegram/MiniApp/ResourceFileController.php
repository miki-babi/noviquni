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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $context = [
            'resource_id' => $resource->id,
            'slug' => $resource->slug,
            'index' => $index,
            'user_id' => Auth::id(),
        ];

        Log::info('miniapp.pdf_stream.start', $context);

        $access = $this->authorizeMiniAppResource($resource, $premium, $settings, recordOpen: false);

        if ($access instanceof RedirectResponse) {
            Log::warning('miniapp.pdf_stream.authorize_redirect', $context);

            return $access;
        }

        if ($access instanceof View) {
            Log::warning('miniapp.pdf_stream.authorize_locked', $context);

            return $access;
        }

        $attachments = $resource->pdfAttachments();
        $attachment = $attachments[$index] ?? null;

        if ($attachment === null) {
            Log::warning('miniapp.pdf_stream.missing_attachment', [
                ...$context,
                'attachment_count' => count($attachments),
            ]);
            abort(404);
        }

        $filename = $attachment['name'];
        $source = (string) ($attachment['source'] ?? '');
        $key = (string) ($attachment['key'] ?? '');

        Log::info('miniapp.pdf_stream.attachment', [
            ...$context,
            'source' => $source,
            'key' => $key,
            'name' => $filename,
        ]);

        if ($source === 'disk') {
            return $this->streamDiskPdf($resource, $key, $filename, $context);
        }

        $contents = $telegram->downloadFileContents($key);
        $length = is_string($contents) ? strlen($contents) : 0;

        if ($contents === null || $contents === '') {
            Log::warning('miniapp.pdf_stream.telegram_download_failed', [
                ...$context,
                'key' => $key,
                'length' => $length,
            ]);
            abort(404);
        }

        Log::info('miniapp.pdf_stream.ok', [
            ...$context,
            'source' => 'telegram',
            'length' => $length,
        ]);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => (string) $length,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function streamDiskPdf(
        LearningResource $resource,
        string $key,
        string $filename,
        array $context,
    ): StreamedResponse {
        $diskName = LearningResourceFiles::diskName();
        $probe = LearningResourceFiles::probeDiskKey($key);
        $isPdf = LearningResourceFiles::isPdfPath($key);
        $hasTelegramFiles = filled($resource->telegram_files);

        $diskContext = [
            ...$context,
            'disk' => $diskName,
            'key' => $key,
            'exists' => $probe['public_exists'],
            'is_pdf_path' => $isPdf,
            'has_telegram_files' => $hasTelegramFiles,
            ...$probe,
        ];

        Log::info('miniapp.pdf_stream.disk_check', $diskContext);

        if ($probe['public_exists'] && $isPdf) {
            Log::info('miniapp.pdf_stream.ok', [
                ...$context,
                'source' => 'disk',
                'disk' => $diskName,
            ]);

            return Storage::disk($diskName)->response($key, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        if ($probe['local_exists'] && $isPdf) {
            Log::info('miniapp.pdf_stream.ok', [
                ...$context,
                'source' => 'disk_local_fallback',
                'disk' => 'local',
            ]);

            return Storage::disk('local')->response($key, $filename, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]);
        }

        Log::warning('miniapp.pdf_stream.missing_disk_file', $diskContext);
        abort(404);
    }
}
