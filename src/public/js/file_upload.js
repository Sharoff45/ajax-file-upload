var FILE_UPLOAD = {

    /**
     * Размер разбития файлов на части
     */
    partitionSize: 1024 * 1024 * 2,
    /**
     * Информация оо загружаемых частях
     */
    uploads: {},
    /**
     * Количество загруженных части
     */
    upload_cnt: 0,
    /*
     * Идентификатор сессии
     */
    session_id: window.location.href + '::' + Math.random(),
    /**
     * Возможные статусы части
     */
    states: {
        initial: 0,
        ready: 1,
        uploaded: 2
    },
    /**
     * УРЛ для временной загрузки
     */
    upload_url: '/',

    /**
     * Очередь на загрузку
     */
    queue: [],
    /**
     * Количество отправок в очереди
     */
    queued: 0,
    /**
     * Количество одновременных загрузок
     */
    queue_limit: 3,

    /**
     * Время ожидания сервера
     */
    timeout: 30,

    /**
     * Инициализация компонента
     */
    init: function () {
        /**
         * Инициализация элементов для загрузки файлов
         */
        $('.js-ajax-upload:not(.js-init-ajax-upload)')
            .on('change', function () {
                // указываем идентификатор загрузки
                $(this).attr('data-upload-id', FILE_UPLOAD.upload_cnt);
                FILE_UPLOAD.handleUpload($(this));
                FILE_UPLOAD.upload_cnt++;
            })
            .on('ajax-upload-success', function (e, data) {
                var $this = $(this),
                    $form = $this.closest('form');
                $form.prepend('<input type="hidden" name="ajax_uploaded_file[' + $this.attr('name') + '][session_id][]" value="' + data.session_id + '" />');
                $form.prepend('<input type="hidden" name="ajax_uploaded_file[' + $this.attr('name') + '][upload_id][]" value="' + data.upload_id + '" />');
                $this.val('');
            })
            .addClass('js-init-ajax-upload');

        if ($('.js-init-ajax-upload').length > 0) {
            setInterval(function () {
                // проверка в очереди на загрузку
                FILE_UPLOAD.checkQueue();
            }, 50);
        }
    },

    checkQueue: function () {
        if (FILE_UPLOAD.queue.length) {
            for (var i = 0; i < FILE_UPLOAD.queue_limit - FILE_UPLOAD.queued; i++) {
                var data = FILE_UPLOAD.queue.shift();
                if ('undefined' == typeof(data)) {
                    return;
                }
                data.beforeSend = function () {
                    FILE_UPLOAD.queued++;
                };
                data.complete = function (e) {
                    if (200 != e.status) {
                        FILE_UPLOAD.queue.push(this);
                    }
                    FILE_UPLOAD.queued--;
                };
                $.ajax(data);
            }
        }
    }
    ,

    canPartition: function () {
        return (window.File && window.Blob) ? true : false;
    },

    handleUpload: function ($element) {
        if (FILE_UPLOAD.canPartition()) {
            // браузер поддерживает разделение файлов на части
            FILE_UPLOAD.getStartPartitions($element);
        } else {
            alert('К сожалению ваш браузер не поддерживает загрузку файлов с помощью AJAX');
        }
    },

    getStartPartitions: function ($element) {
        var input = $element.get(0);
        if (!FILE_UPLOAD.canPartition()) {
            return false;
        }

        var l = input.files.length;
        if (!l) {
            return false;
        }

        for (var i = 0; i < l; i++) {
            var file = input.files[i];
            FILE_UPLOAD.sendMetaData(file, $element, i);
            FILE_UPLOAD.startPartitions(file, $element, i);
        }
    },

    sendMetaData: function (file, $element, file_index) {
        var file_info = {
            last_modified_date: file.lastModifiedDate.toUTCString(),
            name: file.name,
            size: file.size,
            type: file.type
        };
        $.post(FILE_UPLOAD.upload_url, {
            type: 'meta',
            upload_id: $element.attr('data-upload-id') + '-' + file_index,
            session_id: FILE_UPLOAD.session_id,
            file_info: file_info
        }, function (data) {

        });
    },

    startPartitions: function (file, $element, file_index) {
        var cnt_partitions = Math.ceil(file.size / FILE_UPLOAD.partitionSize),
            startingByte,
            endingByte,
            upload_id = $element.attr('data-upload-id') + '-' + file_index,
            blob;

        if ('undefined' == typeof(FILE_UPLOAD.uploads[upload_id])) {
            FILE_UPLOAD.uploads[upload_id] = {
                state: FILE_UPLOAD.states.initial,
                element: $element
            };
        }

        for (var partition_index = 0; partition_index < cnt_partitions; partition_index++) {
            startingByte = partition_index * FILE_UPLOAD.partitionSize;
            endingByte = (partition_index + 1) * FILE_UPLOAD.partitionSize;
            if (file.webkitSlice) {
                blob = file.webkitSlice(startingByte, endingByte);
            } else if (file.mozSlice) {
                blob = file.mozSlice(startingByte, endingByte);
            } else {
                blob = file.slice(startingByte, endingByte);
            }

            FILE_UPLOAD.startUploadPartitions(upload_id, partition_index, blob, cnt_partitions);
        }
    },

    startUploadPartitions: function (upload_id, partition_index, file_data, cnt_partitions) {
        var upload_data = FILE_UPLOAD.uploads[upload_id],
            $element = upload_data.element,
            upload_url = $element.attr('data-upload-url') ? $element.attr('data-upload-url') : FILE_UPLOAD.upload_url,
            form_data = new FormData();

        form_data.append('file_data', file_data);
        form_data.append('session_id', FILE_UPLOAD.session_id);
        form_data.append('upload_id', upload_id);
        form_data.append('current_index', partition_index);
        form_data.append('cnt', cnt_partitions);
        if (!file_data) {
            return false;
        }
        FILE_UPLOAD.queue.push({
            type: 'POST',
            url: upload_url,
            data: form_data,
            dataType: "json",
            processData: false,
            contentType: false,
            success: function (data) {
                var upload_id = data.upload_id,
                    cnt_partitions = data.cnt_partitions,
                    percent = Math.ceil((cnt_partitions - FILE_UPLOAD.queue.length) / cnt_partitions * 100);
                $element
                    .attr('data-percent', percent)
                    .trigger('ajax-upload-percent');
                if ('success' == data.result) {
                    FILE_UPLOAD.uploads[upload_id].state = FILE_UPLOAD.states.uploaded;
                    $element.trigger('ajax-upload-success', data);
                }
            },
            timeout: FILE_UPLOAD.timeout * 1000
        });
    }

};

$(document).ready(function () {
    $('body').trigger('file-upload');
});

$('body').on('file-upload', function () {
    FILE_UPLOAD.init();
    $('.js-ajax-upload').trigger('change');
});
