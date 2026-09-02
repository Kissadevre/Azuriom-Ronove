<header class="ronove-admin-header">
    <div class="ronove-admin-heading">
        <span class="ronove-admin-header-icon text-{{ $color ?? 'primary' }} bg-{{ $color ?? 'primary' }} bg-opacity-10" aria-hidden="true">
            <i class="bi {{ $icon }}"></i>
        </span>
        <div class="ronove-admin-header-copy">
            <span class="ronove-admin-eyebrow">{{ trans('ronove::admin.eyebrow') }}</span>
            <h1>{{ $title }}</h1>
            @isset($description)<p>{{ $description }}</p>@endisset
        </div>
    </div>
    @if(! empty($actions))
        <div class="ronove-admin-header-actions">
            @foreach($actions as $action)
                <a class="btn {{ $action['class'] ?? 'btn-outline-secondary' }}" href="{{ $action['url'] }}">
                    <i class="bi {{ $action['icon'] }} me-1" aria-hidden="true"></i>{{ $action['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</header>
@once
    <script>
        (() => document.currentScript.closest('.container-fluid')?.querySelector(':scope > h1.h3')?.classList.add('ronove-admin-layout-title'))();
    </script>
@endonce
