<?php

namespace App\Controller;

use App\Amqp\Producers\DeviceRechargeProducer;
use ESD\Go\GoController;
use ESD\Plugins\Amqp\Consumer;
use ESD\Plugins\Amqp\Message\ProducerMessage;
use ESD\Plugins\Amqp\Producer;
use ESD\Plugins\AnnotationsScan\ScanClass;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Server\Coroutine\Server;

/**
 * @RestController("producer-consumer")
 */
class ProducerConsumerController extends GoController
{

    /**
     * @Inject
     * @var Producer
     */
    protected $producer;

    /**
     * @var Consumer
     */
    protected $consumer;

    /**
     * @GetMapping("test")
     */
    public function actionTest()
    {
        /** @var ScanClass $scanClass */
        $scanClass = DIget(ScanClass::class);

        var_dump($scanClass->getCachedReader());

        $producer = new DeviceRechargeProducer(1);

        $reflect = new \ReflectionClass($producer);
//
//        var_dump(new \ReflectionClass($producer));
//
        $r = $scanClass->getClassAndInterfaceAnnotation($reflect, \ESD\Plugins\Amqp\Annotation\Producer::class);


        var_dump($r);
    }

    /**
     * @GetMapping("producer");
     * @throws \Throwable
     */
    public function actionProduce()
    {
        for ($i = 1; $i <= 2; $i++) {
            $this->producer->produce(new DeviceRechargeProducer($i));
        }
        $this->response->withContent("success")->end();

    }

    /**
     * @GetMapping("test2")
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function actionTest2()
    {
        //Scan annotation
        $scanClass = Server::$instance->getContainer()->get(ScanClass::class);
        $reflectionClasses = $scanClass->findClassesByAnn(\ESD\Plugins\Amqp\Annotation\Consumer::class);
        var_dump($reflectionClasses);
        foreach ($reflectionClasses as $reflectionClass) {
            $annotation = $scanClass->reflectionClasses($reflectionClass, \ESD\Plugins\Amqp\Annotation\Consumer::class);
            var_dump($annotation);

        }
    }
}