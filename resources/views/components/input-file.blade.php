{{-- <div>
  <label for="{{ $id }}" class="mb-0">{{ $label }} @if ($required) <span class="text-danger">*</span> @endif</label>
  <div class="dropzone" id="{{ $id }}" name="{{ $name }}"></div>
</div>

@pushOnce('css')
  <link rel="stylesheet" href="{{ asset('css/dropzone.min.css') }}" type="text/css" />
  <style>
    .dz-error-mark svg g path{
      fill: #FF0000 !important;
    }
    .dropzone .dz-preview .dz-error-message {
      top: 150px!important;
    }
  </style>
@endPushOnce

@pushOnce('js')
  <script src="{{ asset('js/dropzone.min.js') }}"></script>
  <script> Dropzone.autoDiscover = false; </script>
@endPushOnce

@push('js')
  <script>
    let dz{{ ucfirst($id) }} = null
    $(document).ready(function() {
      dz{{ ucfirst($id) }} = dropzoneInit({
        id: '#{{ $id }}',
        url: '{{ route('uploader.dropzone') }}',
        inputName: '{{ $name }}[]',
        formId: '{{ $formId }}',
        acceptedFiles: '{{ $accepted }}',
        maxFiles: '{{ $maxFiles }}',
      })
    })
  </script>
@endPush
 --}}
