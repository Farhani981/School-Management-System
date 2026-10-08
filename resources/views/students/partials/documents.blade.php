<section class="panel"><div class="panel-heading"><h2>Student Documents</h2></div><div class="student-form">
@forelse($documents as $document)
<div class="document-row"><a href="{{ route('students.documents.download', [$student, $document]) }}">{{ $document->name }}</a><small>{{ $document->created_at->format('d-m-Y') }}</small><form method="POST" action="{{ route('students.documents.destroy', [$student, $document]) }}" onsubmit="return confirm('Delete this document permanently?')">@csrf @method('DELETE')<button class="button-danger">Delete</button></form></div>
@empty<p>No documents uploaded.</p>@endforelse
<form method="POST" action="{{ route('students.documents.store', $student) }}" enctype="multipart/form-data">@csrf<div class="admission-field"><label for="profile-documents">Upload documents</label><input id="profile-documents" type="file" name="documents[]" multiple required accept=".pdf,.jpg,.jpeg,.png"><p>PDF, JPG or PNG. Maximum 5 MB each, up to 10 files per upload.</p></div><div class="form-actions"><button class="button-primary">Upload Documents</button></div></form>
</div></section>
