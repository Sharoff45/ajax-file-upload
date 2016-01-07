# ajax-file-upload
Загрузка файлов по частям с помощью JQUERY + AJAX + PHP

## Установка

~~~
composer require sharoff/ajax-file-upload
~~~

Скопировать из вендора js-файл: src/public/js/file_upload.js

### Подключить скрипты:
~~~
<script src="http://code.jquery.com/jquery-1.11.3.min.js"></script>
<script src="js/file_upload.js"></script>
~~~

### Инициализация элементов
Для input тега необходимо добавить CSS класс: "js-ajax-upload"
------
Указание URL загрузки (JS):
~~~
FILE_UPLOAD.upload_url = 'новый урл';
~~~

Указание количество одновременных передаваемых частей на сервер (JS):
~~~
FILE_UPLOAD.queue_limit = 'кол-во частей';
~~~

Указание размера одного пакета в байтах (JS):
~~~
FILE_UPLOAD.partitionSize = 'размер в байтах';
~~~

Указание максимальное количество секунд ожидания ответа от сервера (JS):
~~~
FILE_UPLOAD.timeout = 'время в секундах';
~~~

### События:
При каждой загрузке одной части пакета у input`а указывается data аттрибут "data-percent" и вызывается событие "ajax-upload-percent"

~~~
$('.js-input-file-1').on('ajax-upload-percent', function(){
  console.log($(this).attr('data-percent'));
});
~~~

После полной загрузки файла вызывается событие "ajax-upload-success", в которое так же передаются все данные пришедшие с сервера
~~~
$('.js-input-file-1').on('ajax-upload-success', function(event, data){
  console.info('Загрузка завершена');
  console.log(data);
});
~~~

### Реинициализация (при ajax загрузке элементов, JS)
~~~
FILE_UPLOAD.init();
~~~
