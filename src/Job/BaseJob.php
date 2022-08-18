<?php


namespace App\Job;

use ESD\Coroutine\Coroutine;
use ESD\Yii\Base\BaseObject;
use ESD\Yii\Queue\JobInterface;
use ESD\Yii\Queue\Queue;

class BaseJob extends BaseObject implements JobInterface
{

    public $orderSn;

    public function execute($queue)
    {
        printf("订单SN：%s\n", $this->orderSn);
//        Coroutine::sleep(mt_rand(10 / 99) / 100);
        printf("订单处理成功：%s\n\n", $this->orderSn);
    }
}