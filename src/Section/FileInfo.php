<?php
namespace Sharoff\Component;

Class FileInfo {

    protected $path;
    protected $name;
    protected $size = false;
    protected $type;
    protected $modified;

    function __construct($path, $name = null, $type = null, $modified = null) {
        $this->path     = $path;
        $this->name     = $name;
        $this->type     = $type;
        $this->modified = $modified;
    }

    function toArray() {
        return [
            'path'     => $this->getPath(),
            'name'     => $this->getName(),
            'size'     => $this->getSize(),
            'type'     => $this->getType(),
            'modified' => $this->getModified(),
        ];
    }

    function getPath() {
        return $this->path;
    }

    function getSize() {
        if (false === $this->size) {
            $this->size = filesize($this->getPath());
        }
        return $this->size;
    }

    function getName() {
        return $this->name;
    }

    function getType() {
        return $this->type;
    }

    function getModified() {
        return $this->modified;
    }

}