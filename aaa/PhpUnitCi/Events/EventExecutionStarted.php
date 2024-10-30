<?php

namespace Shasoft\Ci\Events;

use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;


final class EventExecutionStarted implements ExecutionStartedSubscriber
{
    public function notify(ExecutionStarted $event): void
    {
        echo "@\t" . __METHOD__ . "\t" . $event->asString() . PHP_EOL;
        echo $event->testSuite()->name() . PHP_EOL;
        foreach ($event->testSuite()->tests() as $test) {
            echo "\t" . $test->className() . PHP_EOL;
        }
        //require_once __DIR__ . '/extFunction.php';
    }
}
