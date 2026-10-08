@php
    $sections = [
        'Personal Information' => ['first_name' => 'First Name', 'last_name' => 'Last Name', 'b_form_number' => 'B-form Number', 'date_of_birth' => 'Date of Birth', 'gender' => 'Gender'],
        'Admission Information' => ['admission_no' => 'Admission No', 'admission_date' => 'Admission Date', 'academic_session_id' => 'Academic Session', 'class_id' => 'Class', 'section_id' => 'Section', 'roll_number' => 'Roll Number', 'status' => 'Status'],
        'Parent / Guardian Information' => ['father_name' => 'Father Name', 'mother_name' => 'Mother Name', 'guardian_name' => 'Guardian Name', 'guardian_relationship' => 'Relationship', 'guardian_phone' => 'Guardian Phone', 'emergency_contact' => 'Emergency Contact', 'family_id' => 'Family / Guardian ID'],
        'Contact Information' => ['phone' => 'Phone', 'email' => 'Email', 'address' => 'Current Address', 'permanent_address' => 'Permanent Address'],
        'Previous School Details' => ['previous_school' => 'Previous School Name', 'last_class' => 'Last Class', 'leaving_reason' => 'Leaving Reason'],
        'Medical Details' => ['blood_group' => 'Blood Group', 'allergies' => 'Allergies / Conditions', 'height' => 'Height', 'weight' => 'Weight'],
        'Remarks' => ['remarks' => 'Description / Remarks'],
    ];
    unset($sections['Parent / Guardian Information']['mother_name']);
    if ($admissionDesign ?? false) {
        $sections = [
            'Personal Information' => array_merge($sections['Admission Information'], $sections['Personal Information']),
            'Parent / Guardian Information' => $sections['Parent / Guardian Information'],
            'Contact Information' => $sections['Contact Information'],
            'Previous School Details' => $sections['Previous School Details'],
            'Medical Details' => $sections['Medical Details'],
            'Remarks' => $sections['Remarks'],
        ];
    }
