<?php

// Получить параметры
$params = $runManager->params();

// Подключить файл
require $params['bootstrap'];
// Список доп. параметров
if (file_exists($params['argsFile'])) {
    $argsExt  = unserialize(file_get_contents($params['argsFile']));
} else {
    $argsExt  = [];
}
// Скорректировать параметр --bootstrap с абсолютного на относительный путь
foreach ($argsExt as $i => $argExt) {
    if (trim($argExt) == '--bootstrap') {
        $bootstrap = $argsExt[$i + 1];
        if (file_exists($bootstrap)) {
            $bootstrap = $runManager->normalize($bootstrap);
            $pathPackage = dirname($bootstrap);
            while (true) {
                $composerFile = $runManager->normalize($pathPackage . '/composer.json');
                if (file_exists($composerFile)) {
                    $argsExt[$i + 1] = '"' . substr($bootstrap, strlen($pathPackage) + 1) . '"';
                    break;
                } else {
                    $pathPackage = dirname($pathPackage);
                    if ($pathPackage == dirname($pathPackage)) {
                        break;
                    }
                }
            }
        }
    }
}
//
$testsForClassname = $params['testsForClassname'];
//
foreach ($testsForClassname as $classname => $tests) {
    $refClass = new \ReflectionClass($classname);

    $args = $runManager->args();
    if (array_key_exists('e2e', $args)) {
        $e2e = ($args['e2e'] === true);
    } else {
        $params0 = $runManager->params('0');
        if ($params0['mode'] != 'publish') {
            $e2e = false;
        } else {
            $e2e = true;
        }
    }
    if ($e2e) {
        $pathTest = $runManager->normalize(dirname($refClass->getFileName()));
        $offset = strlen($pathTest);
        //
        $attributes = $refClass->getAttributes();
        foreach ($attributes as $attribute) {
            $classnameTestRunner = $attribute->getName();
            if (str_ends_with($classnameTestRunner, 'TestRunner')) {
                $runner = new $classnameTestRunner(...$attribute->getArguments());
                $errorLevelVar = $runner->run($runManager);
                //
                $refRunner = new \ReflectionClass($runner);
                if ($refRunner->hasProperty('path')) {
                    $refPath = $refRunner->getProperty('path');
                    $refPath->setAccessible(true);
                    $pathTestForRunner = $runManager->normalize($refPath->getValue($runner));
                    $path = substr($pathTestForRunner, $offset + 1);
                } else {
                    $path = '_';
                }
                // Записать результат
                $runManager->echoToFile($params['name'] . '[%' . $errorLevelVar . '%]^<fg=cyan^>    e2e^</^> ' . $classname . ':' . $path, $params['report']);
            }
        }
    }

    $runManager->addPhpCommand('vendor/bin/phpunit', array_merge(
        $argsExt,
        [
            $runManager->fileArg($refClass->getFileName())
        ]
    ));
    $runManager->echoToFile($params['name'] . '[%errorlevel%]^<fg=cyan^>phpunit^</^> ' . $classname, $params['report']);
    //$runManager->setCurrentDir($savePath);
}
