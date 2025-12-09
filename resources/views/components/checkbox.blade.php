<div class="form-check @if($isSwitch) form-switch @endif {{ $containerClass }} @if($isInline) form-check-inline @endif">
  <input {!! $attributes->merge(['class' => "form-check-input input"]) !!} type="checkbox" value="{{ $value }}" id="{{ $id }}" name="{{ $name }}" @if($checked) checked @endif/>
  <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
</div>
