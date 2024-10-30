<?php

// Подключить классы Composer

use Shasoft\Console\Process;

// Если файла автозагрузки классов не существует
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    // то установить
    $pathSave = getcwd();
    chdir(dirname(dirname($autoload)));
    system('composer install', $rc);
    chdir($pathSave);
}

// Подключить классы
require_once __DIR__ . '/../vendor/autoload.php';

$CI_COMMANDS_PATH = getenv('CI_COMMANDS_PATH');

// Имя файла скрипта
$args = $argv;
unset($args[0]);
// Номер
$num = $args[1];
unset($args[1]);
// Режим работы
if (isset($args[2])) {
    $mode = $args[2];
    unset($args[2]);
} else {
    $mode = '';
}
if (!in_array($mode, [
    'test',
    'list',
    'check',
    'publish'
])) {
    $mode = 'test';
}
// Параметры
$options = [];
$args = array_filter($args, function (string $arg) use (&$options) {
    $command_prefix = '--ci-';
    $command_len = strlen($command_prefix);
    if (str_starts_with($arg, $command_prefix)) {
        $pos = strpos($arg, '=');
        if ($pos === false) {
            $name = substr($arg, $command_len);
            $value = null;
        } else {
            $name = substr($arg, $command_len, $pos - $command_len);
            $value = substr($arg, $pos + 1);
        }
        if (array_key_exists($name, $options)) {
            $options[$name][] = $value;
        } else {
            $options[$name] = [$value];
        }
        return false;
    }
    return true;
});
$args = array_values($args);
// Сохранить файл параметров
if (!file_exists($CI_COMMANDS_PATH)) {
    mkdir($CI_COMMANDS_PATH, 0777, true);
}
file_put_contents(
    $CI_COMMANDS_PATH . '/' . $num . '.json',
    json_encode(
        [
            'mode'  => $mode,
            'args' => $options
        ],
        JSON_PRETTY_PRINT
    )
);
file_put_contents(
    $CI_COMMANDS_PATH . '/args.json',
    json_encode(
        $args,
        JSON_PRETTY_PRINT
    )
);
// Значения
file_put_contents(
    $CI_COMMANDS_PATH . '/values.json',
    json_encode(
        [
            'php' => Process::phpPath()
        ],
        JSON_PRETTY_PRINT
    )
);
