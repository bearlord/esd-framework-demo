<?php

namespace App\Amqp\Consumers;

use ESD\Core\Server\Server;
use ESD\Plugins\Amqp\Annotation\Consumer;
use ESD\Plugins\Amqp\Message\ConsumerMessage;
use ESD\Plugins\Amqp\Result;


/**
 * @Consumer(exchange="esd-device", routingKey="esd-device", queue="esd-device", nums=1)
 */
class DeviceRechargeConsumer extends ConsumerMessage
{
    public function consume($data): string
    {
//        printf("%s %s\n", date("Y-m-d H:i:s"), json_encode($data));

//        Server::$instance->getLog()->debug(json_encode($data));

        //1个任务需要2秒钟
//        \Swoole\Coroutine::sleep(0.01);

        return Result::ACK;
    }
}