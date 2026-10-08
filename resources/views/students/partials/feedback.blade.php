@if(session('success'))<div class="student-feedback" role="status"><x-icon name="check" size="16" />{{ session('success') }}</div>@endif
@if($errors->any())<div class="student-errors" role="alert"><strong>Please check the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
