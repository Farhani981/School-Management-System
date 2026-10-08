<div class="page-heading"><div><div class="breadcrumb">Fees <span>/</span> Fee Setup</div><h1>{{ $title }}</h1><p>{{ $description ?? 'Manage fee rules for your school.' }}</p></div>@if(isset($action))<a class="button-primary" href="{{ $action['url'] }}">{{ $action['label'] }}</a>@endif</div>
@include('students.partials.feedback')
<nav class="profile-tabs fee-navigation" aria-label="Fee setup views">
@foreach(['types.index'=>'Fee Types', 'structures.index'=>'Class-wise Structure', 'discounts.index'=>'Discounts', 'settings.index'=>'Fee Settings'] as $suffix=>$label)
<a @class(['workflow-tab', 'fee-nav-'.str_replace('.index', '', $suffix), 'active'=>request()->routeIs('fees.setup.'.str_replace('.index','.*',$suffix))]) href="{{ route('fees.setup.'.$suffix) }}">{{ $label }}</a>
@endforeach
</nav>
