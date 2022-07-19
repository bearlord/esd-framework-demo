<?php

namespace App\Amqp\Producers;

use ESD\Plugins\Amqp\Annotation\Producer;
use ESD\Plugins\Amqp\Message\ProducerMessage;

/**
 * @Producer(exchange="esd-device", routingKey="esd-device")
 */
class DeviceRechargeProducer extends ProducerMessage
{
    public function __construct($id)
    {
        $data = [
            $id, 'hello'
        ];

        $this->payload = [
            'id' => $id,
            'data' => $data
        ];
    }

}