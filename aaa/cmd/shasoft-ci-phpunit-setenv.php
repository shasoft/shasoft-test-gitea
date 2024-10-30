<?php

// https://getcomposer.org/doc/04-schema.md

// Получить параметры
$params = $runManager->params();

// Подключить файл
require $params['bootstrap'];

// Вызвать метод
$argsExt = call_user_func(
    $params['method'],
    $runManager,
    $params['bootstrap']
);
if (empty($argsExt)) {
    $argsExt = [];
}
// Сохранить доп. параметры командной строки
file_put_contents($params['argsFile'], serialize($argsExt));
