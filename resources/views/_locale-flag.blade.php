@php($ronoveFlagCode = \Azuriom\Plugin\Ronove\Support\CountryFlag::normalize($flagCode ?? null))
@php($ronoveFlagEmoji = \Azuriom\Plugin\Ronove\Support\CountryFlag::emoji($ronoveFlagCode))

<span class="ronove-locale-flag d-inline-flex align-items-center justify-content-center" @if($ronoveFlagCode) title="{{ $localeName ?? $ronoveFlagCode }} ({{ $ronoveFlagCode }})" @endif aria-hidden="true">
    @if($ronoveFlagEmoji)
        {{ $ronoveFlagEmoji }}
    @else
        <i class="bi bi-globe2"></i>
    @endif
</span>
@if(isset($localeName))
    <span class="visually-hidden">{{ $localeName }}</span>
@endif
