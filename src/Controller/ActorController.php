<?php

namespace App\Controller;

use App\Actor\CaijiActor;
use App\Actor\ManActor;
use App\Actor\WomanActor;
use ESD\Core\Server\Server;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorException;
use ESD\Plugins\Actor\ActorManager;
use ESD\Plugins\Actor\ActorMessage;
use ESD\Plugins\Actor\Multicast\GetMulticast;
use ESD\Plugins\EasyRoute\Annotation\AnyMapping;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\ProcessRPC\ProcessRPCException;

/**
 * @RestController("/actor")
 */
class ActorController extends \ESD\Go\GoController
{
    use GetMulticast;

    /**
     * 创建角色
     * @RequestMapping("create")
     * @return void
     */
    public function actionCreate()
    {
        $lucy = Actor::create(WomanActor::class, 'lucy', [
            'money' => 10001
        ]);

        Actor::create(WomanActor::class, 'lily', [
            'money' => 20002
        ]);
        Actor::create(ManActor::class, 'lilei', [
            'money' => 30
        ]);
        $this->response->withStatus(200)->end();
    }

    /**
     * @RequestMapping("borrow-money")
     * @return void
     * @throws \ESD\Plugins\Actor\ActorException
     * @throws \Throwable
     */
    public function actionBorrowMoney()
    {
        $lucy = Actor::getProxy('lucy', false);
        $lilei = Actor::getProxy('lilei', false);

        $money1 = 1;

        $m1 = $lucy->outMoney($money1);
        $lilei->inMoney($m1);

        Server::$instance->getLog()->debug(sprintf("lilei余额：%f", $lilei->getMoney()));
        $this->response->withStatus(200)->end();
    }

    /**
     * @RequestMapping("trans")
     * @return void
     * @throws \ESD\Plugins\Actor\ActorException
     * @throws \Throwable
     */
    public function actionTrans()
    {
        $lucy = Actor::getProxy('lucy', false);
        $lily = Actor::getProxy('lily', false);
        $lilei = Actor::getProxy('lilei', false);

        $lilei->startTransaction(function () use ($lilei, $lucy, $lily) {
            $money1 = 1;
            $money2 = 2;

            $m1 = $lucy->outMoney($money1);
            $m2 = $lily->outMoney($money2);
            $lilei->inMoney($m1);
            $lilei->inMoney($m2);
        });
        goWithContext(function () use ($lilei) {
            Server::$instance->getLog()->debug(sprintf("lilei余额：%f", $lilei->getMoney()));
        });

        $this->response->withStatus(200)->end();
    }

    /**
     * @RequestMapping("trans2")
     * @return void
     * @throws \ESD\Plugins\Actor\ActorException
     * @throws \Throwable
     */
    public function actionTrans2()
    {
        $lucy = Actor::getProxy('lucy', false);
        $lilei = Actor::getProxy('lilei', false);

        $lilei->startTransaction(function () use ($lucy, $lilei) {
            $m1 = $lucy->outMoney(1);
            $lilei->inMoney($m1);
            Server::$instance->getLog()->debug(sprintf("lilei余额：%f", $lilei->getMoney()));
        });
    }

    /**
     * @RequestMapping("info")
     * @ResponseBody()
     * @return array
     * @throws \ESD\Plugins\Actor\ActorException
     */
    public function actionInfo()
    {
        $name = $this->request->input('name');
        if (empty($name)) {
            $name = 'lucy';
        }
        $actorInfo = ActorManager::getInstance()->getActorInfo($name);

        return [
            $actorInfo->getProcess()->getProcessName(),
            $actorInfo->getProcess()->getProcessId(),
            date("Y-m-d H:i:s", $actorInfo->getCreateTime()),
            $actorInfo->getClassName() . ":" . $actorInfo->getName()
        ];
    }

    /**
     * @RequestMapping("data")
     * @ResponseBody()
     * @return array
     * @throws \ESD\Plugins\Actor\ActorException
     */
    public function actionData()
    {
        $name = $this->request->input('name');
        $actor = Actor::getProxy($name, false);
        $money = $actor->getMoney();

        return [
            'money' => $money
        ];
    }

    /**
     * @RequestMapping("send")
     * @return void
     * @throws \ESD\Plugins\Actor\ActorException
     */
    public function actionSend()
    {
        $lilei = Actor::getProxy('lilei');
        $lilei->sendMessageToActor(new ActorMessage('晚上看电影?？', time(), 'lilei', 'lucy'), 'lucy');
        $lilei->sendMessageToActor(new ActorMessage('晚上看电影?？', time(), 'lilei', 'lily'), 'lily');
    }

    /**
     * @RequestMapping("create-caiji")
     */
    public function actionCreateCaiji()
    {
        for ($i = 1; $i <= 20; $i++) {
            Actor::create(CaijiActor::class, 'caiji-' . $i, 5);
        }
    }

    /**
     * @RequestMapping("do-caiji")
     */
    public function actionDoCaiji()
    {
        for ($i = 1; $i < 1000; $i++) {
            $url = sprintf("https://www.baidu.com/s?wd=%s", $i);
            goWithContext(function () use ($i, $url) {
                $actorName = "caiji-" . ($i % 20);
                $actor = Actor::getProxy($actorName, true);
                $actor->doCaiji($url);
            });
        }
    }

    /**
     * @RequestMapping("create-more")
     * @return void
     * @throws \ESD\Plugins\Actor\ActorException
     */
    public function actionCreateMore()
    {
        for ($i = 1; $i <= 2000; $i++) {
            Actor::create(WomanActor::class, "actor-" . $i, [
                'age' => mt_rand(10, 99)
            ]);
        }
    }

    /**
     * @RequestMapping("subscribe")
     * @return void
     * @throws ActorException
     * @throws ProcessRPCException
     */
    public function actionSubscribe()
    {
        $channel = 'welcome';
        $channel2 = 'welcome2';
        $this->subscribe($channel, 'lucy');
        $this->subscribe($channel, 'lily');
        $this->subscribe($channel, 'lilei');
        $this->subscribe($channel2, 'lucy');
        $this->subscribe($channel2, 'lilei');

        //10秒后，lucy取消订阅 channel
        addTimerAfter(10 * 1000, function() use ($channel, $channel2){
            $this->unsubscribe($channel, 'lucy');
        });
        //20秒后，lilei取消所有的订阅
        addTimerAfter(20 * 1000, function() use ($channel, $channel2){
            $this->unsubscribeAll('lilei');
        });
        //30秒后，删除 channel
        addTimerAfter(30 * 1000, function() use ($channel, $channel2) {
            $this->deleteChannel($channel);
        });
    }

    /**
     * @RequestMapping("publish")
     * @return void
     * @throws ProcessRPCException
     */
    public function actionPublish()
    {
        $channel = 'welcome';
        $channel2 = 'welcome2';
        $this->publish($channel, "欢迎~~~");
        $this->publish($channel2, "逛街去~~~");
    }
}