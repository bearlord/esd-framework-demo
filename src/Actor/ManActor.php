<?php


namespace App\Actor;


use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorMessage;

class ManActor extends Actor
{
    protected $data;

    protected $money;

    /**
     * @inheritDoc
     */
    public function initData($data)
    {
        $this->data = $data;
        $this->money = $data['money'];
    }

    public function getData()
    {
        return $this->data;
    }

    public function getMoney()
    {
        return $this->money;
    }

    public function outMoney($value)
    {
        $this->money = $this->money - $value;
        return $value;
    }

    public function inMoney($value)
    {
        $this->money = $this->money + $value;
        return $value;
    }

    /**
     * @inheritDoc
     */
    protected function handleMessage(ActorMessage $message)
    {
        printf("Man message: ");
        var_dump($message);
        var_dump($message->getData());
        return 1;
    }
}