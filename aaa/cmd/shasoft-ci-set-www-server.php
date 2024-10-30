<?php

// Получить параметры
$params = $runManager->params();

// Путь
$pathWwwServer = $runManager->normalize($params['path']);
// Домены
$domains = $params['domains'];

$envs = getenv();
//*******************************************************************************************************************************
// Проверим: Open Server Panel запущен?
if (array_key_exists('HOME', $envs)) {
    // Установить директории на домены
    $hasRestart = false;
    foreach ($domains as $domain) {
        $pathDomain = $runManager->normalize($envs['HOME'] . '/domains/' . $domain);
        $hasRestart |= $runManager->createLink($pathWwwServer, $pathDomain);
    }
    // Рестарт сервера
    if ($hasRestart) {
        $init = parse_ini_file($runManager->normalize($envs['HOME'] . '/userdata/init.ini'));
        $runManager->addCommand('CURL http://' . $init['login'] . ':' . $init['pass'] . '@localhost:' . $init['port'] . '/restart');
    }
}
