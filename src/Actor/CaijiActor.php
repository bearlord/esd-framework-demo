<?php

namespace App\Actor;

use ESD\Core\Server\Server;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorMessage;

class CaijiActor extends Actor
{

    protected $data;

    public function initData($data)
    {
        // TODO: Implement initData() method.
    }

    protected function handleMessage(ActorMessage $message)
    {
        // TODO: Implement handleMessage() method.
    }

    public function doCaiji($url)
    {

        $this->data['content']['url']['length'] = 0;
        $this->data['content']['url']['content'] = "";
        goWithContext(function () use ($url){
            $content = file_get_contents($url);
//            $this->data['content']['url']['length'] = strlen($content);
            Server::$instance->getLog()->critical(sprintf("采集器 %s 正在采集 %s, 长度: %d", $this->name, $url, strlen($content)));
            sleep(0.02);
        });
    }


}