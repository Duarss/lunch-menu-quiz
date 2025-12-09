<div class="{{ $containerClass }}">
  @if (isset($label))
    <label for="{{ $id }}" class="form-label mb-0">{{ $label }} @if ($required) <span class="text-danger">*</span> @endif</label>
  @endif
  <select {!! $attributes->merge(['class' => "form-select select"]) !!} id="{{ $id }}" name="{{ $name }}" @if ($required) required @endif @if (isset($multiple)) multiple @endif @if ($select2)
    style="width: 100%"
  @endif>
    @if(!$multiple)
      @if(!$noEmptyOption)
        @if(isset($placeholder))
          <option value="" selected>{{ $placeholder }}</option>
        @elseif (isset($label))
          <option value="" selected>Pilih {{ strtolower($label) }}</option>
        @else
          <option value="" selected>Tidak ada</option>
        @endif
      @endif
    @endif
    @foreach ($options as $key => $value)
      <option value="{{ $key }}">{{ $value }}</option>
    @endforeach
  </select>
  @if($help) <div id="{{ $id }}-help-container" class="form-text text-info">{{ $help }}</div> @endif
  <div id="{{ $name }}-invalid-feedback" class="invalid-feedback d-none"></div>
</div>
@if($select2)
  @pushOnce('js') <script src="{{ asset('js/select2.min.js') }}"></script> @endPushOnce
  @pushOnce('css') <link rel="stylesheet" href="{{ asset('css/select2.min.css') }}"> @endPushOnce
@endif
