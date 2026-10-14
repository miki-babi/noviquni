<?php

namespace App\Support;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Throwable;

class StudyRichText
{
    public const string AttachmentsDisk = 'public';

    public static function attachmentsDirectory(int|string|null $courseId): string
    {
        $courseSegment = filled($courseId) ? (string) $courseId : 'general';

        return LearningResourceFiles::DirectoryPrefix.'/quiz-content/'.$courseSegment;
    }

    /**
     * @return array{type: string, content: list<array<string, mixed>>}
     */
    public static function emptyDocument(): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                ],
            ],
        ];
    }

    public static function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        if (is_array($value) && ($value['type'] ?? null) === 'doc') {
            return ! static::documentHasContent($value);
        }

        if (is_string($value) && static::looksLikeHtml($value)) {
            if (preg_match('/<img\b/i', $value) === 1) {
                return false;
            }

            return blank(static::normalizePlain($value));
        }

        return blank(static::toPlain($value));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected static function documentHasContent(array $document): bool
    {
        $json = json_encode($document) ?: '';

        if (str_contains($json, '"type":"image"') || str_contains($json, '"img"')) {
            return true;
        }

        return filled(static::toPlain($document));
    }

    /**
     * Normalize legacy plain text / HTML into a TipTap document for Filament RichEditor::json().
     *
     * @return array<string, mixed>
     */
    public static function forEditor(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return static::emptyDocument();
        }

        if (is_array($value)) {
            if (($value['type'] ?? null) === 'doc') {
                return $value;
            }

            return static::emptyDocument();
        }

        if (! is_string($value)) {
            return static::emptyDocument();
        }

        $html = static::looksLikeHtml($value)
            ? $value
            : '<p>'.e($value).'</p>';

        try {
            $document = static::renderer($html)->getEditor()->getDocument();

            return is_array($document) ? $document : static::emptyDocument();
        } catch (Throwable) {
            return static::emptyDocument();
        }
    }

    /**
     * Render TipTap JSON, Filament HTML, or legacy plain text to safe HTML for Mini App players.
     */
    public static function toHtml(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if (is_array($value)) {
            if (($value['type'] ?? null) !== 'doc') {
                return '';
            }

            try {
                return static::renderer($value)->toHtml();
            } catch (Throwable) {
                return '';
            }
        }

        if (! is_string($value)) {
            return e((string) $value);
        }

        if (static::looksLikeHtml($value)) {
            try {
                return static::renderer($value)->toHtml();
            } catch (Throwable) {
                return e(strip_tags($value));
            }
        }

        return e($value);
    }

    /**
     * Strip rich content to plain text for Telegram bot messages.
     */
    public static function toPlain(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        if (is_array($value)) {
            if (($value['type'] ?? null) !== 'doc') {
                return '';
            }

            try {
                return static::normalizePlain(static::renderer($value)->toHtml());
            } catch (Throwable) {
                return '';
            }
        }

        if (! is_string($value)) {
            return trim((string) $value);
        }

        if (static::looksLikeHtml($value)) {
            return static::normalizePlain($value);
        }

        return trim($value);
    }

    public static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match('/<[a-z][\s\S]*>/i', $value);
    }

    protected static function normalizePlain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    protected static function renderer(mixed $content): RichContentRenderer
    {
        return RichContentRenderer::make($content)
            ->fileAttachmentsDisk(self::AttachmentsDisk);
    }
}
