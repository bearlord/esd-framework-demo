<?php

namespace App\Amqp\Consumers;

use ESD\Core\Server\Server;
use ESD\Plugins\Actor\ActorRPCProxy;
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
//        $deviceId = $data['device_id'];
//        $flow = $data['flow'];
//
//        $actorName = sprintf("device_%d", $deviceId);
//        $deviceActor = new ActorRPCProxy($actorName, false, 5);
//        if (!empty($deviceActor)) {
//            //判断设备是否占用，是否可以放水...
//            $deviceActor->rpcFetchWater($flow);
//        }

        var_dump($data);
        return Result::ACK;
    }
}