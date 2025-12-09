@php
    $isIcon = isset($icon) && !empty($icon);
    $hasLabel = isset($label) && !empty($label);
    $isDisabled = isset($disabled) && $disabled;
    // If we have both icon and label, use a standard button size with flex for inline display.
    if ($isIcon && $hasLabel) {
        $baseClass = 'btn btn-sm d-inline-flex align-items-center gap-1';
    } elseif ($isIcon && !$hasLabel) {
        $baseClass = 'btn btn-icon';
    } else {
        $baseClass = 'btn btn-sm';
    }
    $finalClass = $baseClass . ($isDisabled ? ' btn-disabled' : '');

    // Ambil style existing jika ada, lalu merge dengan width jika diberikan
    $customStyle = $attributes->get('style');
    $widthStyle = isset($width) ? "width: $width;" : '';
    $mergedStyle = trim($customStyle . ' ' . $widthStyle);

    // Ambil alignment jika dikirim (misal: text-start, text-center, text-end, d-flex justify-content-center, etc.)
    $alignClass = $align ?? '';
@endphp

<div class="{{ $alignClass }}">
    @if (isset($url))
        <a
            {!! $attributes->merge([
                'class' => $finalClass,
                'style' => $mergedStyle
            ]) !!}
            href="{{ $url }}"
            @if($isDisabled) aria-disabled="true" tabindex="-1" @endif
        >
            @if($icon)
                <span class="tf-icons bx {{ $icon }} bx-flashing-hover @if($hasLabel) me-1 @endif"></span>
            @endif
            @if($hasLabel)
                <span @if ($attributes->has('id')) id="{{ $attributes->get('id') }}-label" @endif>{{ $label }}</span>
            @endif
        </a>
    @else
        <button
            type="button"
            {!! $attributes->merge([
                'class' => $finalClass,
                'style' => $mergedStyle
            ]) !!}
            @if($isDisabled) disabled @endif
        >
            @if($icon)
                <span class="tf-icons bx {{ $icon }} bx-flashing-hover @if($hasLabel) me-1 @endif"></span>
            @endif
            @if($hasLabel)
                <span @if ($attributes->has('id')) id="{{ $attributes->get('id') }}-label" @endif>{{ $label }}</span>
            @endif
        </button>
    @endif
</div>
