<fieldset class="admission-section" data-salary-section>
    <legend>Salary</legend>
    <div class="student-form-grid">
        @foreach(['basic_salary' => 'Basic Salary', 'house_allowance' => 'House Allowance', 'medical_allowance' => 'Medical Allowance', 'transport_allowance' => 'Transport Allowance', 'other_allowance' => 'Other Allowance', 'fixed_deductions' => 'Fixed Deductions'] as $field => $label)
        <div class="admission-field">
            <label for="teacher-{{ $field }}">{{ $label }} @if($field === 'basic_salary')<span class="required-mark">*</span>@endif</label>
            <div class="salary-amount"><span aria-hidden="true">Rs.</span><input id="teacher-{{ $field }}" name="{{ $field }}" type="number" step="0.01" min="{{ $field === 'basic_salary' ? 1 : 0 }}" max="99999999.99" value="{{ old($field, $salary?->$field ?? ($field === 'basic_salary' ? '' : 0)) }}" @required($field === 'basic_salary') data-salary-input aria-describedby="{{ $field }}-error"></div>
            <span id="{{ $field }}-error" class="field-error">@error($field){{ $message }}@enderror</span>
        </div>
        @endforeach
        @foreach(['bank_name' => 'Bank Name', 'account_no' => 'Account No'] as $field => $label)
        <div class="admission-field"><label for="teacher-{{ $field }}">{{ $label }}</label><input id="teacher-{{ $field }}" name="{{ $field }}" type="text" maxlength="{{ $field === 'account_no' ? 100 : 255 }}" value="{{ old($field, $salary?->$field) }}">@error($field)<span class="field-error">{{ $message }}</span>@enderror</div>
        @endforeach
        @foreach(['gross_salary' => 'Gross Salary', 'net_salary' => 'Net Salary'] as $field => $label)
        <div class="admission-field"><label for="teacher-{{ $field }}">{{ $label }} (calculated)</label><div class="salary-amount salary-calculated"><span aria-hidden="true">Rs.</span><input id="teacher-{{ $field }}" type="text" readonly value="{{ number_format($salary?->$field ?? 0, 2, '.', '') }}" data-salary-total="{{ $field }}"></div></div>
        @endforeach
    </div>
    <p class="photo-help">Gross = basic + all allowances. Net = gross − fixed deductions. Optional amounts default to zero.</p>
</fieldset>
