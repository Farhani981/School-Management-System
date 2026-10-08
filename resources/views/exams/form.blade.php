@extends('layouts.admin')
@section('title', $exam->exists ? 'Edit Exam' : 'Add New Exam')
@section('content')
    <div class="exams-page exam-editor-page">
        <section class="panel exam-editor">
            <div class="exam-editor-heading">
                <h1>{{ $exam->exists ? 'Edit Exam' : 'Add New Exam' }}</h1><a href="{{ route('exams.index') }}"
                    aria-label="Close">&times;</a>
            </div>
            @include('subjects.partials.feedback')
            <form method="POST" action="{{ $exam->exists ? route('exams.update', $exam) : route('exams.store') }}">
                @csrf @if ($exam->exists)
                    @method('PUT')
                @endif
                <input type="hidden" name="session_id"
                    value="{{ old('session_id', $exam->session_id ?? $sessions->first()?->id) }}">
                <div class="exam-editor-fields">
                    <div class="admission-field exam-wide"><label for="exam-name">Exam Name</label><input id="exam-name"
                            name="name" value="{{ old('name', $exam->name) }}" required maxlength="150"
                            placeholder="Enter Exam name"></div>
                    <div class="admission-field exam-wide"><label for="exam-type">Exam Type</label><select id="exam-type" name="type" required>@foreach(\App\Models\Exam::TYPES as $value=>$label)<option value="{{ $value }}" @selected(old('type',$exam->type ?? 'monthly')===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="admission-field"><label for="exam-date">Exam Date</label><input id="exam-date"
                            type="date" name="start_date"
                            value="{{ old('start_date', $exam->start_date?->format('Y-m-d')) }}" required></div>
                    <div class="admission-field"><label for="exam-start-time">Start Time</label><input id="exam-start-time"
                            type="time" name="start_time"
                            value="{{ old('start_time', $exam->start_time ? substr($exam->start_time, 0, 5) : '') }}" required>
                    </div>
                    <div class="admission-field"><label for="exam-end-time">End Time</label><input id="exam-end-time"
                            type="time" name="end_time"
                            value="{{ old('end_time', $exam->end_time ? substr($exam->end_time, 0, 5) : '') }}" required></div>
                    <div class="admission-field"><label for="exam-status">Status</label><select id="exam-status" disabled>
                            <option>Pending</option>
                        </select><small>Scheduled after paper dates are saved; active after publication on the exam
                            date.</small></div>
                        <div class="admission-field exam-wide"><label for="exam-end">Last Exam Date</label><input
                                id="exam-end" type="date" name="end_date"
                                value="{{ old('end_date', $exam->end_date?->format('Y-m-d')) }}" required></div>
                    <div class="exam-wide">
                        <h2>Participating classes & sections</h2>
                        <p class="photo-help">Choose the classes taking this exam. Existing subject teachers can be loaded
                            in the next step.</p>
                        @php $selected=old('participants',$exam->exists ? $exam->participations->map(fn($p)=>$p->class_id.':'.$p->section_id)->all() : []); @endphp
                        <div class="exam-class-grid">
                            @foreach ($classes as $class)
                                <fieldset class="exam-class-option">
                                    <legend>{{ $class->name }}</legend>
                                    <div class="exam-checks">
                                        @foreach ($pairs->where('class_id', $class->id) as $pair)
                                            <label><input type="checkbox" name="participants[]"
                                                    value="{{ $class->id }}:{{ $pair->section_id }}"
                                                    @checked(in_array($class->id . ':' . $pair->section_id, $selected))>
                                                {{ $sections[$pair->section_id]->name }}</label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="exam-editor-footer"><a class="exam-cancel-button"
                        href="{{ route('exams.index') }}">Cancel</a><button class="button-primary"
                        @disabled($sessions->isEmpty() || $classes->isEmpty())>Save</button></div>
            </form>
        </section>
    </div>
@endsection
