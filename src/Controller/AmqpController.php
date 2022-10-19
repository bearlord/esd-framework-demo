<?php

namespace App\Controller;

use ESD\Core\Runtime;
use ESD\Coroutine\Concurrent;
use ESD\Go\GoController;
use ESD\Plugins\Amqp\GetAmqp;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * @RestController("amqp")
 */
class AmqpController extends GoController
{
    use GetAmqp;

    /**
     * @RequestMapping("hb")
     * @ResponseBody()
     * @return array
     * @throws \Exception
     */
    public function actionHeartBeat()
    {
        $amqp = $this->amqpOnce();

        return [
            'Runtime::$enableCoroutine' => Runtime::$enableCoroutine,
            'lastActivity' => date("Y-m-d H:i:s\n", $amqp->getLastActivity())
        ];
    }

    /**
     * @RequestMapping("produce2")
     * @return int
     * @throws \Exception
     */
    public function actionPublish2()
    {
        try {
            $amqp = $this->amqp()->getConnection();
            $channel = $amqp->channel();
            $channel->queue_declare('hello', false, false, false, false);

            for( $i = 1; $i <= 1; $i++ ) {
                $content = sprintf("Hello world! - %s - %d", date("Y-m-d H:i:s"), $i);
                $msg = new AMQPMessage($content);
                $channel->basic_publish($msg, '', 'hello');
                printf("=> %s Send %s\n", date("Y-m-d H:i:s"), $content);
            }

        } catch (\Exception $exception) {
            printf("code: %d, message: %s\n", $exception->getCode(), $exception->getMessage());
        }

        return 1;
    }

    /**
     * @RequestMapping("produce")
     * @return int
     * @throws \Exception
     */
    public function actionPublish()
    {
        try {
            $amqp = $this->amqp()->getConnection();
            $channel = $amqp->channel();
            $channel->queue_declare('hello', false, false, false, false);

            for( $i = 1; $i <= 10; $i++ ) {
                $content = sprintf("Hello world! - %s - %d", date("Y-m-d H:i:s"), $i);
                $msg = new AMQPMessage($content);
                $channel->basic_publish($msg, '', 'hello');
                printf("=> %s Send %s\n", date("Y-m-d H:i:s"), $content);
            }

        } catch (\Exception $exception) {
            printf("code: %d, message: %s\n", $exception->getCode(), $exception->getMessage());
        }

        return 1;
    }

    /**
     * @RequestMapping("consume")
     * @return void
     * @throws \ErrorException
     */
    public function actionConsume()
    {
//        $amqp = $this->amqp()->getConnection();
//        $channel = $amqp->channel();
//        $channel->queue_declare('hello', false, false, false, false);
//        $callback = function ($msg) {
//            printf("<= %s Received %s\n", date("Y-m-d H:i:s"), $msg->body);
//        };
//
//        $channel->basic_consume('hello', '', false, true, false, false, $callback);

        goWithContext(function ()  {
            $amqp = $this->amqp()->getConnection();
            $channel = $amqp->channel();
            $channel->queue_declare('hello', false, false, false, false);
            $callback = function ($msg) {
                printf("<= %s Received %s\n", date("Y-m-d H:i:s"), $msg->body);
            };

            $channel->basic_consume('hello', '', false, true, false, false, $callback);
            while($channel->is_consuming()) {
                $channel->wait(null, true);
                \Swoole\Coroutine::sleep(0.01);
            }
        });

//        addTimerTick(100, function () use ($channel) {
//            goWithContext(function () use ($channel) {
//                while($channel->is_consuming()) {
//                    $channel->wait(null, true);
//                    \Swoole\Coroutine::sleep(1);
//                }
//            });
//        });



    }
}