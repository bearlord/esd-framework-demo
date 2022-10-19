<?php


namespace App\Actor;


use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorMessage;
use ESD\Server\Coroutine\Server;

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

    public function getData()
    {
        return $this->data;
    }

    public function getMoney()
    {
        return $this->data['money'];
    }

    public function outMoney($value)
    {
        $this->data['money'] = $this->data['money'] - $value;
        return $value;
    }

    public function inMoney($value)
    {
        $this->data['money'] = $this->data['money'] + $value;
        return $value;
    }

    /**
     * @inheritDoc
     */
    protected function handleMessage(ActorMessage $message)
    {
        Server::$instance->getLog()->critical(sprintf("to: %s, form: %s, message: %s",
            $message->getTo(),
            $message->getFrom(),
            $message->getData()
        ));

        if ($message->getTo() == 'lucy') {
            Actor::getProxy($message->getFrom())->sendMessage(new ActorMessage( '好呀~~', $message->getMsgId(), "lucy", "lilei"));
        }

        if ($message->getTo() == 'lily') {
            Actor::getProxy($message->getFrom())->sendMessage(new ActorMessage( '不去！！', $message->getMsgId(), "lily", "lilei"));
        }

        return 1;

    }
}