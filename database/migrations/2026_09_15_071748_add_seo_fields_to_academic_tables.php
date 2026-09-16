<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('universities', function (Blueprint $table) {
            $table->text('description')->nullable()->after('slug');
            $table->string('location')->nullable()->after('description');
            $table->string('website')->nullable()->after('location');
            $table->string('logo_path')->nullable()->after('website');
            $table->string('seo_title')->nullable()->after('sort_order');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
            $table->longText('seo_content')->nullable()->after('seo_description');
            $table->string('og_image')->nullable()->after('seo_content');
            $table->boolean('is_indexable')->default(true)->after('og_image');
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->string('seo_title')->nullable()->after('is_active');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
            $table->longText('seo_content')->nullable()->after('seo_description');
            $table->string('og_image')->nullable()->after('seo_content');
            $table->boolean('is_indexable')->default(true)->after('og_image');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('seo_title')->nullable()->after('is_active');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
            $table->longText('seo_content')->nullable()->after('seo_description');
            $table->string('og_image')->nullable()->after('seo_content');
            $table->boolean('is_indexable')->default(true)->after('og_image');
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('title');
            $table->json('topics')->nullable()->after('description');
            $table->string('seo_title')->nullable()->after('file_path');
            $table->string('seo_description', 500)->nullable()->after('seo_title');
            $table->longText('seo_content')->nullable()->after('seo_description');
            $table->string('og_image')->nullable()->after('seo_content');
            $table->boolean('is_indexable')->default(true)->after('og_image');
        });

        $resources = DB::table('learning_resources')->whereNull('slug')->orderBy('id')->get();

        foreach ($resources as $resource) {
            $base = Str::slug($resource->title) ?: 'resource-'.$resource->id;
            $slug = $base;
            $suffix = 1;

            while (
                DB::table('learning_resources')
                    ->where('slug', $slug)
                    ->where('id', '!=', $resource->id)
                    ->exists()
            ) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            DB::table('learning_resources')
                ->where('id', $resource->id)
                ->update(['slug' => $slug]);
        }

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('universities', function (Blueprint $table) {
            $table->dropColumn([
                'description',
                'location',
                'website',
                'logo_path',
                'seo_title',
                'seo_description',
                'seo_content',
                'og_image',
                'is_indexable',
            ]);
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_content',
                'og_image',
                'is_indexable',
            ]);
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_content',
                'og_image',
                'is_indexable',
            ]);
        });

        Schema::table('learning_resources', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'topics',
                'seo_title',
                'seo_description',
                'seo_content',
                'og_image',
                'is_indexable',
            ]);
        });
    }
};
