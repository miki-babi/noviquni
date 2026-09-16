<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

final class TelegramHtml
{
    /**
     * Convert Filament / browser HTML into Telegram HTML parse_mode content.
     */
    public static function fromRichHtml(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        if (! self::looksLikeHtml($html)) {
            return self::escape($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('body')->item(0);

        if ($root === null) {
            return self::escape(strip_tags($html));
        }

        return self::normalizeWhitespace(self::renderChildren($root));
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
    }

    public static function isBlank(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '';
    }

    private static function looksLikeHtml(string $value): bool
    {
        return (bool) preg_match(
            '/<\/?(?:p|br|div|span|strong|b|em|i|u|s|a|ul|ol|li|h[1-6]|blockquote|pre|code|strike|del|ins)\b/i',
            $value,
        );
    }

    private static function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return self::escape($node->wholeText);
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'br' => "\n",
            'b', 'strong' => self::wrapInline($node, 'b'),
            'i', 'em' => self::wrapInline($node, 'i'),
            'u', 'ins' => self::wrapInline($node, 'u'),
            's', 'strike', 'del' => self::wrapInline($node, 's'),
            'code' => self::wrapInline($node, 'code'),
            'pre' => self::wrapBlock($node, 'pre'),
            'blockquote' => self::wrapBlock($node, 'blockquote'),
            'a' => self::renderAnchor($node),
            'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => self::renderBlock($node),
            'li' => self::renderListItem($node),
            'ul' => self::renderChildren($node)."\n",
            'ol' => self::renderOrderedList($node),
            'span' => self::renderChildren($node),
            default => self::renderChildren($node),
        };
    }

    private static function renderChildren(DOMNode $node): string
    {
        $output = '';

        foreach ($node->childNodes as $child) {
            $output .= self::renderNode($child);
        }

        return $output;
    }

    private static function renderOrderedList(DOMElement $node): string
    {
        $output = '';
        $index = 1;

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'li') {
                continue;
            }

            $inner = trim(self::renderChildren($child));

            if ($inner === '') {
                continue;
            }

            $output .= $index.'. '.$inner."\n";
            $index++;
        }

        return $output;
    }

    private static function wrapInline(DOMElement $node, string $tag): string
    {
        $inner = self::renderChildren($node);

        if (trim($inner) === '') {
            return $inner;
        }

        return "<{$tag}>{$inner}</{$tag}>";
    }

    private static function wrapBlock(DOMElement $node, string $tag): string
    {
        $inner = trim(self::renderChildren($node));

        if ($inner === '') {
            return '';
        }

        return "<{$tag}>{$inner}</{$tag}>\n";
    }

    private static function renderAnchor(DOMElement $node): string
    {
        $href = trim($node->getAttribute('href'));
        $inner = self::renderChildren($node);

        if ($href === '' || ! preg_match('/^(https?:\/\/|tg:\/\/|mailto:)/i', $href)) {
            return $inner;
        }

        return '<a href="'.self::escape($href).'">'.$inner.'</a>';
    }

    private static function renderBlock(DOMElement $node): string
    {
        $inner = self::renderChildren($node);

        // Empty / <br>-only paragraphs are intentional blank lines from the editor.
        if (trim(str_replace(["\n", "\r"], '', $inner)) === '') {
            return "\n";
        }

        return trim($inner)."\n";
    }

    private static function renderListItem(DOMElement $node): string
    {
        $inner = trim(self::renderChildren($node));

        if ($inner === '') {
            return '';
        }

        return '• '.$inner."\n";
    }

    private static function normalizeWhitespace(string $value): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $value));
    }
}
