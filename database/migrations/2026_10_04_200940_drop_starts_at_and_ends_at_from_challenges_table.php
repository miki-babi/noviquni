<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('challenges')) {
            return;
        }

        $hasStartsAt = Schema::hasColumn('challenges', 'starts_at');
        $hasEndsAt = Schema::hasColumn('challenges', 'ends_at');

        if (! $hasStartsAt && ! $hasEndsAt) {
            return;
        }

        $indexes = Schema::getIndexes('challenges');

        $compositeIndex = collect($indexes)->first(
            fn (array $index): bool => ($index['columns'] ?? []) === ['status', 'starts_at', 'ends_at']
        );

        Schema::table('challenges', function (Blueprint $table) use ($compositeIndex, $hasStartsAt, $hasEndsAt): void {
            if (is_array($compositeIndex) && filled($compositeIndex['name'] ?? null)) {
                $table->dropIndex($compositeIndex['name']);
            }

            $columns = array_values(array_filter([
                $hasStartsAt ? 'starts_at' : null,
                $hasEndsAt ? 'ends_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        $hasStatusIndex = collect(Schema::getIndexes('challenges'))->contains(
            fn (array $index): bool => ($index['columns'] ?? []) === ['status']
        );

        if (! $hasStatusIndex) {
            Schema::table('challenges', function (Blueprint $table): void {
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            if (! Schema::hasColumn('challenges', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }

            if (! Schema::hasColumn('challenges', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
        });
    }
};
