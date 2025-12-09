@if($type == 'password')
  <div class="form-password-toggle {{ $containerClass }}">
    <div class="d-flex justify-content-between">
      <label class="form-label mb-0" for="{{ $id }}">{{ $label }} @if ($required) <span class="text-danger">*</span> @endif</label>
    </div>
    <div class="input-group input-group-merge">
      <input
        type="password" id="{{ $id }}"
        name="{{ $name }}"
        {!! $attributes->merge(['class' => "form-control input"]) !!}
        placeholder="{{ $placeholder ?? ucfirst($label).'..' }}" aria-describedby="{{ $placeholder ?? ucfirst($label).'..' }}"
        value="{{ $value }}"
        />
      <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
    </div>
  </div>
@else
  @if($type != 'hidden')
  <div class="row" id="container-{{ $id }}">
    <div class="col {{ $containerClass }}">
        @if(isset($label))
          <label id="label_{{ $id }}" for="{{ $id }}" class="form-label mb-0">{{ $label }} @if ($required) <span class="text-danger">*</span> @endif</label>
        @endif
  @endif
        <input
          {!! $attributes->merge(['class' => "form-control input"]) !!}
          type="{{ $type }}" id="{{ $id }}"
          name="{{ $name }}"
          placeholder="{{ $placeholder ?? ucfirst($label).'..' }}"
          value="{{ $value }}"
          @if($required) required @endif
          @if($type=='number')
            @if ($min) min = "{{ $min }}" @endif
            @if ($max) max = "{{ $max }}" @endif
            step = "{{ $step ?? 1 }}"
          @endif
          @if($disabled) disabled="disabled" @endif
        >
        @if($help) <div id="{{ $id }}-help-container" class="form-text text-info">{{ $help }}</div> @endif
        <div id="{{ $name }}-invalid-feedback" class="invalid-feedback d-none"></div>
  @if($type != 'hidden')
      </div>
  </div>
  @endif
@endif
@if(isset($attributes['class']) && str_contains($attributes['class'],'numeric'))
  @pushOnce('css')
    <style>
      .numeric{
        text-align: right;
      }
    </style>
  @endPushOnce

  @pushOnce('js')
    <script src="{{ asset('js/autoNumeric.min.js') }}"></script>
  @endPushOnce
@endif

@pushOnce('js')
  <script>
    $(function(){
      $(document).on('input', '.input', function(){
        if($(this).hasClass('is-invalid')) $(this).removeClass('is-invalid')
      })
    })
  </script>
@endPushOnce
