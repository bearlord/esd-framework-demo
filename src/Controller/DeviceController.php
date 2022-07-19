<?php

namespace App\Controller;

use ESD\Core\Server\Server;
use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\Pack\GetBoostSend;
use ESD\Plugins\Redis\GetRedis;
use ESD\Yii\Helpers\Json;

/**
 * @RestController("device")
 *
 * Class DeviceController
 * @package App\Controller
 */
class DeviceController extends GoController
{
    use GetRedis;
    use GetBoostSend;

    /**
     * @GetMapping("control")
     */
    public function actionControl()
    {
        //验证数据合法性略
        $deviceId = $this->request->input('device_id');
        $action = $this->request->input('action');

        $deviceKey = sprintf("device_%s", $deviceId);
        //设备ID和连接FD的关系
        $deviceConnInfo = $this->redis()->hGetAll($deviceKey);
        //FD标识
        $fd = $deviceConnInfo['fd'];

        //发送指令到设备
        $cmd = sprintf("%s_%s", $action, $deviceId);
        $this->autoBoostSend($fd, $cmd);
        //事件名称
        $eventName = sprintf("shutdown_receipt_%s", $deviceId);
        //事件派发器
        $eventDispatcher = Server::$instance->getEventDispatcher();
        //等待事件回执
        $call = $eventDispatcher->listen($eventName)->wait(20);

        if (empty($call) || empty($call->getData())) {
            $responseData = [
                'code' => 4400,
                'message' => sprintf("设备%s关机指令执行失败", $deviceId)
            ];
            $this->response
                ->setHeaders([
                    'Content-Type' => 'application/json;charset=utf8'
                ])
                ->withContent(Json::encode($responseData))->end();
            return true;
        }

        $data = $call->getData();
        if (!empty($data)) {
            $responseData = [
                'code' => 200,
                'message' => sprintf("设备%s关机成功", $deviceId)
            ];
            $this->response
                ->setHeaders([
                    'Content-Type' => 'application/json;charset=utf8'
                ])
                ->withContent(Json::encode($responseData))->end();
        }

    }
}