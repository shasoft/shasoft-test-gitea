<?php

// https://getcomposer.org/doc/04-schema.md

use Shasoft\Ci\CiBootstrap;
use Shasoft\Console\Console;

require __DIR__ . '/../vendor/autoload.php';

// Получить параметры
$params = $runManager->params();
// Режим работы
$mode = $params['mode'];

// Читать файл 
if (file_exists($params['filepathReport'])) {
    $lines = array_filter(
        array_map(
            function (string $line) {
                return trim($line);
            },
            explode(
                PHP_EOL,
                file_get_contents(
                    $params['filepathReport'] //$argv[1]
                )
            )
        ),
        function (string $line) {
            return !empty($line);
        }
    );
} else {
    $lines = [];
}
// Режим
Console::writeLn(PHP_EOL . str_repeat('*', 80));
Console::writeLn("<title>Режим работы</> [<success>" . $mode . "</>]");
//
$packages = [];
foreach ($params['names'] as $name) {
    $packages[$name] = [
        'name' => $name,
        'rc' => 0,
        'tests' => []
    ];
}
$rcAll = 0;
foreach ($lines as $line) {
    $pos1 = strpos($line, '[');
    $pos2 = strpos($line, ']', $pos1);
    $name = substr($line, 0, $pos1);
    $rc = intval(substr($line, $pos1 + 1, $pos2 - $pos1 - 1));
    $rcAll += $rc;
    //$test = str_replace('\\', '/', substr($line, $pos2 + 1));
    $test = substr($line, $pos2 + 1);
    //
    $packages[$name]['rc'] = $packages[$name]['rc'] + $rc;
    $packages[$name]['tests'][$test] = $rc;
}
//
Console::writeLn('');
// Вывести результаты
$tab = '    ';
Console::writeLn(CiBootstrap::getStatus($rcAll) . ' <title>' . $params['root']['name'] . '</>');
foreach ($packages as $name => $package) {
    $status = CiBootstrap::getStatus($package['rc']);
    if (empty($package['tests'])) {
        $status = '<warning>Ok</>';
    }
    Console::writeLn($tab . $status . ' <desc>' . $name . '</>');
    foreach ($package['tests'] as $test => $rc) {
        //if ($package['rc'] != 0 || $mode == 'test') 
        {
            Console::writeLn($tab . $tab . CiBootstrap::getStatus($rc) . ' ' . $test);
        }
    }
}
// Если это режим публикации
if ($mode == 'publish' && $rcAll == 0) {
    $commandsFile = $runManager->addGenerateCommandsFile(
        $runManager->normalize(__DIR__ . '/shasoft-ci-publish.php'),
        [
            'root' => $params['root'],
            'packages' => $packages,
        ]
    );
    $runManager->addCallCommand($commandsFile);
}
