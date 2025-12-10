@extends('layouts.app', ['title' => $title])

@section('content')
    @if(in_array($role, ['admin', 'bm']))
        <div class="row">
            <div class="card px-1">
                <div class="card-body pt-2 pb-0 px-0">
                    <div class="row mt-4">
                        <x-table use-datatable />
                    </div>
                </div>
            </div>
        </div>
    @elseif($role === 'karyawan')
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom-0">
                        <h5 class="mb-0">Ubah Password</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <small>
                                Untuk keamanan akun, ubahlah password Anda secara berkala dan gunakan kombinasi huruf, angka, dan simbol.
                            </small>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-form id="form-change-password">
                                    <x-input type="password" id="change_password" name="password" label="Password Baru" required />
                                    <x-input type="password" id="change_password_confirmation" name="password_confirmation" label="Konfirmasi Password Baru" required />
                                    <div class="mt-2">
                                        <x-button type="button" id="btn-change-password" class="btn-primary btn-sm" label="Simpan Password" />
                                    </div>
                                </x-form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if(in_array($role, ['admin', 'bm']))
        <x-modal id="creadit-modal" title="Kelola Master Karyawan">
            <x-form id="form-creadit">
                <div class="row my-2">
                    <div class="col">
                        <div class="d-grid gap-2">
                            <x-input
                                type="text"
                                id="user_name"
                                name="name"
                                label="Nama"
                                required
                            />
                            <x-input
                                type="text"
                                id="user_username"
                                name="username"
                                label="Username"
                                required
                            />
                            <small id="username-lock-note" class="text-muted d-none">
                                Username tidak dapat diubah setelah data tersimpan.
                            </small>
                            <x-select
                                select2
                                id="user_company_code"
                                name="company_code"
                                label="Perusahaan"
                                :options="$companyOptions"
                                placeholder="Pilih perusahaan"
                            />
                            <!-- password otomatis sama dengan username pada saat tambah -->
                            <x-input
                                type="text"
                                id="user_password"
                                name="password"
                                label="Password (otomatis disamakan dengan username)"
                                placeholder="Password mengikuti username"
                                :disabled="true"
                            />
                            <div id="reset-password-wrapper" class="mt-2 d-none">
                                <x-button
                                    id="btn-reset-password"
                                    class="btn-outline-info btn-sm"
                                    icon="bx-key"
                                    label="Reset Password ke Default"
                                />
                                <small class="text-muted d-block mt-1">
                                    Aksi ini akan menyamakan password dengan username saat ini.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </x-form>

            @slot('footer')
                <x-button class="btn-primary btn-sm" id="btn-save" label="Simpan" />
            @endslot
        </x-modal>
    @endif
@endsection

@push('js')
@if($role === 'karyawan')
<script>
    $(function () {
        $(document).on('click', '#btn-change-password', function () {
            loadingScreen();

            const formData = new FormData(document.getElementById('form-change-password'));

            ajaxPost({
                url: '{{ route('karyawan.change-password') }}',
                formData: formData,
                successCallback: function (response) {
                    if (response.success) {
                        Swal.fire('Berhasil', response.message || 'Password berhasil diubah.', 'success');
                        document.getElementById('form-change-password').reset();
                    }
                },
                errorCallback: function (response) {
                    let message = 'Terjadi kesalahan saat mengubah password.';
                    if (response && response.errors) {
                        const firstKey = Object.keys(response.errors)[0];
                        if (firstKey) {
                            message = response.errors[firstKey][0];
                        }
                    } else if (response && response.message) {
                        message = response.message;
                    }
                    Swal.fire('Gagal', message, 'error');
                }
            });
        });
    });
</script>
@endif

