@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h5 class="card-title mb-3 text-center text-md-start">Pengaturan Waktu Pengambilan Lunch</h5>
                    <p class="text-muted mb-4 text-center text-md-start">
                        Tambahkan tanggal pengambilan lunch dan tentukan rentang waktunya. Karyawan hanya dapat melakukan
                        scan barcode ID pada tanggal dan jam yang terdaftar.
                    </p>

                    <x-form id="form-lunch-window" class="d-grid gap-3">
                        <div id="windows-wrapper" class="d-grid gap-4"></div>

                        <x-button id="btn-add" class="btn-outline-primary" label="Tambah Tanggal" />

                        <div class="alert alert-info mt-2">
                            <small>
                                Simpan hanya tanggal yang membuka pengambilan lunch. Hapus baris untuk menghilangkan tanggal tersebut.
                            </small>
                        </div>

                        <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-2">
                            <x-button id="btn-reset" class="btn-outline-secondary flex-grow-1 flex-sm-grow-0" label="Reset" />
                            <x-button id="btn-save" class="btn-primary flex-grow-1 flex-sm-grow-0" label="Simpan Pengaturan" />
                        </div>
                    </x-form>

                    <hr class="my-4">

                    <h6 class="card-title mb-3 text-center text-md-start">Jadwal Tersimpan</h6>
                    <x-table id="table-lunch-windows" use-datatable>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </x-table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    $(function () {
        const wrapper = $('#windows-wrapper');
        const template = document.getElementById('window-row-template');
        const datatableEndpoint = '{{ route('datatables.lunch-windows') }}';
        let windowIndex = wrapper.find('.window-row').length;
        let windowsDataTable = null;

        function formatTimeValue(value) {
            if (value === null || value === undefined) {
                return '';
            }

            const stringValue = String(value).trim();

            if (!stringValue) {
                return '';
            }

            if (/^\d{1,2}:\d{2}(:\d{2})?$/.test(stringValue)) {
                return stringValue.substring(0, 5);
            }

            return stringValue;
        }

        function normalizeWindows(rows) {
            if (!Array.isArray(rows)) {
                return [];
            }

            return rows
                .map(function (row) {
                    if (!row || typeof row !== 'object') {
                        return null;
                    }

                    const date = (row.date ?? '').trim();

                    return {
                        id: row.id ?? '',
                        date: date,
                        start_time: formatTimeValue(row.start_time ?? ''),
                        end_time: formatTimeValue(row.end_time ?? ''),
                    };
                })
                .filter(function (row) {
                    return row && row.date;
                });
        }

        const serverWindows = normalizeWindows(@json($windows));
        const oldWindows = normalizeWindows(@json(old('windows')));
        const initialWindows = oldWindows.length ? oldWindows : serverWindows;
        let baselineWindows = initialWindows.slice();
        let resetSnapshot = baselineWindows.slice();

        // console.log("baselineWindows from script:", baselineWindows)

        function buildRow(data) {
            if (!template) {
                return '';
            }

            const raw = template.innerHTML;

            return raw
                .replace(/__INDEX__/g, data.index ?? 0)
                .replace(/__ID__/g, data.id ?? '')
                .replace(/__DATE__/g, data.date ?? '')
                .replace(/__START__/g, data.start_time ?? '')
                .replace(/__END__/g, data.end_time ?? '');
        }

        function refreshIndexes() {
            wrapper.find('.window-row').each(function (rowIndex) {
                const row = $(this);
                row.attr('data-index', rowIndex);

                row.find('[data-field]').each(function () {
                    const field = $(this);
                    const fieldName = field.data('field');
                    if (!fieldName) {
                        return;
                    }

                    const inputName = `windows[${rowIndex}][${fieldName}]`;
                    field.attr('name', inputName);

                    if (field.attr('type') !== 'hidden') {
                        const inputId = `window_${fieldName}_${rowIndex}`;
                        field.attr('id', inputId);
                        row.find(`label[data-field="${fieldName}"]`).attr('for', inputId);
                    }
                });
            });

            windowIndex = wrapper.find('.window-row').length;
        }

        function appendEmptyRow() {
            const html = buildRow({ index: windowIndex });
            if (!html) {
                return;
            }

            wrapper.append(html);
            refreshIndexes();
        }

        function loadWindowsFromData(rows) {
            const data = normalizeWindows(rows);

            if (!data.length) {
                wrapper.empty();
                windowIndex = 0;
                appendEmptyRow();
                return;
            }

            wrapper.empty();

            data.forEach(function (row, index) {
                const html = buildRow({
                    index,
                    id: row.id || '',
                    date: row.date || '',
                    start_time: row.start_time || '',
                    end_time: row.end_time || '',
                });

                if (html) {
                    wrapper.append(html);
                }
            });

            refreshIndexes();
        }

        function serializeWindows() {
            const raw = [];

            wrapper.find('.window-row').each(function () {
                const row = $(this);
                const entry = {
                    id: '',
                    date: '',
                    start_time: '',
                    end_time: '',
                };

                row.find('[data-field]').each(function () {
                    const field = $(this);
                    const key = field.data('field');

                    if (!key) {
                        return;
                    }

                    const value = (field.val && typeof field.val === 'function')
                        ? field.val()
                        : field.attr('value');

                    entry[key] = value ?? '';
                });

                raw.push(entry);
            });

            return normalizeWindows(raw);
        }

        function getTableInstance() {
            if (windowsDataTable) {
                return windowsDataTable;
            }

            if (typeof window.tableLunchWindows !== 'undefined' && window.tableLunchWindows) {
                windowsDataTable = window.tableLunchWindows;
                return windowsDataTable;
            }

            if ($.fn.DataTable && $.fn.DataTable.isDataTable && $.fn.DataTable.isDataTable('#table-lunch-windows')) {
                windowsDataTable = $('#table-lunch-windows').DataTable();
                window.tableLunchWindows = windowsDataTable;
                return windowsDataTable;
            }

            return null;
        }

        function initDataTable() {
            if (typeof dataTableInit !== 'function') {
                windowsDataTable = getTableInstance();
                return;
            }

            windowsDataTable = dataTableInit({
                selector: '#table-lunch-windows',
                title: 'Jadwal Pengambilan Lunch',
                rowIndex: false,
                btnDetails: false,
                btnActions: false,
                pageLength: 10,
                order: [[0, 'asc']],
                ajax: {
                    url: datatableEndpoint,
                },
                columns: [
                    {
                        data: 'date',
                        title: 'Tanggal',
                        render: function (data, type) {
                            if (type === 'display' || type === 'filter') {
                                return data || '-';
                            }
                            return data;
                        },
                    },
                    {
                        data: 'start_time',
                        title: 'Jam Mulai',
                        render: function (data, type) {
                            return data ? toShortTime(data) : '-';
                        },
                    },
                    {
                        data: 'end_time',
                        title: 'Jam Selesai',
                        render: function (data, type) {
                            return data ? toShortTime(data) : '-';
                        },
                    },
                ],
            });

            window.tableLunchWindows = windowsDataTable;
        }

        function reloadDataTable() {
            const tableInstance = getTableInstance();

            if (tableInstance && tableInstance.ajax && typeof tableInstance.ajax.reload === 'function') {
                tableInstance.ajax.reload(null, false);
            }
        }

        function showValidationErrors(response) {
            if (!response?.responseJSON?.errors) {
                const fallbackMessage = response?.responseJSON?.message || 'Pengaturan waktu gagal disimpan.';
                Swal.fire('Gagal', fallbackMessage, 'error');
                return;
            }

            const errors = response.responseJSON.errors;
            const messages = Object.values(errors)
                .filter(function (value) {
                    return Array.isArray(value) && value.length;
                })
                .map(function (value) {
                    return value[0];
                });

            const message = messages.length ? messages[0] : 'Pengaturan waktu gagal disimpan.';
            Swal.fire('Gagal', message, 'error');
        }

        loadWindowsFromData(baselineWindows);
        initDataTable();

        $(document).on('click', '#btn-save', function () {
            const form = document.getElementById('form-lunch-window');
            if (!form) {
                return;
            }

            loadingScreen();
            refreshIndexes();

            const formData = new FormData(form);

            ajaxPost({
                url: '{{ route('masterLunchPickupWindow.store') }}',
                formData: formData,
                successCallback: function (response) {
                    const message = response?.message || 'Pengaturan waktu berhasil disimpan.';
                    Swal.fire('Berhasil', message, 'success');

                    const nextWindows = response?.windows ?? serializeWindows();

                    baselineWindows = normalizeWindows(nextWindows);
                    resetSnapshot = baselineWindows.slice();

                    loadWindowsFromData(baselineWindows);
                    reloadDataTable();
                },
                errorCallback: function (response) {
                    showValidationErrors(response);
                }
            });
        });

        $(document).on('click', '#btn-add', function () {
            appendEmptyRow();
        });

        $(document).on('click', '.btn-remove', function () {
            const row = $(this).closest('.window-row');
            if (!row.length) return

            // const id = row.find('input[data-field="id"]').val() || null
            const dateValue = row.find('input[data-field="date"]').val()
            const date = (dateValue || '').toString().trim()

            if (!date) {
                Swal.fire("Gagal", "ID pengaturan tidak ditemukan. Tidak bisa menghapus data.", "error")
                return
            }

            let url = "{{ route("masterLunchPickupWindow.destroy", "-date-") }}".replace("-date-", encodeURIComponent(date));

            Swal.fire({
                title: "Hapus Pengaturan di Tanggal " + row.find('input[data-field="date"]').val() + "?",
                text: "Tindakan ini tidak dapat dibatalkan.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus",
                cancelButtonText: "Batal",
            }).then((result) => {
                if (!result.isConfirmed) return

                ajaxPost({
                    url: url,
                    formData: {_method: 'DELETE'},
                    successCallback: function (response) {
                        Swal.fire("Berhasil!", response.message || "Data pengaturan di tanggal " + date + " telah dihapus.", "success")
                        row.remove()
                        refreshIndexes()

                        if (!wrapper.find('.window-row').length) {
                            appendEmptyRow()
                        }
                        reloadDataTable()
                    },
                    errorCallback: function (response) {
                        Swal.fire("Gagal", response.message || "Data pengaturan gagal dihapus.", "error")
                    }
                })
            })
        });

        $(document).on('click', '#btn-reset', function () {
            loadWindowsFromData(resetSnapshot);
        });
    });
</script>
<template id="window-row-template">
    <div class="window-row row g-3 align-items-end" data-index="__INDEX__">
        <input type="hidden" data-field="id" name="windows[__INDEX__][id]" value="__ID__">

        <div class="col-12 col-lg-4">
            <label class="form-label" data-field="date">Tanggal</label>
            <input
                type="date"
                class="form-control"
                data-field="date"
                name="windows[__INDEX__][date]"
                value="__DATE__"
            >
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" data-field="start_time">Jam Mulai</label>
            <input
                type="time"
                class="form-control"
                data-field="start_time"
                name="windows[__INDEX__][start_time]"
                value="__START__"
            >
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <label class="form-label" data-field="end_time">Jam Selesai</label>
            <input
                type="time"
                class="form-control"
                data-field="end_time"
                name="windows[__INDEX__][end_time]"
                value="__END__"
            >
        </div>
        <div class="col-12 col-lg-2 d-flex">
            <button type="button" class="btn btn-outline-danger btn-sm w-100 btn-remove">Hapus</button>
        </div>
    </div>
</template>
@endpush
