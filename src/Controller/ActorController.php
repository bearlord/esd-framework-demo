<?php

namespace App\Controller;

use App\Actor\CaijiActor;
use App\Actor\ManActor;
use App\Actor\WomanActor;
use ESD\Core\Server\Server;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorManager;
use ESD\Plugins\Actor\ActorMessage;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;

/**
 * @RestController("/actor")
 */
class ActorController extends \ESD\Go\GoController
{

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
        for ($i = 0; $i <= 9; $i++) {
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
                $actorName = "caiji-" . ($i % 10);
                $actor = Actor::getProxy($actorName, true);
                $actor->doCaiji($url);
            });
        }
    }

}