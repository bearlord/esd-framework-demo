<?php

namespace App\Plugins\Demo;

use ESD\Core\Context\Context;
use ESD\Core\Server\Server;

class DemoPlugin extends \ESD\Core\Plugin\AbstractPlugin
{

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return "DemoPlugin";
    }

    /**
     * @inheritDoc
     */
    public function beforeServerStart(Context $context)
    {
        printf("DemoPlugin Server start\n");
        for ($i = 0; $i < 1; $i++) {
            Server::$instance->addProcess('demo-'. $i, DemoProcess::class, 'HelpGroup');
        }

    }

    /**
     * @inheritDoc
     */
    public function beforeProcessStart(Context $context)
    {
        $this->ready();
    }
}