<?php

abstract class TestRunner
{
    // Конструктор aaa!!!
    public function __construct(protected string $path) {}
    // Запустить тесты
    abstract public function run(\RunManager $runManager): string;
}
