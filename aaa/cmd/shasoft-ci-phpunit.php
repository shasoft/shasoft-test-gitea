<?php

// Параметры
$params = $runManager->params();

// Путь до пакета
$pathPackage = $params['path'];

// Установить зависимости
$runManager->composerInstall($pathPackage);

// Сгенерировать XML со всеми тестами пакета
$filepathXml = $runManager->getTempFilename('xml');
$runManager->exec(
    $runManager->fileArg($runManager->config('php')) .
        ' vendor\bin\phpunit --list-tests-xml ' .
        $runManager->fileArg($filepathXml),
    $pathPackage
);


// Параметры Php Unit
if (empty($params['test']) || !file_exists($params['test'])) {
    // Определить файл автозагрузки
    $bootstrap = null;
} else {
    // Загрузим файл конфигурации
    $xmlPhpUnit = simplexml_load_file($params['test']);
    // Определить файл автозагрузки
    $bootstrap = strval($xmlPhpUnit->attributes()['bootstrap']);
}
if (empty($bootstrap)) {
    // Если не указан файл в настройках, то подгрузим 
    $bootstrap = $pathPackage . '/vendor/autoload.php';
} else {
    $bootstrap = str_replace(['/', '\\'], '/', $bootstrap);
    if (str_starts_with($bootstrap, '.')) {
        $bootstrap = substr($bootstrap, 1);
    }
    if (str_starts_with($bootstrap, '/')) {
        $bootstrap = substr($bootstrap, 1);
    }
    $bootstrap = $pathPackage . '/' . $bootstrap;
}
$bootstrap = $runManager->normalize($bootstrap);

// Подключить файл автозагрузки классов
require $bootstrap;
// Определить список тестов
$methodName = 'settingUpTheEnvironment';
$testsForClassSetting = [];
if (file_exists($filepathXml)) {
    $xmlTest = simplexml_load_file($filepathXml);
    foreach ($xmlTest->children() as $testCaseClass) {
        $classname = strval($testCaseClass->attributes()['name']);
        $tests = [];
        foreach ($testCaseClass->children() as $testCaseMethod) {
            $name = strval($testCaseMethod->attributes()['name']);
            $group = strval($testCaseMethod->attributes()['groups']);
            $tests[$name] = $group;
        }
        $refClass = new \ReflectionClass($classname);
        $classSetting = '';
        if ($refClass->hasMethod($methodName)) {
            $refMethod = $refClass->getMethod($methodName);
            if (!empty($refMethod)) {
                $classSetting = $refMethod->getDeclaringClass()->getName();
            }
        }
        if (!array_key_exists($classSetting, $testsForClassSetting)) {
            $testsForClassSetting[$classSetting] = [];
        }
        $testsForClassSetting[$classSetting][$classname] = $tests;
    }
}
// Фильтры
$filterTestsPhpUnit = $params['filter'];
if (!empty($filterTestsPhpUnit)) {
    $testsForClassSetting = array_map(function (array $tests) use ($filterTestsPhpUnit) {
        return array_filter(
            $tests,
            function (string $classname) use ($filterTestsPhpUnit) {
                return array_key_exists($classname, $filterTestsPhpUnit);
            },
            ARRAY_FILTER_USE_KEY
        );
    }, $testsForClassSetting);
}
// Нашли классы?
if (!empty($testsForClassSetting)) {
    $runManager->setCurrentDir($pathPackage, $savePath);
    foreach ($testsForClassSetting as $classSetting => $testsForClassname) {
        if (!empty($testsForClassname)) {

            // Сгенерировать имя для обмена
            $argsFile = $runManager->getTempFilename('ser');
            // Вызвать скрипт настройки среды окружения
            $hasEnvironment = !empty($classSetting);
            if ($hasEnvironment) {
                // Сгенерировать настройку среды окружения
                $commandsFile = $runManager->addGenerateCommandsFile(
                    $runManager->normalize(__DIR__ . '/shasoft-ci-phpunit-setenv.php'),
                    [
                        'bootstrap' => $bootstrap,
                        'method' => $classSetting . '::' . $methodName,
                        'argsFile' => $argsFile
                    ]
                );
                $runManager->addCallCommand($commandsFile);
                $runManager->echoToFile($params['name'] . '[%errorlevel%] ^<fg=cyan^>setenv^</^> ' . $classSetting . '::' . $methodName, $params['report']);
            }
            // Сгенерировать запуск файлов
            $commandsFile = $runManager->addGenerateCommandsFile(
                $runManager->normalize(__DIR__ . '/shasoft-ci-phpunit-run.php'),
                [
                    'bootstrap' => $bootstrap,
                    'testsForClassname' => $testsForClassname,
                    'argsFile' => $argsFile,
                    'report' => $params['report'],
                    'name' => $params['name'],
                    //'path'=>$pathPackage
                ]
            );
            $runManager->addCallCommand($commandsFile);
        }
    }
    // Вернуть обратно
    $runManager->setCurrentDir($savePath);
}
//s_dd($runManager, $testsForClassSetting);
