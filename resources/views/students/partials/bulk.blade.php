<form id="student-bulk-form" method="POST" action="{{ route('students.actions') }}" class="student-form bulk-toolbar directory-bulk" hidden>@csrf
<strong id="selection-count" role="status">0 selected</strong>
<select id="bulk-action" name="action" class="sr-only" aria-label="Selected student action"><option value="">Choose action</option><option value="status">Change status</option><option value="id_cards">Print ID Cards</option></select>
<button type="button" class="directory-bulk-button" data-directory-bulk="status">Change status</button><button type="button" class="directory-bulk-button" data-directory-bulk="id_cards">Print ID Cards</button><button type="button" class="directory-bulk-button" data-directory-promote>Promote</button><button type="button" class="directory-bulk-button directory-clear" data-directory-clear>Clear</button>
<div data-bulk-fields="status" hidden><label for="bulk-status" class="sr-only">New status</label><select id="bulk-status" name="status" disabled>@foreach(\App\Models\Student::STATUSES as $status)<option>{{ $status }}</option>@endforeach</select><button type="submit" class="button-primary" id="bulk-submit" disabled>Apply</button></div>
</form>
