<?php

use App\Models\LearningResource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookmarks', 'bookmarkable_type')) {
            Schema::table('bookmarks', function (Blueprint $table) {
                $table->string('bookmarkable_type')->nullable()->after('user_id');
                $table->unsignedBigInteger('bookmarkable_id')->nullable()->after('bookmarkable_type');
                $table->index(['bookmarkable_type', 'bookmarkable_id']);
            });
        }

        $learningResourceMorph = (new LearningResource)->getMorphClass();

        if (Schema::hasColumn('bookmarks', 'learning_resource_id')) {
            DB::table('bookmarks')
                ->whereNull('bookmarkable_id')
                ->orderBy('id')
                ->chunkById(100, function ($bookmarks) use ($learningResourceMorph): void {
                    foreach ($bookmarks as $bookmark) {
                        DB::table('bookmarks')->where('id', $bookmark->id)->update([
                            'bookmarkable_type' => $learningResourceMorph,
                            'bookmarkable_id' => $bookmark->learning_resource_id,
                        ]);
                    }
                });

            $foreignKeys = collect(Schema::getForeignKeys('bookmarks'));
            $hasLearningResourceForeign = $foreignKeys->contains(
                fn (array $foreign): bool => in_array('learning_resource_id', $foreign['columns'] ?? [], true)
            );

            if ($hasLearningResourceForeign) {
                Schema::table('bookmarks', function (Blueprint $table): void {
                    $table->dropForeign(['learning_resource_id']);
                });
            }

            $indexes = collect(Schema::getIndexes('bookmarks'));

            $hasUserIdIndex = $indexes->contains(
                fn (array $index): bool => ($index['columns'] ?? []) === ['user_id']
            );

            if (! $hasUserIdIndex) {
                Schema::table('bookmarks', function (Blueprint $table) {
                    $table->index('user_id');
                });
            }

            $indexes = collect(Schema::getIndexes('bookmarks'));
            $hasUserResourceUnique = $indexes->contains(
                fn (array $index): bool => ($index['unique'] ?? false)
                    && ($index['columns'] ?? []) === ['user_id', 'learning_resource_id']
            );
            $learningResourceIndexName = $indexes
                ->first(fn (array $index): bool => ($index['columns'] ?? []) === ['learning_resource_id'])['name'] ?? null;

            Schema::table('bookmarks', function (Blueprint $table) use ($hasUserResourceUnique, $learningResourceIndexName): void {
                if ($hasUserResourceUnique) {
                    $table->dropUnique(['user_id', 'learning_resource_id']);
                }

                if ($learningResourceIndexName !== null) {
                    $table->dropIndex($learningResourceIndexName);
                }

                $table->dropColumn('learning_resource_id');
            });
        }

        $indexes = collect(Schema::getIndexes('bookmarks'));
        $hasPolymorphicUnique = $indexes->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['user_id', 'bookmarkable_type', 'bookmarkable_id']
        );

        if (! $hasPolymorphicUnique) {
            Schema::table('bookmarks', function (Blueprint $table) {
                $table->unique(['user_id', 'bookmarkable_type', 'bookmarkable_id']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookmarks', 'learning_resource_id')) {
            Schema::table('bookmarks', function (Blueprint $table) {
                $table->foreignId('learning_resource_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            });
        }

        $learningResourceMorph = (new LearningResource)->getMorphClass();

        if (Schema::hasColumn('bookmarks', 'bookmarkable_type')) {
            DB::table('bookmarks')
                ->where('bookmarkable_type', $learningResourceMorph)
                ->orderBy('id')
                ->chunkById(100, function ($bookmarks): void {
                    foreach ($bookmarks as $bookmark) {
                        DB::table('bookmarks')->where('id', $bookmark->id)->update([
                            'learning_resource_id' => $bookmark->bookmarkable_id,
                        ]);
                    }
                });

            $indexes = collect(Schema::getIndexes('bookmarks'));
            $hasPolymorphicUnique = $indexes->contains(
                fn (array $index): bool => ($index['unique'] ?? false)
                    && ($index['columns'] ?? []) === ['user_id', 'bookmarkable_type', 'bookmarkable_id']
            );

            Schema::table('bookmarks', function (Blueprint $table) use ($hasPolymorphicUnique): void {
                if ($hasPolymorphicUnique) {
                    $table->dropUnique(['user_id', 'bookmarkable_type', 'bookmarkable_id']);
                }

                $table->dropIndex(['bookmarkable_type', 'bookmarkable_id']);
                $table->dropColumn(['bookmarkable_type', 'bookmarkable_id']);
            });
        }

        $indexes = collect(Schema::getIndexes('bookmarks'));
        $hasUserResourceUnique = $indexes->contains(
            fn (array $index): bool => ($index['unique'] ?? false)
                && ($index['columns'] ?? []) === ['user_id', 'learning_resource_id']
        );

        if (! $hasUserResourceUnique) {
            Schema::table('bookmarks', function (Blueprint $table) {
                $table->unique(['user_id', 'learning_resource_id']);
            });
        }
    }
};
