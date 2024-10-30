<?php

// Зарегистрировать функцию загрузки классов
spl_autoload_register(function ($classname) {
    $filepath = __DIR__ . '/../classes0/' . $classname . '.php';
    if (file_exists($filepath)) {
        require_once $filepath;
    }
});

new RunManager($argv);
