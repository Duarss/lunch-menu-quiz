window.swalToast = window.swalToast || Swal.mixin({
    toast: true,
    position: 'top-right',
    iconColor: 'white',
    customClass: {
        popup: 'colored-toast'
    },
    showConfirmButton: false,
    timer: 1500,
    width: '20%',
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
})
window.swalErrorToast = window.swalErrorToast || Swal.mixin({
    customClass: {
        confirmButton: 'btn btn-secondary',
    },
    imageUrl: $('meta[name="asset-url"]').attr('content') + 'assets/img/warning.jpg',
    imageHeight: '25em',
    reverseButtons: true,
    buttonsStyling: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
})

window.swalCancelToast = window.swalCancelToast || Swal.mixin({
    toast: true,
    icon: 'info',
    title: 'Aksi Dibatalkan',
    position: 'top-right',
    iconColor: 'white',
    customClass: {
        popup: 'colored-toast'
    },
    showConfirmButton: false,
    timer: 1500,
    width: '20%',
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
})
window.swalConfirm = window.swalConfirm || Swal.mixin({
    customClass: {
        confirmButton: 'btn btn-success',
        cancelButton: 'btn btn-danger'
    },
    text: "Apakah anda yakin?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, setujui!',
    cancelButtonText: 'Tidak, batalkan!',
    reverseButtons: true,
    buttonsStyling: false
})
window.swalDeleteConfirm = window.swalDeleteConfirm || Swal.mixin({
    customClass: {
        confirmButton: 'btn btn-danger',
        cancelButton: 'btn btn-success'
    },
    text: "Harap berhati - hati. Setelah dihapus, data tidak dapat dipulihkan kembali.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus saja!',
    cancelButtonText: 'Tidak, batalkan!',
    reverseButtons: true,
    buttonsStyling: false
})
function loadingScreen() {
    Swal.fire({
        html: "<div style='height: 150px'><i class='bx bx-loader bx-spin' style='color: #fff;font-size:5rem'></i></div>",
        background: 'rgba(255, 255, 255, 0)',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false
    });
}
/**
*
* @param {} options required options is url
* available options is successCallback, name or message
* @returns
*/
function deleteHandler(options = {}) {
    let title = 'Apakah anda yakin'
    if (options.name) title += ' ingin menghapus ' + options.name

    swalDeleteConfirm.fire({
        title: title + ' ?'
    }).then((result) => {
        if (result.isConfirmed) {
            ajaxPost({
                url: options.url,
                formData: { _method: 'delete' },
                successCallback: function (response) {
                    typeof options.successCallback === "function" && options.successCallback(response)
                }
            })
        } else swalCancelToast.fire()
    })
}