@endphp
@foreach($sections as $title => $fields)
<fieldset class="admission-section">
    <legend>{{ $title }}</legend>
    @if($title === 'Parent / Guardian Information')
        <p class="photo-help">Father name is required. If another person is the guardian, enter their name, relationship and phone together. Otherwise, you can use the father's details.</p>
        <button type="button" class="button-secondary" id="use-father-guardian">Use Father as Guardian</button>
    @endif
    <div class="student-form-grid">
    @foreach($fields as $field => $label)
        @php
            $optional = in_array($field, ['last_name', 'class_id', 'section_id', 'academic_session_id', 'b_form_number', 'mother_name', 'guardian_name', 'guardian_relationship', 'guardian_phone', 'emergency_contact', 'permanent_address', 'family_id', 'email', 'previous_school', 'last_class', 'leaving_reason', 'blood_group', 'allergies', 'height', 'weight', 'remarks']) || ($field === 'admission_no' && !$student->exists);
            if (isset($application) && in_array($field, ['class_id', 'section_id', 'academic_session_id'])) $optional = false;
            $isDate = in_array($field, ['date_of_birth', 'admission_date']);
            $isNumber = in_array($field, ['class_id', 'section_id']);
            $isFullWidth = in_array($field, ['address', 'permanent_address', 'leaving_reason', 'allergies', 'remarks']);
            $value = $isDate ? $student->$field?->format('d-m-Y') : $student->$field;
            if ($field === 'academic_session_id' && !$student->exists && !$value) $value = \App\Models\AcademicSession::where('is_current', true)->value('id');
            $value = old($field, $value ?? ($field === 'status' ? 'Active' : ($field === 'admission_date' ? now()->format('d-m-Y') : '')));
            if ($isDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) $value = \Carbon\Carbon::parse($value)->format('d-m-Y');
        @endphp
        <div @class(['admission-field', 'student-address' => $isFullWidth])>
            <label for="{{ $field }}">{{ $label }} @unless($optional)<span class="required-mark">*</span>@endunless</label>
            @if($field === 'admission_no' && !$student->exists)
                <input id="admission_no" value="Generated when saved" readonly aria-describedby="admission-number-help">
            @elseif($isDate)
                <input id="{{ $field }}" name="{{ $field }}" type="text" inputmode="numeric" placeholder="dd-mm-yyyy" pattern="\d{2}-\d{2}-\d{4}" maxlength="10" value="{{ $value }}" required aria-describedby="{{ $field }}-error">
            @elseif(isset($options[$field]))
                <select id="{{ $field }}" name="{{ $field }}" @required(!$optional)><option value="">Select {{ strtolower($label) }}</option>@foreach($options[$field] as $id => $name)<option value="{{ $id }}" @selected((string)$value === (string)$id)>{{ $name }}</option>@endforeach</select>
                @php($optionType = ['class_id' => 'class', 'section_id' => 'section', 'academic_session_id' => 'session'][$field])
                <details class="academic-quick-add" data-target="{{ $field }}" data-type="{{ $optionType }}" data-url="{{ route('academic-setup.store') }}">
                    <summary>Add new {{ strtolower($label) }}</summary>
                    <label for="new-{{ $field }}">New {{ strtolower($label) }} name</label>
                    <input id="new-{{ $field }}" type="text" maxlength="100" placeholder="{{ ['class' => 'e.g. Grade 1', 'section' => 'e.g. A', 'session' => 'e.g. 2026-2027'][$optionType] }}">
                    <button type="button" class="button-secondary" data-save-option>Add {{ $label }}</button>
                    <p role="status" data-option-message></p>
                </details>
                @if($options[$field]->isEmpty())<small data-empty-options="{{ $field }}">No options yet. Add one above, then select it.</small>@endif
            @elseif($field === 'guardian_relationship')
                <select id="{{ $field }}" name="{{ $field }}"><option value="">Select relationship</option>
                @foreach(\App\Models\Student::RELATIONSHIPS as $relationship)<option value="{{ $relationship }}" @selected($value === $relationship || ($relationship === 'Other' && $value && !in_array($value, \App\Models\Student::RELATIONSHIPS)))>{{ $relationship }}</option>@endforeach
                </select>
            @elseif($field === 'blood_group')
                <select id="{{ $field }}" name="{{ $field }}"><option value="">Select blood group</option>
                @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)<option value="{{ $bg }}" @selected($value === $bg)>{{ $bg }}</option>@endforeach
                </select>
            @elseif(in_array($field, ['gender', 'status']))
                <select id="{{ $field }}" name="{{ $field }}" required aria-describedby="{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">
                    <option value="">Select {{ strtolower($label) }}</option>
                    @foreach($field === 'gender' ? ['Male', 'Female', 'Other'] : (isset($application) ? ['Active'] : \App\Models\Student::STATUSES) as $option)
                        <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @elseif($isFullWidth)
                <textarea id="{{ $field }}" name="{{ $field }}" rows="{{ in_array($field, ['remarks', 'address', 'permanent_address']) ? 3 : 2 }}" maxlength="2000" @required(!$optional) aria-describedby="{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">{{ $value }}</textarea>
            @else
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $isDate ? 'date' : ($isNumber ? 'number' : ($field === 'phone' ? 'tel' : ($field === 'email' ? 'email' : 'text'))) }}" value="{{ $value }}" @required(!$optional) @if($isNumber) min="1" placeholder="Optional {{ strtolower($label) }} ID" @endif @if(!$isDate && !$isNumber) maxlength="{{ $field === 'phone' ? 30 : 255 }}" @endif @if($field === 'date_of_birth') max="{{ now()->format('Y-m-d') }}" @endif aria-describedby="{{ $field }}-error" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}">
            @endif
            <span id="{{ $field }}-error" class="field-error">@error($field){{ $message }}@enderror</span>
            @if($field === 'admission_no' && !$student->exists)<small id="admission-number-help">Assigned automatically, e.g. ADM-{{ now()->format('Y') }}-0001.</small>@endif
            @if($field === 'family_id')<small>Use the same ID for siblings. Father name and phone also link siblings automatically.</small>@endif
        </div>
    @endforeach
    </div>
</fieldset>
@endforeach
<fieldset class="admission-section">
    <legend>Student Photo</legend>
    <div class="photo-upload">
        @php($hasPhoto = $student->photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($student->photo))
        <div class="photo-preview"><img id="student-photo-preview" @if($hasPhoto) src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($student->photo) }}" @else hidden @endif alt="Student photo preview"><span id="student-photo-placeholder" @if($hasPhoto) hidden @endif aria-label="Student initials">{{ mb_strtoupper(mb_substr(old('first_name', $student->first_name ?? ''), 0, 1).mb_substr(old('last_name', $student->last_name ?? ''), 0, 1)) ?: '?' }}</span></div>
        <div><label for="photo">Upload Photo</label><input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help photo-error"><p id="photo-help" class="photo-help">JPG, PNG or WebP. Maximum 10 MB. Optional.</p><span id="photo-error" class="field-error">@error('photo'){{ $message }}@enderror</span></div>
    </div>
</fieldset>

<fieldset class="admission-section"><legend>Admission Documents</legend><div class="admission-field"><label for="documents">B-form, previous school certificate or other documents</label><input id="documents" type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png"><p class="photo-help">Optional. Up to 10 files per upload; PDF, JPG or PNG, maximum 5 MB each.</p></div></fieldset>
