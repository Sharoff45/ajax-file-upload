<?php
namespace Sharoff\Component;

Class FileInfo {

    /**
     * Полный путь к файлу
     * @var string
     */
    protected $path;
    /**
     * Оригинальное название файла
     * @var string
     */
    protected $name;
    /**
     * Размер файла в байтах
     * @var int
     */
    protected $size = false;
    /**
     * Тип файла
     * @var string
     */
    protected $type;
    /**
     * Дата изменения файла
     * @var string
     */
    protected $modified;

    /**
     * Инициализация объекта
     *
     * @param      $path
     * @param null $name
     * @param null $type
     * @param null $modified
     */
    function __construct($path, $name = null, $type = null, $modified = null) {
        $this->path     = $path;
        $this->name     = $name;
        $this->type     = $type;
        $this->modified = $modified;
    }

    /**
     * Получить все данные по файлу
     * @return array
     */
    function toArray() {
        return [
            'path'     => $this->getPath(),
            'name'     => $this->getName(),
            'size'     => $this->getSize(),
            'type'     => $this->getType(),
            'modified' => $this->getModified(),
        ];
    }

    /**
     * Получить полный путь к файлу
     * @return string
     */
    function getPath() {
        return $this->path;
    }

    /**
     * Получить размер файла в байтах
     * @return int
     */
    function getSize() {
        if (false === $this->size) {
            $this->size = filesize($this->getPath());
        }
        return $this->size;
    }

    /**
     * Получить оригинальное название файла
     * @return null|string
     */
    function getName() {
        return $this->name;
    }

    /**
     * Получить mime-тип файла
     * @return null|string
     */
    function getType() {
        return $this->type;
    }

    /**
     * Получить дату последнего изменения файла
     * @return null|string
     */
    function getModified() {
        return $this->modified;
    }

}