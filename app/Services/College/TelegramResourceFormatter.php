<?php

namespace App\Services\College;

use App\Enums\CollegeResourceKind;
use App\Models\LearningResource;
use Illuminate\Support\Str;

class TelegramResourceFormatter
{
    public const MAX_CHUNK_LENGTH = 4000;

    /**
     * @return list<string>
     */
    public function format(LearningResource $resource): array
    {
        $content = $resource->content;

        if (! is_array($content) || empty($content['payload'])) {
            $fallback = '<b>'.e($resource->title).'</b>';

            if (filled($resource->description)) {
                $fallback .= "\n\n".e($resource->description);
            }

            return [$fallback];
        }

        $kind = $resource->generation_kind
            ?? CollegeResourceKind::tryFrom((string) ($content['kind'] ?? ''))
            ?? CollegeResourceKind::Notes;

        $body = match ($kind) {
            CollegeResourceKind::Notes => $this->formatNotes($resource->title, $content['payload']),
            CollegeResourceKind::Quiz => $this->formatQuiz($resource->title, $content['payload']),
            CollegeResourceKind::Exam => $this->formatExam(
                $resource->title,
                $resource->playerPayload() ?? ['questions' => []],
            ),
            CollegeResourceKind::Flashcards => $this->formatFlashcards($resource->title, $content['payload']),
        };

        return $this->chunk($body);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function formatNotes(string $title, array $payload): string
    {
        $lines = ['<b>'.e($title).'</b>'];

        if (filled($payload['summary'] ?? null)) {
            $lines[] = '';
            $lines[] = e((string) $payload['summary']);
        }

        if (! empty($payload['keyDefinitions']) && is_array($payload['keyDefinitions'])) {
            $lines[] = '';
            $lines[] = '<b>Key definitions</b>';
            foreach ($payload['keyDefinitions'] as $definition) {
                if (! is_array($definition)) {
                    continue;
                }
                $term = e((string) ($definition['term'] ?? ''));
                $text = e((string) ($definition['definition'] ?? ''));
                $lines[] = '• <b>'.$term.'</b>: '.$text;
            }
        }

        if (! empty($payload['corePrinciples']) && is_array($payload['corePrinciples'])) {
            $lines[] = '';
            $lines[] = '<b>Core principles</b>';
            foreach ($payload['corePrinciples'] as $principle) {
                if (is_string($principle) && filled($principle)) {
                    $lines[] = '• '.e($principle);
                }
            }
        }

        if (! empty($payload['commonMistakes']) && is_array($payload['commonMistakes'])) {
            $lines[] = '';
            $lines[] = '<b>Common mistakes</b>';
            foreach ($payload['commonMistakes'] as $mistake) {
                if (is_string($mistake) && filled($mistake)) {
                    $lines[] = '• '.e($mistake);
                }
            }
        }

        if (! empty($payload['keyFormulas']) && is_array($payload['keyFormulas'])) {
            $lines[] = '';
            $lines[] = '<b>Key formulas</b>';
            foreach ($payload['keyFormulas'] as $formula) {
                if (! is_array($formula)) {
                    continue;
                }
                $expression = e((string) ($formula['formula'] ?? ''));
                $description = e((string) ($formula['description'] ?? ''));
                $lines[] = '• <code>'.$expression.'</code>'.(filled($description) ? ' — '.$description : '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function formatQuiz(string $title, array $payload): string
    {
        $lines = ['<b>'.e($title).'</b>', ''];
        $questions = $payload['questions'] ?? [];

        foreach (array_values(is_array($questions) ? $questions : []) as $index => $question) {
            if (! is_array($question)) {
                continue;
            }

            $number = $index + 1;
            $lines[] = '<b>Q'.$number.'.</b> '.e((string) ($question['question'] ?? ''));
            $options = $question['options'] ?? [];
            if (is_array($options)) {
                foreach (array_values($options) as $optionIndex => $option) {
                    $letter = chr(65 + $optionIndex);
                    $lines[] = $letter.') '.e((string) $option);
                }
            }
            $answerIndex = $question['answerIndex'] ?? null;
            if (is_int($answerIndex) && is_array($options) && isset($options[$answerIndex])) {
                $lines[] = '<i>Answer: '.chr(65 + $answerIndex).'</i>';
            }
            if (filled($question['explanation'] ?? null)) {
                $lines[] = e((string) $question['explanation']);
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function formatExam(string $title, array $payload): string
    {
        $lines = ['<b>'.e($title).'</b>', ''];

        if (filled($payload['instructions'] ?? null)) {
            $lines[] = e((string) $payload['instructions']);
            $lines[] = '';
        }

        foreach (array_values($payload['questions'] ?? []) as $index => $question) {
            if (! is_array($question)) {
                continue;
            }

            $options = $question['options'] ?? [];

            if (! is_array($options) || $options === [] || blank($question['question'] ?? null)) {
                continue;
            }

            $lines[] = '<b>'.($index + 1).'.</b> '.e((string) $question['question']);
            foreach (array_values($options) as $optionIndex => $option) {
                $lines[] = chr(65 + $optionIndex).') '.e((string) $option);
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function formatFlashcards(string $title, array $payload): string
    {
        $lines = ['<b>'.e($title).'</b>', ''];
        $cards = $payload['cards'] ?? (array_is_list($payload) ? $payload : []);

        foreach (array_values(is_array($cards) ? $cards : []) as $index => $card) {
            if (! is_array($card)) {
                continue;
            }
            $lines[] = '<b>Card '.($index + 1).'</b>';
            $lines[] = 'Front: '.e((string) ($card['front'] ?? ''));
            $lines[] = 'Back: '.e((string) ($card['back'] ?? ''));
            if (filled($card['hint'] ?? null)) {
                $lines[] = 'Hint: '.e((string) $card['hint']);
            }
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    protected function chunk(string $text): array
    {
        if (Str::length($text) <= self::MAX_CHUNK_LENGTH) {
            return [$text];
        }

        $chunks = [];
        $remaining = $text;

        while (Str::length($remaining) > self::MAX_CHUNK_LENGTH) {
            $slice = Str::substr($remaining, 0, self::MAX_CHUNK_LENGTH);
            $breakAt = max(
                Str::rpos($slice, "\n\n") ?: 0,
                Str::rpos($slice, "\n") ?: 0,
            );

            if ($breakAt < (int) (self::MAX_CHUNK_LENGTH * 0.5)) {
                $breakAt = self::MAX_CHUNK_LENGTH;
            }

            $chunks[] = trim(Str::substr($remaining, 0, $breakAt));
            $remaining = ltrim(Str::substr($remaining, $breakAt));
        }

        if (filled($remaining)) {
            $chunks[] = $remaining;
        }

        return $chunks;
    }
}
