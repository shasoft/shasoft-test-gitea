<?php

namespace Shasoft\Ci;
// 11
use Github\Client;
use Github\AuthMethod;
use Github\HttpClient\Builder;

// Клиент для работы с GitHub
class GitHubClient extends Client
{
    // Конструктор
    public function __construct(Builder $httpClientBuilder = null, $apiVersion = null, $enterpriseUrl = null)
    {
        // Вызвать конструктор родителя
        parent::__construct($httpClientBuilder, $apiVersion, $enterpriseUrl);
        // Файл с токеном доступа https://github.com/settings/tokens
        $filepathConfig = __DIR__ . '/../config/config.php';
        if (file_exists($filepathConfig)) {
            //
            $config = require $filepathConfig;
            // Создать клиента для доступа
            $this->authenticate($config['vendor']['shasoft']['github'], null, AuthMethod::ACCESS_TOKEN);
        }
    }
    // Получить список репозиториев
    public function getRepositories(string $vendor): array
    {
        //var_dump($vendor);
        // Получить список репозиториев
        $repositories = $this->api('user')->repositories($vendor);
        $ret = [];
        foreach ($repositories as $repository) {
            $ret[$repository['name']] = $repository;
        }
        return $ret;
    }
    // Получить список релизов
    public function getReleases(string $vendor, string $namePackage): array
    {
        // Получить список репозиториев
        $releases = $this->api('repo')->releases()->all($vendor, $namePackage);
        $ret = [];
        foreach ($releases as $release) {
            $ret[$release['tag_name']] = $release;
        }
        return $ret;
    }
}
