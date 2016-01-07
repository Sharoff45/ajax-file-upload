<?php

$tmp_path = __DIR__ . '/storage/';
Sharoff\Component\FileUpload::setTmpPath($tmp_path);
Sharoff\Component\FileUpload::checkUpload();
