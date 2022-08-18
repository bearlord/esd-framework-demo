<?php

namespace App;

use App\Plugins\Demo\DemoPlugin;
use ESD\Go\GoApplication;
use ESD\Plugins\Actor\ActorConfig;
use ESD\Plugins\Actor\ActorPlugin;
use ESD\Plugins\Amqp\AmqpConsumerPlugin;
use ESD\Plugins\Amqp\AmqpPlugin;
use ESD\Plugins\Scheduled\ScheduledPlugin;
use ESD\Yii\Plugin\Mongodb\MongodbPlugin;
use ESD\Yii\Plugin\Pdo\PdoPlugin;
use ESD\Yii\Plugin\Queue\QueuePlugin;

class Application
{
    /**
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     * @throws \ESD\Core\Exception
     * @throws \ESD\Core\Plugins\Config\ConfigException
     * @throws \ReflectionException
     */
    public static function main()
    {
        $goApp = new GoApplication();

        $actorConfig = new ActorConfig();
        $actorConfig->setActorWorkerCount(3);
        $goApp->addPlugin(new ActorPlugin($actorConfig));

//        $goApp->addPlugin(new ScheduledPlugin());
        $goApp->addPlugin(new PdoPlugin());
        $goApp->addPlugin(new AmqpPlugin());
        $goApp->addPlugin(new AmqpConsumerPlugin());
//        $goApp->addPlugin(new MongodbPlugin());
        $goApp->addPlugin(new QueuePlugin());

//        $goApp->addPlugin(new DemoPlugin());
        $goApp->run(Application::class);
    }
}
