<?php

namespace App\Amqp\Consumers;

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
        printf("%s %s\n", date("Y-m-d H:i:s"), $data);
        return Result::ACK;
    }
}