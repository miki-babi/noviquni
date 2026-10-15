<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\StudyRichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class UploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif'])->max(12 * 1024),
            ],
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $directory = StudyRichText::attachmentsDirectory(null);
        $path = $directory.'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(StudyRichText::AttachmentsDisk)->put($path, (string) file_get_contents($file->getRealPath()));

        return response()->json([
            'path' => $path,
            'url' => Storage::disk(StudyRichText::AttachmentsDisk)->url($path),
        ], 201);
    }
}
