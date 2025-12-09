<div class="table-responsive table-wrapper @if(!$noTextWrap) text-nowrap @endif" id="{{ $id }}-table-wrapper">
  <table id="{{ $id }}" {!! $attributes->merge(['class' => 'table table-sm table-hover table-striped w-100' . ($centerHeaders ? ' center-th' : '')]) !!}>
    @if($caption)
    <caption style="text-align:left;caption-side:top">{{ $caption }}</caption>
    @endif
    {!! $slot !!}
  </table>
</div>
@if($useDatatable)
  @pushOnce('css')
    <link rel="stylesheet" href="{{ asset('DataTables/datatables.min.css') }}">
  @endPushOnce
  @pushOnce('js')
    <script src="{{ asset('DataTables/datatables.min.js') }}"></script>
  @endPushOnce
  @push('js')
    <script>
      let {{ str(str($id)->replace('-','_'))->camel() }} = null
    </script>
  @endpush
@endif

