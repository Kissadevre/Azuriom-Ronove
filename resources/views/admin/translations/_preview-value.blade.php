<div class="border rounded p-3 h-100 bg-body-tertiary text-break">
    @if($value === null || $value === '')
        <span class="text-body-secondary">{{ trans('ronove::admin.translations.preview_empty') }}</span>
    @elseif($definition->type === \Azuriom\Plugin\Ronove\Support\TranslatableField::RICH_TEXT)
        {!! $value !!}
    @elseif($definition->type === \Azuriom\Plugin\Ronove\Support\TranslatableField::MARKDOWN)
        {!! \Azuriom\Support\Markdown::parse($value) !!}
    @else
        <span style="white-space: pre-wrap">{{ $value }}</span>
    @endif
</div>
