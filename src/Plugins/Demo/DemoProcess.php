<?php

namespace App\Plugins\Demo;

use ESD\Core\Message\Message;
use ESD\Core\Server\Process\Process;
use ESD\Core\Server\Server;
use ESD\Coroutine\Coroutine;
use Swlib\Saber;
use Swoole\Coroutine\Channel;

class DemoProcess extends \ESD\Core\Server\Process\Process
{

    /**
     * @inheritDoc
     */
    public function init()
    {
        // TODO: Implement init() method.
    }

    /**
     * @inheritDoc
     */
    public function onProcessStart()
    {
        printf("DemoProcess start\n");
        $url = "https://www.baidu.com/s?wd=php";
        $data = [
            'wd' => 'php'
        ];
        $channel = new Channel(1);
        do {
            goWithContext(function () use ($channel, $url, $data) {
                $saber = Saber::create();
                $responeData = $saber->get($url, $data)->getBody();
                $channel->push($responeData);
            });

            $responeData = $channel->pop();
            Server::$instance->getLog()->debug(
                sprintf("content: %s\n", substr($responeData, 100, 200))
                );
            Coroutine::sleep(1);
        } while(true);

    }

    /**
     * @inheritDoc
     */
    public function onProcessStop()
    {
        // TODO: Implement onProcessStop() method.
    }

    /**
     * @inheritDoc
     */
    public function onPipeMessage(Message $message, Process $fromProcess)
    {
        // TODO: Implement onPipeMessage() method.
    }
}