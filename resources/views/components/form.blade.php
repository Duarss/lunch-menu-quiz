<form
    {!! $attributes !!}
    action="{{ $action }}"
    {{-- method="{{ $method != 'get' ? 'post' : 'get' }}" --}}
    method="{{ $method ? $method : 'get' }}"
    @if($sendFile) enctype="multipart/form-data" @endif
    >

    @if ($method != 'get')
        @method($method)
        @csrf
    @endif

    {!! $slot !!}
    @if($requiredNote)
      <p>Note : isian berbintang ( <span class="text-danger">*</span> ) wajib diisi</p>
    @endif
</form>
