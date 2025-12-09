<div class="{{ $containerClass }}">
  <label for="{{ $id }}" class="form-label m-0">{{ $label }} @if ($required) <span class="text-danger">*</span> @endif</label>
  <textarea style="resize:none" {!! $attributes->merge(['class' => "form-control input"]) !!} id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}">{{ $value }}</textarea>
  <span id="help-block-{{ $id }}" class="help-block text-danger"></span>
</div>
