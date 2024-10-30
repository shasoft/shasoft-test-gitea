<?php

namespace Shasoft\Ci;
// 1
use CiBootstrap;
use Shasoft\Ci\ChangeLog;
use Shasoft\Ci\GitHubClient;
use Shasoft\Console\Console;
use Shasoft\Console\Process;
use Shasoft\Filesystem\File;
use Shasoft\Filesystem\Path;
use Shasoft\Filesystem\Filesystem;
use Shasoft\Composer\Composer;
use Shasoft\Ci\Exceptions\ExceptionVersionExists;
use Shasoft\Ci\Exceptions\ExceptionNotComposerFile;
use SplFileInfo;

// Клиент для работы с репозиторием github 
class GitHubRepository
{
    // Директория
    protected string $path;
    // Файл composer текущего пакета
    protected string $filepathComposer;
    // Параметры
    protected array $composer;
    // Имя пакета
    protected string $name;
    // Автор
    protected string $vendor;
    // Конструктор
    public function __construct(string $path)
    {
        // Директория пакета
        $this->path = Path::normalize($path);
        // Файл composer текущего проекта
        $this->filepathComposer =  $this->path . '/' . Composer::FILEINFO;
        if (!file_exists($this->filepathComposer)) {
            throw new ExceptionNotComposerFile($this->filepathComposer);
        }
        // Данные
        $this->composer = File::load($this->filepathComposer);
        // Определить компанию и имя пакета
        $tmp = explode('/', $this->composer['name']);
        // Имя пакета
        $this->name = $tmp[1];
        // Автор
        $this->vendor = $tmp[0];
        $filepathConfig = __DIR__ . '/../config/config.php';
        if (file_exists($filepathConfig)) {
            //
            $config = require $filepathConfig;
            // Заменить в адресе электронной почты имя пакета
            $composerAuthors = array_map(function (array $author) {
                $author['email'] = str_replace('{name}', str_replace(['/'], '_', $this->composer['name']), $author['email']);
                return $author;
            }, [$config['authors'] ?? []]);
            // Установить имя пользователя
            $authors = $this->composer['authors'] ?? [];
            $author0 = $authors[0] ?? ['name' => '', 'email' => ''];
            $composerAuthor0 = $composerAuthors[0] ?? [];
            if ($author0['name'] != $composerAuthor0['name'] || $author0['email'] != $composerAuthor0['email']) {
                echo "Изменился " . Composer::FILEINFO . PHP_EOL;
                //
                if (!array_key_exists('authors', $this->composer)) {
                    $this->composer['authors'] = [];
                }
                if (empty($this->composer['authors'])) {
                    $this->composer['authors'][] = $composerAuthor0;
                } else {
                    $this->composer['authors'][0] = $composerAuthor0;
                }
                // ПереСохранить
                File::save($this->filepathComposer, $this->composer);
            }
        }
    }
    // Путь внутри пакета
    public function path(?string $path = null): string
    {
        $ret = $this->path;
        if (!is_null($path)) {
            $ret .= '/' . $path;
        }
        return $ret;
    }
    // Полное имя пакета
    public function fullName(): string
    {
        return $this->composer['name'];
    }
    // Имя пакета
    public function name(): string
    {
        return $this->name;
    }
    // Автор
    public function vendor(): string
    {
        return $this->vendor;
    }
    // Описание
    public function description(): string
    {
        return $this->composer['description'] ?? null;
    }
    // Домашняя страница
    public function homepage(): ?string
    {
        return $this->composer['homepage'] ?? null;
    }
    // Версия
    public function version(bool $reSave = false): string
    {
        // Локальные версии
        $changeLog = new ChangeLog($this->path('CHANGELOG.md'));
        // Определить последнюю версию
        return $changeLog->getLastVersion($reSave);
    }
    // Выполнить команду  консоли
    protected function execCmd(string $cmd): int
    {
        Console::writeLn('<title>exec</>: [' . $cmd . ']');
        return Process::exec($cmd, $this->path, true, false);
    }
    // Выполнить проверку полноты описания
    public function getWarning(): array
    {
        $ret = [];
        //
        $files = ['readme.md', 'CHANGELOG.md', '.gitignore'];
        foreach ($files as $filename) {
            if (!file_exists($this->path($filename))) {
                $ret[] = 'Отсутствует файл <file>' . $filename . '</>';
            }
        }
        if (!file_exists($this->path('.gitattributes'))) {
            // Проверим наличие bat файлов
            $hasBat = false;
            Filesystem::items($this->path, function (\SplFileInfo $spl) use (&$hasBat) {
                if ($spl->isDir()) {
                    if ($hasBat) return false;
                    switch ($spl->getFilename()) {
                        case 'vendor': {
                                return false;
                            }
                            break;
                        case '.git': {
                                return false;
                            }
                            break;
                    }
                } else if ($spl->getExtension() == 'bat') {
                    $hasBat = true;
                }
            });
            if ($hasBat) {
                $ret[] = 'Отсутствует файл <file>.gitattributes</> но присутствуют файлы <info>bat</>';
            }
        }
        //
        return $ret;
    }
    // Сохранить пакет на github
    public function save(GitHubClient $client, bool $errExistsVersion): bool
    {
        // Определить последнюю версию
        $version = $this->version(true);
        // Тег с версией
        $tagVersion = 'v' . $version;
        // Получить список репозиториев
        $repositories = $client->getRepositories($this->vendor());
        // Репозиторий существует?
        if (array_key_exists($this->name(), $repositories)) {
            //-- Да, репозиторий СУЩЕСТВУЕТ
            // Список версий
            $tags = [];
            foreach ($client->api('repo')->tags($this->vendor(), $this->name()) as $tag) {
                $tags[$tag['name']] = $tag;
            }
            // Проверить наличие версии
            if (array_key_exists($tagVersion, $tags)) {
                if ($errExistsVersion) {
                    throw new ExceptionVersionExists($version, $this->composer['name']);
                }
                return false;
            }
            // Получить репозиторий
            $repo = $repositories[$this->name()];
        } else {
            //-- Нет, репозиторий НЕ СУЩЕСТВУЕТ
            // Создать репозиторий
            $repo = $client->api('repo')->create($this->name(), $this->description(), $this->homepage(), true);
        }
        // А может что-то изменилось?
        if (
            $this->description() != $repo['description'] ||
            $this->homepage() != $repo['homepage']
        ) {
            // Обновить
            $repo = $client->api('repo')->update(
                $this->vendor(),
                $this->name(),
                array(
                    'description' => $this->description(),
                    'homepage' => $this->homepage()
                )
            );
        }
        //
        $url = $repo['clone_url'];
        $default_branch = $repo['default_branch'];
        $desc = $argv[1] ?? $tagVersion;
        s_dump($this->path('.git'), file_exists($this->path('.git')), $default_branch, $url);
        if (file_exists($this->path('.git'))) {
            $this->execCmd('git remote add origin ' . $url); //!!!
            $this->execCmd('git add .');
            $this->execCmd('git commit -m "' . $desc . '"');
            $this->execCmd('git branch -M ' . $default_branch);
            $this->execCmd('git push -u origin ' . $default_branch);
            // Если тега не существует
            $filepathTag = $this->path('.git/refs/tags/' . $tagVersion);
            $hasTag = file_exists($filepathTag);
            if (!$hasTag) {
                // то добавить
                $this->execCmd('git tag ' . $tagVersion);
            }
            $this->execCmd('git push origin ' . $tagVersion);
        } else {
            $this->execCmd('git init');
            //
            $author = $this->composer['authors'][0];
            // Имя
            $name = $author['name'] ?? null;
            if (!is_null($name)) {
                $this->execCmd('git config user.name "' . $name . '"');
            }
            // Адрес электронной почты
            $email = $author['email'] ?? null;
            if (!is_null($email)) {
                $this->execCmd('git config user.email ' . $email);
            }
            $this->execCmd('git add .');
            $this->execCmd('git commit -m "' . $desc . '"');
            $this->execCmd('git branch -M ' . $default_branch);
            $this->execCmd('git remote add origin ' . $url);
            $this->execCmd('git push -u origin ' . $default_branch);
            $this->execCmd('git tag ' . $tagVersion);
            $this->execCmd('git push origin ' . $tagVersion); //!!!
        }
        // Создать Release (если он отсутствует)
        $releases = $client->getReleases($this->vendor(), $this->name());
        if (!array_key_exists($tagVersion, $releases)) {
            $release = $client->api('repo')->releases()->create($this->vendor(), $this->name(), array('tag_name' => $tagVersion));
        }
        //
        return true;
    }
    // Искать тесты
    public function findTests(\RunManager $runManager, string $filepathReport): void
    {
        $params = $runManager->params();
        // Список фильтров по классам
        $filterTestsPhpUnit = $params['args']['phpunit-filter'] ?? [];
        if (!empty($filterTestsPhpUnit)) {
            $filterTestsPhpUnit = array_flip($filterTestsPhpUnit);
        }
        // Есть в зависимостях PhpUnit?
        $hasPhpUnit = false;
        if (array_key_exists('require-dev', $this->composer)) {
            if (array_key_exists('phpunit/phpunit', $this->composer['require-dev'])) {
                $hasPhpUnit = true;
            }
        }
        if ($hasPhpUnit) {
            $pathForCheck = [
                'phpunit.xml',
                'phpunit.dist.xml',
                'phpunit.xml.dist'
            ];
            $filepathTestFind = null;
            foreach ($pathForCheck as $testPath) {
                $filepathTest = $this->path($testPath);
                if (file_exists($filepathTest)) {
                    $filepathTestFind = $filepathTest;
                }
            }
            //
            if (!empty($filepathTestFind)) {
                $commandsFile = $runManager->addGenerateCommandsFile(
                    $runManager->normalize(__DIR__ . '/../bin/shasoft-ci-phpunit.php'),
                    [
                        'test' => $filepathTestFind,
                        'report' => $filepathReport,
                        'name' => $this->fullName(),
                        'path' => $this->path(),
                        'filter' => $filterTestsPhpUnit
                    ]
                );
                $runManager->addCallCommand($commandsFile);
            }
        }
        // Тесты которые запускаются через командные файлы
        $pathForCheck = [
            'tests/run.bat',
            'tests/run.php',
        ];
        foreach ($pathForCheck as $testPath) {
            $filepathTest = $this->path($testPath);
            if (file_exists($filepathTest)) {
                // Если НЕ указана фильтрация по имени теста для PhpUnit
                if (empty($filterTestsPhpUnit)) {
                    if (basename($filepathTest) == 'run.php') {
                        $filepathBat = $runManager->addGenerateCommandsFile($filepathTest);
                        $runManager->addCallCommand($filepathBat);
                        $runManager->echoToFile($this->fullName() . '[%errorlevel%]^<fg=cyan^>php^</^> ' . Path::normalize($testPath, false), $filepathReport);
                    } else {
                        $runManager->addCallCommand($filepathTest, $runManager->argv());
                        $runManager->echoToFile($this->fullName() . '[%errorlevel%]^<fg=cyan^>bat^</^> ' . Path::normalize($testPath, false), $filepathReport);
                    }
                }
            }
        }
    }
    // 
}
