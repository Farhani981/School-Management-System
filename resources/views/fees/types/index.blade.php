@extends('layouts.admin')
@section('title','Fee Types')
@section('content')
@include('fees.partials.header',['title'=>'Fee Types','action'=>['url'=>route('fees.setup.types.create'),'label'=>'+ Add Fee Type']])
<section class="panel student-panel"><form class="student-form teacher-filters" method="GET" action="{{ route('fees.setup.types.index') }}" data-auto-filters><div class="admission-field"><label for="fee-type-search">Search</label><input id="fee-type-search" name="search" value="{{ request('search') }}" placeholder="Fee type name"></div><button class="button-secondary">Search</button></form>
<div class="student-table-scroll"><table class="student-table"><thead><tr><th>Name</th><th>Frequency</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($types as $type)<tr><td>{{ $type->name }}</td><td>{{ \App\Models\FeeType::FREQUENCIES[$type->frequency] }}</td><td><span class="student-status {{ $type->is_active?'status-active':'status-inactive' }}">{{ $type->is_active?'Active':'Inactive' }}</span></td><td><div class="student-actions"><a class="action-edit" href="{{ route('fees.setup.types.edit',$type) }}">Edit</a><button type="button" class="action-delete" data-fee-delete="{{ route('fees.setup.types.destroy',$type) }}" data-fee-name="{{ $type->name }}">Delete</button></div></td></tr>
@empty<tr><td colspan="4"><div class="students-empty"><p>No matching fee types. Add one to begin.</p></div></td></tr>@endforelse
</tbody></table></div><div class="student-pagination">{{ $types->links() }}</div></section>
@include('fees.partials.delete-dialog')
@endsection
