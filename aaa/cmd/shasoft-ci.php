<?php

// https://getcomposer.org/doc/04-schema.md

use Shasoft\Ci\CiBootstrap;
use Shasoft\Console\Console;
use Shasoft\Ci\GitHubRepository;
use Shasoft\Ci\Exceptions\ExceptionBase;

require $runManager->composerInstall(__DIR__ . '/../');

// Режим работы
$mode = $runManager->params()['mode'];
Console::writeLn("<title>Режим работы</> [<success>" . $mode . "</>]");
try {
    // Основной репозиторий
    $rootRepository = new GitHubRepository(getcwd());
    // *****************************************************************************************************
    // Все репозитории
    $allRepositories = [];
    CiBootstrap::findRequires($runManager, $rootRepository->path(),  $allRepositories);
    $allRepositories[$rootRepository->fullName()] = 1;
    $allRepositories = array_keys($allRepositories);
    sort($allRepositories);
    // *****************************************************************************************************
    // Выполнить проверки на предупреждения всех репозиториев
    $warnings = [];
    foreach ($allRepositories as $name) {
        $messages = (new GitHubRepository($runManager->pathRepositories($name)))->getWarning();
        if (!empty($messages)) {
            $warnings[$name] = $messages;
        }
    }
    if (!empty($warnings)) {
        Console::writeLn("<title>Выявлены предупреждения</>");
        foreach ($warnings as $name => $messages) {
            Console::writeLn("\t <warning>{$name}</>");
            foreach ($messages as $message) {
                Console::writeLn("\t\t" . $message);
            }
        }
        exit(13);
    }
    // Репозитории с тестами для проверки
    $testsRepositories = [$rootRepository];
    if ($mode != 'test') {
        // Установить основной пакет
        $runManager->composerInstall($rootRepository->path());
        // Получить список дочерних пакетов
        foreach ($allRepositories as $name) {
            $pathPackage = $rootRepository->path('vendor/' . $name);
            if (file_exists($pathPackage)) {
                $testsRepositories[] = new GitHubRepository($rootRepository->path('vendor/' . $name));
            }
        }
    }

    // Выполнить тесты
    $commandsFile = $runManager->addGenerateCommandsFile(
        $runManager->normalize(__DIR__ . '/shasoft-ci-run-tests.php'),
        [
            'mode' => $mode,
            'root' => [
                'name' => $rootRepository->fullName(),
                'path' => $rootRepository->path()
            ],
            'paths' => array_map(function (GitHubRepository $package) {
                return $package->path();
            }, $testsRepositories)
        ]
    );
    $runManager->addCallCommand($commandsFile);
} catch (ExceptionBase $e) {
    Console::writeLn('<error>Exception</>[' . $e->getCode() . '] ' . $e->getMessage());
    exit($e->getCode());
}
