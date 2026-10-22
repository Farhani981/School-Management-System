@props([
    'headers' => [],
    'title' => null,
])

<div {{ $attributes->merge(['class' => 'portal-table-container']) }}>
    @if($title || isset($headerActions))
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--portal-border-subtle);">
            @if($title)
                <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--portal-text);">{{ $title }}</h3>
            @endif
            @if(isset($headerActions))
                <div>{{ $headerActions }}</div>
            @endif
        </div>
    @endif
    <div class="portal-table-scroll">
        <table class="portal-table">
            @if(!empty($headers))
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
