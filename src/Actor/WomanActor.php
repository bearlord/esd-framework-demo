<?php


namespace App\Actor;


use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorMessage;

class WomanActor extends Actor
{

    protected $data;

    /**
     * @inheritDoc
     */
    public function initData($data)
    {
        $this->data = $data;
    }

    /**
     * @inheritDoc
     */
    protected function handleMessage(ActorMessage $message)
    {
        printf("Woman message: ");
        var_dump($message);
        var_dump($message->getData());


    }
}