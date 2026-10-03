@php
    $selectedStreamId = (int) old('stream_id', $user->stream_id);
    $selectedUniversityId = old('university_id', $user->university_id);
    $selectedSemesterId = old('semester_id', $user->semester_id);
    $selectedCourses = collect(old('course_ids', $selectedCourseIds))->map(fn ($id) => (int) $id)->all();
@endphp

<x-telegram.mini-app.layout
    :copy="$copy"
    :user="$user"
    :active-nav="$activeNav"
    :title="$copy->get('profile.edit_title')"
    :back-url="route('tg.profile')"
>
    <div class="study-page">
        <form method="POST" action="{{ route('tg.profile.academic') }}" class="space-y-4">
            @csrf

            <div class="study-card study-continue">
                <p class="study-card-title">{{ $copy->get('profile.stream') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($streams as $stream)
                        <label class="study-card study-card-ghost block cursor-pointer">
                            <div class="study-card-inner">
                                <input
                                    type="radio"
                                    name="stream_id"
                                    value="{{ $stream->id }}"
                                    class="mt-1"
                                    @checked((int) $selectedStreamId === $stream->id)
                                    required
                                >
                                <span class="study-card-title">{{ $stream->name }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('stream_id')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="study-card study-continue">
                <p class="study-card-title">{{ $copy->get('profile.university') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($universities as $university)
                        <label class="study-card study-card-ghost block cursor-pointer">
                            <div class="study-card-inner">
                                <input
                                    type="radio"
                                    name="university_id"
                                    value="{{ $university->id }}"
                                    class="mt-1"
                                    @checked((string) $selectedUniversityId === (string) $university->id)
                                >
                                <span class="study-card-title">{{ $university->name }}</span>
                            </div>
                        </label>
                    @endforeach
                    <label class="study-card study-card-ghost block cursor-pointer">
                        <div class="study-card-inner">
                            <input
                                type="radio"
                                name="university_id"
                                value=""
                                class="mt-1"
                                @checked($selectedUniversityId === null || $selectedUniversityId === '')
                            >
                            <span class="study-card-title">{{ $copy->get('profile.other_university') }}</span>
                        </div>
                    </label>
                </div>
                @error('university_id')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div class="study-card study-continue">
                <p class="study-card-title">{{ $copy->get('profile.semester') }}</p>
                <div class="mt-3 space-y-2">
                    @foreach ($semesters as $semester)
                        <label class="study-card study-card-ghost block cursor-pointer">
                            <div class="study-card-inner">
                                <input
                                    type="radio"
                                    name="semester_id"
                                    value="{{ $semester->id }}"
                                    class="mt-1"
                                    @checked((string) $selectedSemesterId === (string) $semester->id)
                                >
                                <span class="study-card-title">{{ $semester->name }}</span>
                            </div>
                        </label>
                    @endforeach
                    <label class="study-card study-card-ghost block cursor-pointer">
                        <div class="study-card-inner">
                            <input
                                type="radio"
                                name="semester_id"
                                value=""
                                class="mt-1"
                                @checked($selectedSemesterId === null || $selectedSemesterId === '')
                            >
                            <span class="study-card-title">{{ $copy->get('profile.skip_semester') }}</span>
                        </div>
                    </label>
                </div>
                @error('semester_id')
                    <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

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

            <button type="submit" class="tg-btn">
                {{ $copy->get('profile.save') }}
            </button>
        </form>
    </div>
</x-telegram.mini-app.layout>
