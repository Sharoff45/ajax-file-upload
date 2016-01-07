<?php
namespace Sharoff\Component;

Class FileUpload {

    static protected $tmp_path;
    static protected $result_path = '/result/';
    static protected $files       = null;


    static function setTmpPath($path) {
        self::$tmp_path = rtrim($path, '/') . '/file-upload/';
    }

    static protected function serverGet($key, $default = null) {
        return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
    }

    static protected function requestGet($key, $default = null) {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
    }

    static protected function fileGet($key) {
        return isset($_FILES[$key]) ? $_FILES[$key] : false;
    }

    static protected function getResultPath() {
        $dir = self::$tmp_path . self::$result_path;
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    static function checkUpload() {
        if ('POST' !== self::serverGet('REQUEST_METHOD')) {
            return false;
        }
        if ('meta' == self::requestGet('type')) {
            return self::getMetaData();
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

    static function getMetaData() {
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

    static function getFile($name) {
        if (is_null(self::$files)) {
            self::getAllFiles();
        }
        return isset(self::$files[$name]) ? self::$files[$name] : false;
    }

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
                throw new Exception('Ошибка при формировании файла');
            }
        }

        return true;
    }

    static protected function savePartition($file, $current_index, $session_id, $upload_id) {
        $path = self::makePath($session_id, $upload_id, $current_index);
        copy($file, $path);
        file_put_contents($path . '_complete', ' ');
    }

    static protected function makePath($session_id, $upload_id, $current_index = null) {
        $path = self::$tmp_path . '/' . md5($session_id) . '/' . md5($upload_id);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        return is_null($current_index) ? $path : $path . '/' . $current_index . '.tmp';
    }


}
