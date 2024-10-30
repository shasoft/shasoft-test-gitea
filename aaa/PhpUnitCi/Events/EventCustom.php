<?php

namespace Shasoft\Ci\Events;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;


final class EventCustom implements PreparationStartedSubscriber
{
    public function notify(PreparationStarted $event): void
    {
        echo "@\t" . __METHOD__ . "\t" . $event->asString() . PHP_EOL;
        //s_trace();
        //echo $event->testSuite()->name() . PHP_EOL;
        s_dump(function_exists('MyDump'));
        require_once __DIR__ . '/extFunction.php';
    }
}
