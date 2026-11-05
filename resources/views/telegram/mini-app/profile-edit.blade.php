@php
    $selectedCourses = collect(old('course_ids', $selectedCourseIds))->map(fn ($id) => (int) $id)->all();
    $departmentName = old('department_name', $user->department_name);
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('profile.edit_title')"
    :back-url="route('tg.profile')"
>
    <div class="study-page space-y-4">
        <a href="{{ route('tg.progress') }}" class="study-card study-continue block">
            <p class="study-continue-label">Insights</p>
            <p class="study-continue-title">Study progress</p>
            <p class="study-continue-sub">See strengths, weaknesses, and how you improve over time.</p>
        </a>

        <form method="POST" action="{{ route('tg.profile.academic') }}" class="space-y-4">
            @csrf

            @if (! $isGat)
                @if ($isFreshman)
                @else
                    <div class="study-card study-continue">
                        <p class="study-card-title">{{ $copy->get('profile.department') }}</p>
                        <input
                            type="text"
                            name="department_name"
                            value="{{ $departmentName }}"
                            maxlength="255"
                            required
                            class="mt-3 w-full rounded-2xl border border-border bg-surface px-4 py-3 text-sm text-text-primary"
                            placeholder="{{ $copy->get('profile.department') }}"
                        >
                        @error('department_name')
                            <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            @endif


            @if (! $isGat && $isFreshman)
                <div class="study-card study-continue">
                    <p class="study-card-title">{{ $copy->get('profile.courses') }}</p>
                    <div class="mt-3 space-y-2">
                        @foreach ($courses as $course)
                            <label class="study-card study-card-ghost block cursor-pointer">
                                <div class="study-card-inner">
                                    <input
                                        type="checkbox"
                                        name="course_ids[]"
                                        value="{{ $course->id }}"
                                        class="mt-1"
                                        @checked(in_array($course->id, $selectedCourses, true))
                                    >
                                    <span class="study-card-title">{{ $course->name }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    @error('course_ids')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                    @error('course_ids.*')
                        <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <button type="submit" class="tg-btn">
                {{ $copy->get('profile.save') }}
            </button>
        </form>
    </div>
</x-telegram.mini-app.layout>
