<?php

// https://getcomposer.org/doc/04-schema.md

use Shasoft\Ci\CiBootstrap;
use Shasoft\Ci\GitHubClient;
use Shasoft\Console\Console;
use Shasoft\Console\Process;
use Shasoft\Filesystem\File;
use Shasoft\Composer\Composer;
use Shasoft\Ci\PackagistClient;
use Shasoft\Ci\GitHubRepository;
use Shasoft\Filesystem\Filesystem;

// Подключить файл
require __DIR__ . '/../vendor/autoload.php';

// Получить параметры
$params = $runManager->params();

//s_dump($params);

$repositories = [];
foreach (array_keys($params['packages']) as $name) {
    $repositories[] = new GitHubRepository(
        $runManager->pathRepositories($name)
    );
}

// Список сообщений
$messages = [];
//
$clientPackagist = new PackagistClient();
$clientGithub = new GitHubClient();
// Версии пакетов
$versions = [];
// Сохранить
$error = false;
$index = 0;
foreach ($repositories as $repository) {
    // Сохранить
    $rcSave = $repository->save($clientGithub, false);
    // Если версия обновилась
    if ($rcSave) {
        $versions[$repository->fullName()] = $repository->version(false);
    }
    // Статус сохранения
    $saveStatus = $rcSave ? '<success>Обновлено</>' : '<info>Актуально</>';
    //
    $version = $repository->version();
    $rcVersion = $clientPackagist->hasVersion($repository->fullName(), $repository->version(false));
    //
    $versionColor = is_null($rcVersion) ? '<error>' : ($rcVersion === true ? '<success>' : '<fg=gray>');
    if (count($repositories) <= 1) {
        $num = '';
    } else {
        $index++;
        $num = sprintf("%2d", $index);
    }
    //
    $msgExt = '';
    if (is_null($rcVersion)) {
        $msgExt = ' <warning>Необходимо добавить</> пакет на <file>https://packagist.org</>';
        $error = true;
    }
    //
    if (empty($messages)) {
        $messages[] = '';
        $messages[] = '<title>Обновляем пакеты на github</>';
    }
    $messages[] =
        "\t" . $num . ' <desc>' . $repository->fullName() . '</> = ' . $versionColor . $repository->version() . '</> ' . $saveStatus . $msgExt;
}
// Если нет ошибок
if (!$error) {
    if (!empty($versions)) {
        //
        Console::writeLn('');
        Console::writeLn('<title>Ждём пока обновятся версии</> на <file>packagist.org</>...');
        // Ждать пока обновятся версии на packagist.org
        $endTime = time() + 60;
        while (!empty($versions) && time() < $endTime) {
            $versions = array_filter($versions, function (string $version, string $name) use ($clientPackagist) {
                $rc =  $clientPackagist->hasVersion($name, $version);
                return $rc !== true;
            }, ARRAY_FILTER_USE_BOTH);
            if (!empty($versions)) {
                sleep(3);
            }
        }
    }
    // Если все пакеты успешно обновились
    if (empty($versions)) {
        // Директория для скаченных тестов
        $pathPublishTest = $params['root']['path'] . '/vendor/~';
        Filesystem::rmdir($pathPublishTest);
        Filesystem::mkdir($pathPublishTest);
        //
        $vendorName = explode('/', $params['root']['name']);
        $vendorName = $vendorName[0];
        //
        $composer = [
            'name' => $vendorName . '/publish-tests',
            'require' => []
        ];
        foreach ($repositories as $repository) {
            $composer['require'][$repository->fullName()] = $repository->version();
        }
        File::save($pathPublishTest . '/' . Composer::FILEINFO, $composer);
        // Установить все пакеты
        $rc = Process::exec('composer install', $pathPublishTest);
        if ($rc == 0) {
            // Выполнить тесты по всем установленным пакетам
            $commandsFile = $runManager->addGenerateCommandsFile(
                $runManager->normalize(__DIR__ . '/shasoft-ci-run-tests.php'),
                [
                    'mode' => 'install+tests',
                    'root' => [
                        'name' => $params['root']['name'],
                        'path' => $pathPublishTest . '/vendor/' . $params['root']['name']
                    ],
                    'paths' => array_map(function (string $name) use ($pathPublishTest) {
                        return $pathPublishTest . '/vendor/' . $name;
                    }, array_keys($params['packages']))
                ]
            );
            $runManager->addCallCommand($commandsFile);
        }
    }
}
//
foreach ($messages as $message) {
    Console::writeLn($message);
}
