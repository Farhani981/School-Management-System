<nav class="directory-pagination" aria-label="Student pagination">
@if($students->onFirstPage())<span class="directory-page-disabled" aria-disabled="true">Prev</span>@else<a href="{{ $students->previousPageUrl() }}" rel="prev">Prev</a>@endif
@php($start=max(1,$students->currentPage()-2)) @php($end=min($students->lastPage(),$students->currentPage()+2))
@if($start>1)<a href="{{ $students->url(1) }}" aria-label="Page 1">1</a>@if($start>2)<span aria-hidden="true">…</span>@endif @endif
@for($page=$start;$page<=$end;$page++)@if($page===$students->currentPage())<span class="directory-page-current" aria-current="page">{{ $page }}</span>@else<a href="{{ $students->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>@endif @endfor
@if($end<$students->lastPage())@if($end<$students->lastPage()-1)<span aria-hidden="true">…</span>@endif<a href="{{ $students->url($students->lastPage()) }}" aria-label="Page {{ $students->lastPage() }}">{{ $students->lastPage() }}</a>@endif
@if($students->hasMorePages())<a href="{{ $students->nextPageUrl() }}" rel="next">Next</a>@else<span class="directory-page-disabled" aria-disabled="true">Next</span>@endif
</nav>
