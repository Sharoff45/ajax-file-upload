<?php
namespace Sharoff\Component;

Class FileUpload {

    /**
     * Временная папка для работы скрипта
     * @var
     */
    static protected $tmp_path;
    /**
     * Путь к загружженным файлам (объединенным) отностительно папки tmp
     * @var string
     */
    static protected $result_path = '/result/';
    /**
     * Список загруженных файлов
     * @var null|array
     */
    static protected $files = null;


    /**
     * Указание временной папки для загрузки файлов
     *
     * @param $path
     */
    static function setTmpPath($path) {
        self::$tmp_path = rtrim($path, '/') . '/file-upload/';
    }

    /**
     * Получение данных из суперглобального массива $_SERVER
     *
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    static protected function serverGet($key, $default = null) {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    /**
     * Получение данных из суперглобального массива $_REQUEST
     *
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    static protected function requestGet($key, $default = null) {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }

    /**
     * Получение данных из суперглобального массива $_FILES
     *
     * @param $key
     *
     * @return bool
     */
    static protected function fileGet($key) {
        return isset($_FILES[$key]) ? $_FILES[$key] : false;
    }

    /**
     * Получить путь к папке с загруженными файлами
     * @return string
     */
    static protected function getResultPath() {
        $dir = self::$tmp_path . self::$result_path;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Проверка пост запроса на отправку файла и загрузка если он есть
     * @return bool
     */
    static function checkUpload() {
        if ('POST' !== self::serverGet('REQUEST_METHOD')) {
            return false;
        }
        if ('meta' == self::requestGet('type')) {
            return self::saveMetaData();
        }

        $cnt_all       = (int)self::requestGet('cnt');
        $current_index = (int)self::requestGet('current_index');
        $session_id    = self::requestGet('session_id');
        $upload_id     = self::requestGet('upload_id');

        $file_data = self::fileGet('file_data');
        if ($file_data && 0 == $file_data['error']) {
            $file = $file_data['tmp_name'];
            self::savePartition($file, $current_index, $session_id, $upload_id);
            self::checkReady($session_id, $upload_id, $cnt_all);
            header('Content-type: application/json; charset=utf-8');
            echo json_encode([
                'result'         => 'partition',
                'session_id'     => $session_id,
                'upload_id'      => $upload_id,
                'cnt_partitions' => $cnt_all
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        return true;
    }

    /**
     * Созранить META-данные файла
     *
     * @return bool
     */
    static function saveMetaData() {
        $session_id = self::requestGet('session_id');
        $upload_id  = self::requestGet('upload_id');
        if (!$session_id || !$upload_id) {
            return false;
        }

        $file_data = self::requestGet('file_info');
        $name      = isset($file_data['name']) ? $file_data['name'] : null;
        $type      = isset($file_data['type']) ? $file_data['type'] : null;
        $modified  = isset($file_data['last_modified_date']) ? $file_data['last_modified_date'] : null;
        if (!is_null($modified) && $time = strtotime($modified)) {
            $modified = date('Y-m-d H:i:s', $time);
        } else {
            $modified = null;
        }
        $data        = compact('modified', 'name', 'type');
        $result_file = self::getResultPath() . md5($session_id . $upload_id) . '.meta';
        file_put_contents($result_file, '<?php ' . PHP_EOL . 'return ' . var_export($data, true) . ';');
        return true;
    }

    /**
     * Получить список всех загруженных файлов
     * (Проверка POST данных, которые подставляются скриптом при инициализации)
     * @return array|null
     */
    static function getAllFiles() {
        if (!is_null(self::$files)) {
            return self::$files;
        }
        self::$files = [];
        $files       = self::requestGet('ajax_uploaded_file');
        foreach ($files as $input_name => $files_list) {
            if (isset($files_list['upload_id'])) {
                foreach ($files_list['upload_id'] as $file_id => $upload_id) {
                    $session_id = isset($files_list['session_id']) && isset($files_list['session_id'][$file_id]) ? $files_list['session_id'][$file_id] : null;

                    if (!is_null($session_id)) {
                        $result_file = self::getResultPath() . md5($session_id . $upload_id);
                        if (file_exists($result_file)) {
                            $real_path = realpath($result_file);
                            if (file_exists($real_path . '.meta')) {
                                $file_data = include $real_path . '.meta';
                            } else {
                                $file_data = [];
                            }
                            $name                       = isset($file_data['name']) ? $file_data['name'] : null;
                            $type                       = isset($file_data['type']) ? $file_data['type'] : null;
                            $modified                   = isset($file_data['modified']) ? $file_data['modified'] : null;
                            self::$files[$input_name][] = new FileInfo($real_path, $name, $type, $modified);
                        }
                    }
                }
            }
        }
        return self::$files;
    }

    /**
     * Получить список файлов по имени input[type=file]
     * Каждый элемент результирующего массива будет в обертке FileInfo
     *
     * @param $name
     *
     * @return bool|array
     */
    static function getFile($name) {
        if (is_null(self::$files)) {
            self::getAllFiles();
        }
        return isset(self::$files[$name]) ? self::$files[$name] : false;
    }

    /**
     * Проверка полной загрузки файла
     *
     * @param $session_id
     * @param $upload_id
     * @param $cnt_all
     *
     * @throws \Exception
     */
    static protected function checkReady($session_id, $upload_id, $cnt_all) {
        $path = self::makePath($session_id, $upload_id);
        $cnt  = count(glob($path . '/*.*'));
        if ($cnt_all * 2 == $cnt) {
            self::saveFile($session_id, $upload_id, $cnt_all);

            header('Content-type: text/json; charset=utf-8');
            echo json_encode([
                'result'         => 'success',
                'session_id'     => $session_id,
                'upload_id'      => $upload_id,
                'cnt_partitions' => $cnt_all
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /***
     * Сохраняем файл в результирующей папке
     *
     * @param $session_id
     * @param $upload_id
     * @param $cnt_all
     *
     * @return bool
     * @throws \Exception
     */
    static protected function saveFile($session_id, $upload_id, $cnt_all) {
        $path        = self::makePath($session_id, $upload_id);
        $result_file = self::getResultPath() . md5($session_id . $upload_id);
        if (!is_dir(dirname($result_file))) {
            mkdir(dirname($result_file), 0777, true);
        }

        if (file_exists($result_file)) {
            unlink($result_file);
        }
        for ($i = 0; $i < $cnt_all; $i++) {
            $file = $path . '/' . $i . '.tmp';
            if (file_exists($file)) {
                $content = file_get_contents($file);
                file_put_contents($result_file, $content, FILE_APPEND);
                unlink($file);
                unlink($file . '_complete');
            } else {
                echo $file;
                throw new \Exception('Ошибка при формировании файла');
            }
        }

        return true;
    }

    /**
     * Сохраняем часть файла во временной папке
     *
     * @param $file
     * @param $current_index
     * @param $session_id
     * @param $upload_id
     */
    static protected function savePartition($file, $current_index, $session_id, $upload_id) {
        $path = self::makePath($session_id, $upload_id, $current_index);
        copy($file, $path);
        file_put_contents($path . '_complete', ' ');
    }

    /**
     * Составляем путь ко временной части файла
     *
     * @param      $session_id
     * @param      $upload_id
     * @param null $current_index
     *
     * @return string
     */
    static protected function makePath($session_id, $upload_id, $current_index = null) {
        $path = self::$tmp_path . '/' . md5($session_id) . '/' . md5($upload_id);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        return is_null($current_index) ? $path : $path . '/' . $current_index . '.tmp';
    }


    /**
     * Очистка временной дирректории
     *
     * ВНИМАНИЕ АККУРАТНО! НЕОБХОДИМО УБЕДИТЬСЯ В ПРАВИЛЬНОСТИ УКАЗАНИЯ ПАПКИ TMP & RESULT
     */
    static function clearTmpDir() {
        $dir = self::$tmp_path;
        self::removeDir($dir);
    }

    /**
     * Функция очистки папки (рекурсивно)
     *
     * @param $dir
     */
    static protected function removeDir($dir) {
        $list = scandir($dir);
        foreach ($list as $item) {
            if ('.' == $item || '..' == $item) {
                continue;
            }
            $file = rtrim($dir, '/') . '/' . $item;
            if (is_dir($file)) {
                self::removeDir($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

}
