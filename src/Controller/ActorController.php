<?php

namespace App\Controller;

use App\Actor\ManActor;
use App\Actor\WomanActor;
use ESD\Core\Server\Server;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorManager;
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
        $lily = Actor::getProxy('lily', false);
        $lilei = Actor::getProxy('lilei', false);

        $money1 = 1;
        $money2 = 2;

        $m1 = $lucy->outMoney($money1);
//        $m2 = $lily->outMoney($money2);
        $lilei->inMoney($money1);
//        $lilei->inMoney($money2);

        Server::$instance->getLog()->debug(sprintf("lilei余额：%f", $lilei->getMoney()));

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
        $lilei = Actor::getProxy('lilei', false);
        $m1 = $lucy->outMoney(1);
        $lilei->inMoney($m1);
        Server::$instance->getLog()->debug(sprintf("lucy余额： %f, lilei余额：%f", $lucy->getMoney(), $lilei->getMoney()));
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

        $lilei->startTransaction(function () use ($lucy, $lilei){
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
     * @RequestMapping("pay-back")
     * @return void
     */
    public function actionPayBack()
    {

    }


    /**
     * 删除角色
     * @RequestMapping("delete")
     * @return void
     */
    public function actionDelete()
    {

    }

}