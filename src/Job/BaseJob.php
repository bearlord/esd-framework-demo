<?php


namespace App\Job;


use ESD\Yii\Base\BaseObject;
use ESD\Yii\Queue\JobInterface;
use ESD\Yii\Queue\Queue;

class BaseJob extends BaseObject implements JobInterface
{

    public $orderSn;

    public function execute($queue)
    {
        printf("订单SN：%s\n", $this->orderSn);
    }
}