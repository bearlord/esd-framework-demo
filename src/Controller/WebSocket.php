<?php

namespace App\Controller;

use App\Libs\ExtraHelper;
use ESD\Core\Server\Beans\Request;
use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\WsController;
use ESD\Plugins\Redis\GetRedis;
use ESD\Server\Co\Server;

/**
 * @WsController()
 * Class WebSocket
 * @package app\Controller
 */
class WebSocket extends GoController
{
    use GetRedis;
    /**
     * @RequestMapping("onWsOpen")
     */
    public function actionWsOpen($fd, $reactorId)
    {
        printf("onWsOpen. fd: %d, reactorId: %d\n", $fd, $reactorId);
    }
    /**
     * @RequestMapping("onWsClose")
     */
    public function actionWsClose($fd, $reactorId)
    {
        printf("onWsClose. fd: %d, reactorId: %d\n", $fd, $reactorId);
    }
    /**
     * @RequestMapping("receive-test")
     * @return void
     */
    public function actionReceiveTest()
    {
        $data = $this->clientData->getData();
        var_dump($data);
        $fd = $this->clientData->getFd();
        $this->autoBoostSend($fd, ['content' => 'Hi~~~']);
    }


    protected $hashCode = 'ws-onenet-8082';

    /**
     * @RequestMapping()
     */
    public function wsBindUid()
    {
        $this->bindUid($this->clientData->getFd(), "test1");
        return "test";
    }

    /**
     * @RequestMapping()
     * @return mixed|null
     */
    public function wsGetUid()
    {
        return $this->getFdUid($this->clientData->getFd());
    }

    /**
     * @RequestMapping()
     * @return mixed|null
     */
    public function send()
    {
        $this->sendToUid("test1", "hello");
    }

    /**
     * @RequestMapping()
     * @return mixed|null
     * @throws \ESD\Plugins\ProcessRPC\ProcessRPCException
     */
    public function wsAddSub()
    {
        $this->addSub("sub", $this->getUid());
    }

    /**
     * @RequestMapping()
     * @throws \ESD\Plugins\ProcessRPC\ProcessRPCException
     */
    public function wsPub()
    {
        $this->pub("sub", "sub");
    }

    /**
     * @RequestMapping()
     * @return mixed|null
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function auth()
    {
        $data = $this->clientData->getData();
        if (empty($data)) {
            return false;
        }

        if (empty($data['device_id'] || empty($data['code']))) {
            return false;
        }

        if (md5($data['device_id'] . $this->hashCode) !== $data['code']) {
            return;
        }

        //Device id
        $deviceId = $data['device_id'];
        //Server fd
        $fd = $this->clientData->getFd();

        //Websocket id
        $wsKey  = ExtraHelper::getWSKey($deviceId);
        //Fd id
        $fdKey = ExtraHelper::getFdKey($fd);

        $this->redis()->set($fdKey, $deviceId);
        $this->redis()->sAdd($wsKey, $fd);
    }


    /**
     * @param int $fd
     * @param int $reactorId
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function onWsClose(int $fd, int $reactorId)
    {
        var_dump("Closed");
        //Fd
        $fd = $this->clientData->getFd();
        //Fd id
        $fdKey = ExtraHelper::getFdKey($fd);

        //Device id
        $deviceId = $this->redis()->get($fdKey);
        //Websocket id
        $wsKey  = ExtraHelper::getWSKey($deviceId);

        $this->redis()->del($fdKey);
        $this->redis()->sRem($wsKey, $fd);
    }
}
