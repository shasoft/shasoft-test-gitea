<?php

namespace Shasoft\Ci;

use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\Extension;
use Shasoft\Ci\Events\EventExecutionStarted;
use Shasoft\Ci\Events\EventCustom;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\Runner\Extension\ParameterCollection;
//use Shasoft\Ci\Events\EventExtensionBootstrapped;


final class PhpUnitExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters
    ): void {
        if ($configuration->noOutput()) {
            return;
        }

        $message = 'the-default-message';

        if ($parameters->has('message')) {
            $message = $parameters->get('message');
        }
        //s_dump($configuration->bootstrap());
        $facade->registerSubscriber(new EventExecutionStarted());
        $facade->registerSubscriber(new EventCustom());
        //$facade->registerTracer(new ExampleTracer);
    }
}
