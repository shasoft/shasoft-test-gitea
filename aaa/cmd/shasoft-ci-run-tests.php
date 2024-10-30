<?php

// https://getcomposer.org/doc/04-schema.md

use Shasoft\Ci\CiBootstrap;
use Shasoft\Console\Console;
use Shasoft\Ci\GitHubRepository;

require __DIR__ . '/../vendor/autoload.php';

// Получить параметры
$params = $runManager->params();
// Режим работы
$mode = $params['mode'];

if ($mode != 'list') {
    //
    $filepathReport = $runManager->getTempFilename('txt');
    $runManager->addDeleteFile($filepathReport);
    // Все пакеты
    $names = [];
    // Выполнить все тесты
    foreach ($params['paths'] as $path) {
        // Создать репозиторий
        $repository = new GitHubRepository($path);
        // Добавить имя в список
        $names[] = $repository->fullName();
        // Добавить вызов тестов
        $repository->findTests($runManager, $filepathReport);
    }
    // Добавить обработку результатов
    $commandsFile = $runManager->addGenerateCommandsFile(
        $runManager->normalize(__DIR__ . '/shasoft-ci-result.php'),
        [
            'mode' => $mode,
            'root' => $params['root'],
            'names' => $names,
            'filepathReport' => $filepathReport,
        ]
    );
    $runManager->addCallCommand($commandsFile);
}
