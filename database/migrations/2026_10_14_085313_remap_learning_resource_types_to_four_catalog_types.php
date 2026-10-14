<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Catalog types are only practice_exams, quiz, reference_books, and past_exams.
     */
    public function up(): void
    {
        $remap = [
            'mid_exam' => 'past_exams',
            'final_exam' => 'past_exams',
            'notes' => 'reference_books',
            'module' => 'reference_books',
            'slides' => 'reference_books',
            'worksheet' => 'reference_books',
            'assignment' => 'reference_books',
            'flashcards' => 'quiz',
            'lecture_notes' => 'reference_books',
            'summary' => 'reference_books',
            'past_exam' => 'past_exams',
            'other' => 'reference_books',
        ];

        foreach ($remap as $from => $to) {
            DB::table('learning_resources')
                ->where('type', $from)
                ->update(['type' => $to]);
        }
    }

    public function down(): void
    {
        DB::table('learning_resources')
            ->where('type', 'past_exams')
            ->update(['type' => 'mid_exam']);
    }
};
