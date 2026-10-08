<details class="panel student-form teacher-setup"><summary>Manage teacher setup</summary><p class="photo-help">Add departments, designations, subjects and leave types. Classes, sections and sessions are managed in <a href="{{ route('academic-setup.index') }}">Academic Setup</a>.</p>
<form method="POST" action="{{ route('teachers.setup') }}" class="student-form-grid">@csrf
<div class="admission-field"><label for="setup-type">Type</label><select id="setup-type" name="type"><option value="department">Department</option><option value="designation">Designation</option><option value="subject">Subject</option><option value="leave_type">Leave type</option></select></div>
<div class="admission-field"><label for="setup-name">Name</label><input id="setup-name" name="name" required maxlength="100"></div>
<div class="admission-field"><label for="annual-days">Annual leave days (leave types)</label><input id="annual-days" name="annual_days" type="number" value="12" min="0" max="366"></div>
<div class="admission-field"><label><input type="checkbox" name="is_paid" value="1" checked> Paid leave type</label><button class="button-secondary">Save option</button></div>
</form></details>
