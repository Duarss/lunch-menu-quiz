@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h5 class="card-title mb-3 text-center text-md-start">Pengaturan Waktu Pengambilan Lunch</h5>
                    <p class="text-muted mb-4 text-center text-md-start">
                        Tentukan rentang jam pengambilan lunch untuk karyawan pada setiap hari (Senin - Kamis).
                        Karyawan hanya dapat melakukan scan barcode ID pada rentang waktu yang ditentukan.
                    </p>

                    <form id="form-lunch-window" class="d-grid gap-3">
                        @csrf
                        @method('PUT')
                        @foreach($dayLabels as $dayKey => $label)
                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-3">
                                    <strong class="d-block text-center text-md-start">{{ $label }}</strong>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label for="start_{{ $dayKey }}" class="form-label">Jam Mulai</label>
                                    <input
                                        type="time"
                                        class="form-control"
                                        id="start_{{ $dayKey }}"
                                        name="windows[{{ $dayKey }}][start_time]"
                                        value="{{ old("windows.$dayKey.start_time", data_get($windows, "$dayKey.start_time")) }}"
                                    >
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label for="end_{{ $dayKey }}" class="form-label">Jam Selesai</label>
                                    <input
                                        type="time"
                                        class="form-control"
                                        id="end_{{ $dayKey }}"
                                        name="windows[{{ $dayKey }}][end_time]"
                                        value="{{ old("windows.$dayKey.end_time", data_get($windows, "$dayKey.end_time")) }}"
                                    >
                                </div>
                            </div>
                        @endforeach

                        <div class="alert alert-info mt-2">
                            <small>
                                Kosongkan kedua kolom pada suatu hari apabila pengambilan lunch tidak dibuka pada hari tersebut.
                            </small>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-2">
                            <button type="reset" class="btn btn-outline-secondary btn-sm flex-grow-1 flex-sm-grow-0">Reset</button>
                            <button type="button" class="btn btn-primary btn-sm flex-grow-1 flex-sm-grow-0" id="btn-save-windows">Simpan Pengaturan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    $(function () {
        $(document).on('click', '#btn-save-windows', function () {
            const form = document.getElementById('form-lunch-window');
            if (!form) {
                return;
            }

            loadingScreen();

            const formData = new FormData(form);

            ajaxPost({
                url: '{{ route('lunchWindow.update') }}',
                formData: formData,
                successCallback: function (response) {
                    Swal.fire('Berhasil', response.message || 'Pengaturan waktu berhasil disimpan.', 'success');
                },
                errorCallback: function (response) {
                    let message = 'Pengaturan waktu gagal disimpan.';

                    if (response?.responseJSON?.errors) {
                        const errors = Object.values(response.responseJSON.errors);
                        if (errors.length && Array.isArray(errors[0]) && errors[0].length) {
                            message = errors[0][0];
                        }
                    } else if (response?.responseJSON?.message) {
                        message = response.responseJSON.message;
                    }

                    Swal.fire('Gagal', message, 'error');
                }
            });
        });
    });
</script>
@endpush
