<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'lecture_notes' => 'notes',
            'summary' => 'notes',
            'practice_question' => 'quiz',
            'past_exam' => 'practice_exams',
            'other' => 'reference_books',
        ];

        foreach ($map as $from => $to) {
            DB::table('learning_resources')
                ->where('type', $from)
                ->update(['type' => $to]);
        }
    }

    public function down(): void
    {
        $map = [
            'notes' => 'lecture_notes',
            'quiz' => 'practice_question',
            'practice_exams' => 'past_exam',
            'reference_books' => 'other',
        ];

        foreach ($map as $from => $to) {
            DB::table('learning_resources')
                ->where('type', $from)
                ->update(['type' => $to]);
        }
    }
};
