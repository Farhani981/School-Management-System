@if(session('success'))<p class="leave-message" role="status">{{ session('success') }}</p>@endif
@if($errors->any())<div class="leave-errors" role="alert"><p>Please correct the following:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