@if(in_array($role, ['admin', 'bm']))
<script>
    // penting: parameter route harus bernama "user" karena URI: master/user/{user}
    const showUserRoute    = "{{ route("masterUser.show", "__USERNAME__") }}";
    const viewDetailsUserRoute = "{{ route("masterUser.details", "__USERNAME__") }}";
    const updateUserRoute  = "{{ route("masterUser.update",  "__USERNAME__") }}";
    const destroyUserRoute = "{{ route("masterUser.destroy", "__USERNAME__") }}";
    const resetPasswordUserRoute = "{{ route("masterUser.resetPassword", "__USERNAME__") }}";
    const select2CompaniesUrl = "{{ route("select2.companies") }}";

    const title = "{{ $title }}"
    let dataUser

    $(function() {
        let scrollY = '74vh'
        const columns = [
            {
                title: "Nama",
                data: 'name',
            },
            {
                title: "Username",
                data: 'username',
            },
            {
                title: "Perusahaan",
                data: 'company',
                defaultContent: '-',
            },
            {
                title: "Role",
                data: 'role',
            },
            {
                title: "Pembaruan Terakhir",
                data: 'updated_at',
                render: function(data, type, row) {
                    return data ? toLongDateTime(data) : '-';
                }
            }
        ]

        table = dataTableInit({
            selector: "#table",
            title: "Daftar Karyawan",
            scrollY: scrollY,
            pageLength: 10,
            ajax: {
                url: "{{ route('datatables.master-user') }}",
                type: "POST",
                data: function(params) {
                    // params._token = "{{ csrf_token() }}"
                },
            },
            columns: columns,
            btnDetails: true,
            btnActions: true,
            buttons: [{
                text: "<i class='bx bx-plus'></i>",
                className: 'btn-sm btn-outline-secondary',
                titleAttr: 'Tambah ' + title + ' baru',
                action: function ( e, dt, node, config ) {
                    clearForm("#form-creadit")
                    dataUser = null
                    $("#form-creadit").removeAttr("data-username")
                    $("#user_username").prop('readonly', true)
                    $('#username-lock-note').removeClass('d-none')
                    $("#user_username").val('')
                    $("#user_company_code").val('').trigger('change')
                    $("#user_password").val('') // kosongkan password saat tambah baru
                    $('#reset-password-wrapper').addClass('d-none')
                    $('#btn-reset-password').prop('disabled', true)

                    // default ke mode manual
                    $('#tab-manual').addClass('active')
                    $('#pane-manual').addClass('show active')
                    $('#tab-import').removeClass('active')
                    $('#pane-import').removeClass('show active')
                    $('#btn-save').removeClass('d-none')
                    $('#btn-import').addClass('d-none')

                    $("#creadit-modal").modal("show")
                }
            }]
        })

        // inisialisasi select2 untuk perusahaan (ajax search) dengan helper global
        if (typeof select2Init === 'function') {
            select2Init({
                selector: '#user_company_code',
                url: select2CompaniesUrl,
                placeholder: 'Pilih atau ketik nama perusahaan',
                allowClear: true,
                minimumInputLength: 0,
                dropdownParent: $('#creadit-modal'),
            });
        }

        // Toggle tombol footer sesuai tab
        $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"]', function (event) {
            const target = $(event.target).attr('data-bs-target')
            if (target === '#pane-import') {
                $('#btn-save').addClass('d-none')
                $('#btn-import').removeClass('d-none')
            } else {
                $('#btn-save').removeClass('d-none')
                $('#btn-import').addClass('d-none')
            }
        })

        // simpan (tambah / edit) manual
        $(document).on('click', '#btn-save', function() {
            loadingScreen()

            const username = $("#form-creadit").attr("data-username")
            let url = "{{ route("masterUser.store") }}"

            const formData = new FormData(document.getElementById("form-creadit"))

            // console.log("Saving user, username:", username, dataUser)

            if (dataUser) {
                // mode edit
                url = updateUserRoute.replace('__USERNAME__', username)
                formData.append("_method", "PUT")
            }

            ajaxPost({
                url: url,
                formData: formData,
                successCallback: function(response) {
                    if (response.success) {
                        Swal.fire("Berhasil", response.message || "Data user berhasil disimpan.", "success")
                        table.ajax.reload()
                        $("#creadit-modal").modal("hide")
                    }
                },
                errorCallback: function(response) {
                    Swal.fire("Gagal", response.message || "Terjadi kesalahan saat menyimpan data user.", "error")
                }
            })
        })

        // detail
        $(document).on("click", ".btn-details", function() {
            const username = $(this).data("username")
            if (!username) return

            const detailsUrl = viewDetailsUserRoute.replace('__USERNAME__', encodeURIComponent(username))
            window.location.href = detailsUrl
        })

        // edit
        $(document).on("click", ".btn-edit", function () {
            const username = $(this).data('username')
            if (!username) return

            let url = showUserRoute.replace('__USERNAME__', encodeURIComponent(username))

            Swal.fire({
                title: "Lanjutkan?",
                text: "Ingin edit data karyawan ini?",
                icon: "question",
                showCancelButton: true,
                confirmButtonText: "Ya, lanjutkan",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (!result.isConfirmed) return

                ajaxGet({
                    url: url,
                    loading: false,
                    successCallback: function (response) {
                        if (!response.success) return

                        // tergantung bentuk respons dari backend:
                        // jika backend mengirim { success: true, data: { ...user fields... } }
                        // maka:
                        dataUser = response.data

                        clearForm("#form-creadit")
                        $('#reset-password-wrapper').addClass('d-none')
                        $('#btn-reset-password').prop('disabled', true)

                        if (dataUser) {
                            $("#form-creadit").attr("data-username", dataUser.username)
                            // console.log("Editing user:", dataUser.username)
                            $("#user_name").val(dataUser.name)
                            $("#user_username").val(dataUser.username)
                            $("#user_username").prop('readonly', true)
                            $('#username-lock-note').removeClass('d-none')
                            $("#user_password").val(dataUser.username)

                            // set perusahaan via select2Val jika ada company_code
                            if (dataUser.company && typeof select2Val === 'function') {
                                select2Val('user_company_code', {
                                    id: dataUser.company.code,
                                    text: dataUser.company.name
                                });
                            } else {
                                $("#user_company_code").val(null).trigger('change')
                            }

                            $('#reset-password-wrapper').removeClass('d-none')
                            $('#btn-reset-password').prop('disabled', false)
                        }

                        $("#creadit-modal").modal("show")
                    }
                })
            })
        })

        $(document).on('input', '#user_username', function () {
            const currentUsername = $(this).val()
            $("#user_password").val(currentUsername)
        })

        const generateUsernameFromName = (name) => {
            return (name || '')
                .toString()
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '.')
                .replace(/\.{2,}/g, '.')
                .replace(/^\.+|\.+$/g, '');
        }

        $(document).on('input', '#user_name', function () {
            if (dataUser) {
                return
            }

            const slug = generateUsernameFromName($(this).val())
            $("#user_username").val(slug).trigger('input')
        })

        // reset password dari modal
        $(document).on('click', '#btn-reset-password', function () {
            const username = $("#form-creadit").attr("data-username")
            if (!username) return

            const url = resetPasswordUserRoute.replace('__USERNAME__', encodeURIComponent(username))
            const displayUsername = username

            Swal.fire({
                title: 'Reset Password?',
                text: 'Password karyawan akan direset menjadi username (' + displayUsername + ').',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, reset',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (!result.isConfirmed) return

                ajaxPost({
                    url: url,
                    formData: {},
                    toast: false,
                    successCallback: function (response) {
                        Swal.fire('Berhasil', response.message || 'Password berhasil direset.', 'success')
                        table.ajax.reload(null, false)
                    },
                    errorCallback: function (response) {
                        let message = 'Password gagal direset.'
                        if (response?.responseJSON?.message) {
                            message = response.responseJSON.message
                        }
                        Swal.fire('Gagal', message, 'error')
                    }
                })
            })
        })

        // hapus
        $(document).on("click", ".btn-delete", function() {
            const username = $(this).data("username")
            if (!username) return

            Swal.fire({
                title: "Hapus Karyawan",
                text: "Apakah Anda yakin ingin menghapus karyawan ini?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, hapus",
                cancelButtonText: "Batal"
            }).then((result) => {
                if (!result.isConfirmed) return

                loadingScreen()

                const url = destroyUserRoute.replace('__USERNAME__', encodeURIComponent(username))

                ajaxPost({
                    url: url,
                    formData: {
                        _method: "DELETE"
                    },
                    successCallback: function(response) {
                        if (response.success) {
                            Swal.fire("Berhasil", response.message || "Data karyawan berhasil dihapus.", "success")
                            table.ajax.reload()
                        }
                    },
                    errorCallback: function(response) {
                        Swal.fire("Gagal", response.message || "Terjadi kesalahan saat menghapus data karyawan.", "error")
                    }
                })
            })
        })
    })
</script>
@endif
@endpush