function ajaxPost(options = {}) {
    let data = null

    if (typeof options.formData === 'string') {
        data = options.formData;
    } else if (options.formData instanceof FormData) {
        data = options.formData
    } else if (typeof options.formData === 'object') {
        data = new FormData()
        for (var key in options.formData) {
            data.append(key, options.formData[key]);
        }
    } else {
        data = options.formData
    }
    if (options.toast === false) options.toast = false
    else if (!options.toast) options.toast = true

    if (options.loading === false) options.loading = false
    else if (!options.loading) options.loading = true

    // Use passed contentType and processData, or default to false
    let contentType = options.contentType !== undefined ? options.contentType : false;
    let processData = options.processData !== undefined ? options.processData : false;

    return $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: options.url,
        type: 'post',
        data: data,
        cache: false,
        processData: processData,
        contentType: contentType,
        accept: 'application/json',
        beforeSend: function (response) {
            options.loading && loadingScreen()
        },
        success: function (response) {
            options.modal && $(options.modal).modal('hide');
            options.toast && swalToast.fire({ title: response.message, icon: 'success' })
            typeof options.successCallback === "function" && options.successCallback(response)
        },
        error: function (response) {
            if (response.status == 422) {
                if (response.responseJSON.errors) {
                    Object.keys(response.responseJSON.errors).forEach(name => {
                        const tmpName = name.includes('.') ? name.replace('.', '[') + ']' : name
                        const elementInput = $("[name^='" + tmpName + "']")
                        elementInput.addClass('is-invalid')

                        const elementErrorMessage = $('[id^=' + (tmpName.replace('[', '\\[')).replace(']', '\\]') + '');
                        elementErrorMessage.removeClass('d-none')
                        elementErrorMessage.html(response.responseJSON.errors[name].join('<br>'))
                    });
                }
            }
            options.toast && swalErrorToast.fire(ajaxErrorResponse(response))
            typeof options.errorCallback === "function" && options.errorCallback(response)
        },
        complete: function (response) {
            options.loading && !options.toast && Swal.close()
        },
    });
}
function ajaxGet(options = {}) {
    return $.ajax({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        url: options.url,
        type: 'get',
        cache: false,
        beforeSend: function (response) {
            options.loading && loadingScreen()
        },
        success: function (response) {
            options.modal && $(options.modal).modal('hide');

            if (typeof options.successCallback === "function") options.successCallback(response)
        },
        error: function (response) {
            if (typeof options.errorCallback === "function") options.errorCallback(response)
        },
        complete: function (response) {
            options.loading && !options.toast && Swal.close()
        },
    });
}
function ajaxErrorResponse(request) {
    let msg = {}
    if (request.status == 422) {
        msg.title = "Kesalahan validasi!"
        let validation_error_msg = "";
        if (request.responseJSON.errors) {
            validation_error_msg = "<ul style='text-align: left' class='mx-5'>"
            Object.keys(request.responseJSON.errors).forEach(key => {
                validation_error_msg += "<li>" + request.responseJSON.errors[key] + "</li>"
            });
            validation_error_msg += '</ul>'
        } else {
            validation_error_msg = request.responseJSON.message
        }
        msg.html = validation_error_msg
    } else {
        msg = {
            title: 'Terjadi kesalahan pada server',
            html: 'Harap muat ulang halaman dan coba lagi. Jika masalah terus terjadi harap hubungi penyedia layanan anda!',
            imageUrl: $('meta[name="asset-url"]').attr('content') + 'assets/img/danger.jpg',
        }
    }
    return msg
}
function dataTableInit(parameters) {
    if (parameters.buttons && parameters.buttons.length) parameters.btnTools = true

    if (!parameters.hasOwnProperty('rowIndex')) parameters.rowIndex = true
    // Tambahan
    if (!parameters.hasOwnProperty('btnDetails')) parameters.btnDetails = true
    //
    if (!parameters.hasOwnProperty('btnActions')) parameters.btnActions = true

    let buttons = []
    let dom = "<'row'<'col'l><'col'f>>rt<'row'<'col'i><'col mt-1'p>>"
    if (parameters.btnTools) {
        dom = "<'row'<'col'l><'col'f><'col-auto'B>>rt<'row'<'col'i><'col mt-1'p>>"
        buttons = [];
        if (parameters.btnExcel) {
            buttons.push({
                extend: 'excelHtml5',
                title: parameters.title,
                className: 'btn-sm btn-outline-secondary',
                text: "<i class='bx bx-spreadsheet' style='color: #1d6f42'></i>",
                titleAttr: 'Export Excel',
                exportOptions: {
                    columns: ':not(.no-export)'
                }
            })
        }
        if (parameters.btnPdf) {
            buttons.push({
                extend: 'pdfHtml5',
                title: parameters.title,
                className: 'btn-sm btn-outline-secondary',
                text: "<i class='bx bxs-file-pdf' style='color: #f40f02'></i>",
                titleAttr: 'Export Pdf',
                exportOptions: {
                    columns: ':not(.no-export)',
                },
                pageSize: 'LEGAL',
                orientation: 'landscape',
                customize: function (doc) {
                    /* solution 1
                      doc.content[1].margin = [ 100, 0, 100, 0 ] //left, top, right, bottom
                    */

                    /* solution 2
                      var colCount = new Array();
                      $('#'+id).find('tbody tr:first-child td').each(function(){
                          if($(this).attr('colspan')){
                              for(var i=1;i<=$(this).attr('colspan');$i++){
                                  colCount.push('*');
                              }
                          }else{ colCount.push('*'); }
                      });
                      doc.content[1].table.widths = colCount;
                    */

                    /* solution 3
                      doc.content[1].table.widths = Array(doc.content[1].table.body[0].length + 1).join('*').split('');
                    */
                    var colCount = new Array();
                    $('#' + id).find('tbody tr:first-child td').each(function () {
                        if ($(this).attr('colspan')) {
                            for (var i = 1; i <= $(this).attr('colspan'); $i++) {
                                colCount.push('*');
                            }
                        } else { colCount.push('*'); }
                    });
                    doc.content[1].table.widths = colCount;
                }
            })
        }
    }

    let columns = []
    if (parameters.rowIndex) columns = columns.concat([{ data: 'DT_RowIndex', orderable: false, searchable: false, width: '1%', title: 'No', className: 'no-export' }])
    parameters.columns.forEach(e => { columns = columns.concat(e) })
    // Tambahan
    if (parameters.btnDetails) columns = columns.concat([{ data: 'details', orderable: false, searchable: false, width: '5%', title: 'Details', className: 'no-export' }])
    //
    if (parameters.btnActions) columns = columns.concat([{ data: 'action', orderable: false, searchable: false, width: '5%', title: 'Aksi', className: 'no-export text-center' }])

    if (parameters.hasOwnProperty('ajax')) {
        parameters.ajax.type = 'post'
        parameters.ajax.headers = {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    }
    let initParams = {
        dom: dom,
        buttons: buttons,
        processing: true,
        serverSide: true,
        scrollCollapse: true,
        scrollY: '66vh',
        scrollX: '100%',
        pageLength: 100,
        order: [[0, 'asc']],
        lengthMenu: [[5, 10, 25, 50, 100, 150, 200, 500, 1000, -1], [5, 10, 25, 50, 100, 150, 200, 500, 1000, 'Semua']],
        language: dataTableTranslate(parameters.title),
        columns: columns,
        ajax: parameters.ajax
    }

    if (parameters) {
        Object.keys(parameters).forEach(key => {
            if (key == 'buttons') {
                initParams[key].unshift(parameters[key])
                initParams['dom'] = "<'row'<'col'l><'col'f><'col-auto'B>>rt<'row'<'col'i><'col mt-1'p>>"
            } else if (jQuery.inArray(key, ['ajax', 'columns']) === -1) {
                initParams[key] = parameters[key]
            }
        });
    }

    return $(parameters.selector).DataTable(initParams);
}
function dataTableTranslate(title = '') {
    return {
        "decimal": "",
        "emptyTable": "Data belum ada",
        "info": "Menampilkan _START_ hingga _END_ dari total _TOTAL_ data",
        "infoEmpty": "Data belum ada",
        "infoFiltered": "(disaring dari _MAX_ total data)",
        "infoPostFix": "",
        "thousands": ",",
        "lengthMenu": "Menampilkan _MENU_ data",
        "loadingRecords": "Sedang memuat...",
        "search": "Cari:",
        "zeroRecords": "Data tidak ditemukan",
        "paginate": {
            "first": "Pertama",
            "last": "Terakhir",
            "next": ">",
            "previous": "<"
        },
        "aria": {
            "sortAscending": ": activate to sort column ascending",
            "sortDescending": ": activate to sort column descending"
        }
    }
}
function dropzoneTranslate(attr = 'Berkas') {
    return {
        dictDefaultMessage: "Jatuhkan " + attr.toLowerCase() + " ke sini untuk mengunggah.",
        dictFallbackMessage: "Browser Anda tidak mendukung 'drag and drop' berkas.",
        dictFallbackText: "Silakan gunakan cara lama untuk mengunggah berkas.",
        dictFileTooBig: "Ukuran berkas terlalu besar ({{filesize}}MB). Ukuran maksimum: {{maxFilesize}}MB.",
        dictInvalidFileType: "Ekstensi berkas tidak sesuai.",
        dictResponseError: "Terjadi masalah pada sistem, silakan hubungi penyedia layanan Anda! Kode: {{statusCode}}.",
        dictCancelUpload: "Batal unggah",
        dictUploadCanceled: "Pengunggahan dibatalkan.",
        dictCancelUploadConfirmation: "Yakin ingin membatalkan unggahan ini?",
        dictRemoveFile: "Hapus " + attr.toLowerCase(),
        dictRemoveFileConfirmation: "Yakin ingin menghapus " + attr.toLowerCase() + " ini?",
        dictMaxFilesExceeded: "Total " + attr.toLowerCase + " yang diunggah sudah mencapai batas.",
    }
}
/**
*
* @param {} options required options is id, url, inputName, formId
* @returns
*/
function dropzoneInit(options = {}) {
    const parameters = options

    if (!parameters.translate) parameters.translate = dropzoneTranslate()
    if (!parameters.maxFilesize) parameters.maxFilesize = 2
    if (!parameters.maxFiles) parameters.maxFiles = 1
    if (!parameters.acceptedFiles) parameters.acceptedFiles = 'image/*'

    return new Dropzone(parameters.id, {
        dictDefaultMessage: parameters.translate.dictDefaultMessage,
        dictFallbackMessage: parameters.translate.dictFallbackMessage,
        dictFallbackText: parameters.translate.dictFallbackText,
        dictFileTooBig: parameters.translate.dictFileTooBig,
        dictInvalidFileType: parameters.translate.dictInvalidFileType,
        dictResponseError: parameters.translate.dictResponseError,
        dictCancelUpload: parameters.translate.dictCancelUpload,
        dictUploadCanceled: parameters.translate.dictUploadCanceled,
        dictCancelUploadConfirmation: parameters.translate.dictCancelUploadConfirmation,
        dictRemoveFile: parameters.translate.dictRemoveFile,
        dictRemoveFileConfirmation: parameters.translate.dictRemoveFileConfirmation,
        dictMaxFilesExceeded: parameters.translate.dictMaxFilesExceeded,
        url: parameters.url,
        maxFilesize: parameters.maxFilesize, // MB
        addRemoveLinks: true,
        maxFiles: parameters.maxFiles,
        acceptedFiles: parameters.acceptedFiles, // ".jpeg,.jpg,.png,.gif", Accepted File Formats for Uploads https://onlinecode.org/dropzone-allowed-file-extensions-tutorials-technology/
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        success: function (file, response) {
            $('#' + parameters.formId).append('<input type="hidden" name="' + parameters.inputName + '" value="' + response.name + '">')
            typeof parameters.successCallback == 'function' && parameters.successCallback(file, response)
        },
        removedfile: function (file) {
            file.previewElement.remove()
            $('#' + parameters.formId).find('input[name="' + parameters.inputName + '"][value="' + file.name + '"]').remove()

            ajaxPost({
                loading: false,
                url: parameters.url,
                formData: {
                    file: file.name,
                    isDelete: true
                }
            })

            typeof parameters.removedfileCallback == 'function' && parameters.removedfileCallback(file)

            let fileCount = $('input[name="' + parameters.inputName + '"]').map(function () { return $(this).val(); }).get().length;
            fileCount < this.options.maxFiles && $(".dz-hidden-input").prop("disabled", false)
        },
        maxfilesexceeded: function (file) {
            this.removeAllFiles();
            this.addFile(file);
        }
    });
}
// function dropzonePreview(dz, files, url, formSelector, inputName){
/**
*
* @param {} options required options is dz, formId, inputName, files
*/
function dropzonePreview(options = {}) {
    const parameters = options

    dropzoneClear({ dz: parameters.dz, formId: parameters.formId, inputName: parameters.inputName })
    if (parameters.files) {
        parameters.files.forEach(image => {
            console.log(image);
            let mockFile = { name: image.name, size: image.size };
            parameters.dz.displayExistingFile(mockFile, image.thumbnail);
            $(parameters.formId).append('<input type="hidden" name="' + parameters.inputName + '[]" value="' + mockFile.name + '">')
            var fileCount = $("input[name='" + parameters.inputName + "[]']").map(function () { return $(this).val(); }).get().length;
            if (fileCount >= parameters.dz.options.maxFiles) $(".dz-hidden-input").prop("disabled", true);
        });
    }
}

// function dropzoneClear(dz, formId, inputName){
function dropzoneClear(options = {}) {
    const parameters = options

    const id = parameters.dz.element.id
    $("#" + id + " .dz-started").remove()
    parameters.dz.element.classList.remove("dz-started");
    $("#" + id + " .dz-preview").remove()

    parameters.dz.removeAllFiles(true)
    $(parameters.formId).find("input[name='" + parameters.inputName + "[]']").remove()
    $(".dz-hidden-input").prop("disabled", false);
}

function clearForm(formselector) {
    $(formselector).find('input').each(function () {
        if ($(this).attr('type') !== 'hidden' && $(this).attr('type') !== 'checkbox' && $(this).attr('type') !== 'radio') {
            $(this).val('')
        }
    })
    $(formselector).find('textarea').each(function () {
        $(this).val('')
    })
    $(formselector).find('select').each(function () {
        $(this).val(null).trigger('change')
    })
    $(formselector).find('select2').each(function () {
        $(this).val([]).trigger('change')
    })
    $(formselector).find('input[type="radio"]').prop('checked', false);
    $(formselector).find('input[type="checkbox"]').prop('checked', false);
}

function select2Init(parameters) {
    // --- 1. Siapkan fungsi data khusus untuk AJAX ---
    const ajaxDataFn = parameters.data || function (params) {
        return { search: params.term ?? '' };
    };

    // Jangan biarkan parameters.data nongkrong di top-level,
    // karena Select2 akan mengira itu 'data adapter' dan
    // menganggap return-nya array lalu di-slice.
    if (parameters.hasOwnProperty('data')) {
        delete parameters.data;
    }

    // --- 2. Language default (biarkan seperti sebelumnya) ---
    if (!parameters.hasOwnProperty('language')) {
        parameters.language = {
            errorLoading: function () { return "Terjadi masalah pada sistem." },
            inputTooLong: function (n) { return "Hapus " + (n.input.length - n.maximum) + " huruf" },
            inputTooShort: function (n) { return "Masukkan " + (n.minimum - n.input.length) + " huruf lagi untuk mencari" },
            loadingMore: function () { return "Mengambil data ..." },
            maximumSelected: function (n) { return "Anda hanya dapat memilih " + n.maximum + " pilihan" },
            noResults: function () { return "Tidak ada data yang sesuai" },
            searching: function () { return "Mencari ..." },
            removeAllItems: function () { return "Hapus semua pilihan" }
        };
    }

    // --- 3. Config AJAX khusus Select2 ---
    parameters.ajax = {
        type: 'post',
        delay: 250,
        cache: true,
        dataType: 'json',
        data: ajaxDataFn, // <--- pakai ajaxDataFn di sini
        url: parameters.url,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        processResults: function (data) {
            // Pastikan controller select2.companies return format:
            // [ { id: 'CODE', text: 'Nama Perusahaan' }, ... ]
            return {
                results: data
            };
        }
    };

    // --- 4. Init Select2 ---
    return $(parameters.selector).select2(parameters);
}

function select2Val(select2Id, options) {
    /* still in concept
      if(options instanceof Array){
        const selected = [];
        options.forEach(e => {
          selected.push(e.id)
          $('#'+select2Id).append(new Option(optionText,optionId,true,true))
        });
        $('#'+select2Id).val(selected).trigger('change');
      }else{
        $('#'+select2Id).append(new Option(options.text,options.id,true,true)).trigger('change');
      }
    */

    $('#' + select2Id).append(new Option(options.text, options.id, true, true)).trigger('change');
}
function autoNumericInit(options) {
    const defaultPreset = {
        emptyInputBehavior: 'null',
        isCancellable: true,
        modifyValueOnWheel: false,
        value: 0
    }

    let preset = null
    if (options.isCurrency) {
        preset = {
            alwaysAllowDecimalCharacter: false,
            decimalPlaces: 0,
            decimalPlacesShownOnBlur: 0,
            decimalPlacesShownOnFocus: 0,
            decimalPlacesRawValue: null
        }
    } else if (options.isPercentage) {
        preset = {
            alwaysAllowDecimalCharacter: true,
            decimalPlaces: 2,
            decimalPlacesShownOnBlur: 2,
            decimalPlacesShownOnFocus: 2,
            decimalPlacesRawValue: 2,
            maximumValue: '99.99'
        }
    }
    if (options.multiple) return new AutoNumeric.multiple(options.selector, defaultPreset.value, Object.assign(defaultPreset, preset));
    else return new AutoNumeric(options.selector, Object.assign(defaultPreset, preset));

    /*
      return new AutoNumeric(selector, {
        alwaysAllowDecimalCharacter: false,
        emptyInputBehavior: 'null',
        isCancellable: true,
        modifyValueOnWheel: false,
        decimalPlaces: 0,
        decimalPlacesShownOnBlur: 0,
        decimalPlacesShownOnFocus: 0,
        decimalPlacesRawValue: null
      });
    */
}
function autoNumericVal(options) {
    AutoNumeric.getAutoNumericElement(options.selector).set(options.value)
}
function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}
function ucwords(string) {
    return (string + '').replace(/^([a-z])|\s+([a-z])/g, function ($1) {
        return $1.toUpperCase();
    });
}
function toLongDay(date) {
    let days = ['Sabtu', 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
    return days[date.getDay()]
}
function toLongMonth(date) {
    let months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    return months[date.getMonth()];
}
function toLongDateDayTime(datetime) {
    let days = ['Sabtu', 'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
    let months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
    let date = new Date(datetime)
    return days[date.getDay()] + ', ' + date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear() + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0')
}
function toLongDateTime(datetime) {
    let date = new Date(datetime)
    return toLongDate(datetime) + ' ' + String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0') + ':' + String(date.getSeconds()).padStart(2, "0")
}
function toLongDate(datetime) {
    let date = new Date(datetime)
    let day = String(date.getDate()).padStart(2, "0") // menambahkan leading zero
    return day + ' ' + toLongMonth(date) + ' ' + date.getFullYear()
}
function toLongDateDay(datetime) {
    let date = new Date(datetime)
    return toLongDay(date) + ', ' + toLongDate(datetime)
}
function formatNumber(value, thousands_separator = ',', decimals_separator = '.') {
    var num_parts = value.toString().split(".");
    num_parts[0] = num_parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousands_separator);
    return num_parts.join(decimals_separator);
}
function date(dateNow = new Date(), separator = "-") {
    return dateNow.getFullYear() + separator + String(dateNow.getMonth() + 1).padStart(2, '0') + separator + String(dateNow.getDate()).padStart(2, '0');
}
function wordwrap(value, $n_digits = 4, separator = '-', cut = true) {
    if (!value) { return value; }
    var regex = '.{1,' + $n_digits + '}(\\s|$)' + (cut ? '|.{' + $n_digits + '}|.+$' : '|\\S+?(\\s|$)');
    return value.match(RegExp(regex, 'g')).join(separator);
}
function setLocal(name, value) {
    if (local = getLocal(name)) {
        if (local != value) return localStorage.setItem(name, value)
    } else {
        return localStorage.setItem(name, value)
    }
}
function getLocal(name) {
    return localStorage.getItem(name)
}
function clearLocal(name) {
    return localStorage.removeItem(name)
}
