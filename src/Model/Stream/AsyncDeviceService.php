<?php

namespace App\Model\Stream;

use App\Libs\ExtraHelper;
use App\Model\Device;
use ESD\Core\Server\Server;
use ESD\Plugins\Pack\GetBoostSend;
use ESD\Plugins\Redis\GetRedis;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class AsyncDeviceService
{
    use GetRedis;
    use GetBoostSend;

    /**
     * @var int 时间捕获过期时间 3秒
     */
    protected $eventDispatcherTtl = 3;

    /**
     * @var int 检测设备掉线的时间
     */
    protected $activeTtl = 1200;

    /**
     * 获取Redis缓存的设备信息
     *
     * @param int $deviceId
     * @return array
     * @throws \Server\CoreBase\SwooleException
     */
    protected function getRedisDeviceInfo(int $deviceId): array
    {
        $deviceKey = ExtraHelper::buildRedisDeviceKey($deviceId);

        //从redis获取当前设备的详细信息
        $deviceInfo = $this->redis()->hGetAll($deviceKey);

        return $deviceInfo ?? [];
    }

    /**
     * 发送数据至指定的Fd连接
     *
     * @param int $fd
     * @param string $data
     * @param string $deviceId
     * @return bool
     * @throws \Exception
     */
    public function sendDataToFd(int $fd, string $data, string $deviceId): bool
    {
        ExtraHelper::debug($fd, 2, $data);

        $ctrl = $deviceState = "";
        if (strlen($data) > 8) {
            $ctrl = substr($data, 10, 2);
            $deviceState = substr($data, 12, 2);
        }

        $streamModel = new StreamModel();
        $streamModel->logToMySQL($data, $fd, 2, $deviceId, $ctrl, $deviceState);
        $this->autoBoostSend($fd, $data);
        return true;
    }

    /**
     * 发送数据到设备
     *
     * @param int $deviceId
     * @param string $cmdString
     * @return bool
     * @throws \Server\CoreBase\SwooleException
     */
    public function sendDataToDevice(int $deviceId, string $cmdString): bool
    {
        $redisDeviceInfo = $this->getRedisDeviceInfo($deviceId);

        if (empty($redisDeviceInfo)) {
            return false;
        }

        if (time() - $redisDeviceInfo['updated_at'] > $this->activeTtl) {
            return false;
        }
        if (!Server::$instance->existFd($redisDeviceInfo['fd'])) {
            return false;
        }
        $this->sendDataToFd($redisDeviceInfo['fd'], $cmdString, $deviceId);
        return true;
    }

    /**
     * 删除Redis之前可能因超时保存的数据
     *
     * @param string $eventName
     * @throws \ESD\Plugins\Redis\RedisException
     */
    protected function deleteLastEventReceipt(string $eventName)
    {
        $this->redis()->del($eventName);
    }

    /**
     * @param string $eventName
     * @param int $wait
     * @return mixed
     */
    protected function waitEventReceipt(string $eventName, int $wait)
    {
        $interval = 300;
        $tries = ceil($wait / $interval);

        $chan = new Channel(1);
        Coroutine::create(function () use ($eventName, $interval, $tries, $chan) {
            for ($i = 1; $i <= $tries; $i++) {
                $value = $this->redis()->get($eventName);
                if ($value) {
                    $data = json_decode($value, true);
                    $this->redis()->del($eventName);
                    $chan->push($data);
                    break;
                } else {
                    if ($i == $tries) {
                        $chan->push(false);
                    }
                }
                Coroutine::sleep($interval / 1000);
            }
        });
        return $chan->pop();
    }

    /**
     * @param int $deviceId
     * @param array $args
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function boot(int $deviceId, array $args, array $presetInfo): bool
    {
        //动作方法
        $actionName = 'boot';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Ctrl
        $ctrlHex = '02';

        if ($protocolLength === 90) {
            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
        } else if ($protocolLength === 104) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        } else if ($protocolLength === 116) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
        }

        //事件名称
        $eventName = sprintf("%sReceipt_%s", $actionName, $deviceId);
        $eventDispatcherTtl = $this->eventDispatcherTtl;

        $chan = new Channel(1);
        Coroutine::create(function () use ($deviceId, $cmdString, $eventName, $eventDispatcherTtl, $chan) {
            $this->deleteLastEventReceipt($eventName);
            $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
            if (!$sendResult) {
                $message = [
                    'code' => 4003,
                    'message' => '设备不在线，指令发送失败!',
                    'data' => new \stdClass()
                ];
                $chan->push($message);
                return false;
            }
            $eventData = $this->waitEventReceipt($eventName, $eventDispatcherTtl);
            $chan->push($eventData);
        });
        $eventData = $chan->pop();
        if (!empty($eventData['code'])) {
            return false;
        }
        if (empty($eventData)) {
            return false;
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param array $args
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function shutdown(int $deviceId, array $args, array $presetInfo): bool
    {
        //动作方法
        $actionName = 'shutdown';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Ctrl
        $ctrlHex = '01';

        if ($protocolLength === 90) {
            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
        } else if ($protocolLength === 104) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        } else if ($protocolLength === 116) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
        }

        //事件名称
        $eventName = sprintf("%sReceipt_%s", $actionName, $deviceId);
        $eventDispatcherTtl = $this->eventDispatcherTtl;

        $chan = new Channel(1);
        Coroutine::create(function () use ($deviceId, $cmdString, $eventName, $eventDispatcherTtl, $chan) {
            $this->deleteLastEventReceipt($eventName);
            $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
            if (!$sendResult) {
                $message = [
                    'code' => 4003,
                    'message' => '设备不在线，指令发送失败!',
                    'data' => new \stdClass()
                ];
                $chan->push($message);
                return false;
            }
            $eventData = $this->waitEventReceipt($eventName, $eventDispatcherTtl);
            $chan->push($eventData);
        });
        $eventData = $chan->pop();
        if (!empty($eventData['code'])) {
            return false;
        }
        if (empty($eventData)) {
            return false;
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param array $args
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \ESD\Yii\Db\Exception
     */
    public function recharge(int $deviceId, array $args, array $presetInfo): bool
    {
        //动作方法
        $actionName = 'recharge';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Ctrl
        $ctrlHex = '05';

        //充值时间
        $addTime = !empty($args['addTime']) ? $args['addTime'] : 0;
        //充值流量
        $addFlow = !empty($args['addFlow']) ? $args['addFlow'] : 0;

        //设备信息
        $deviceInfo = (new Device())->getItemByDeviceId($deviceId);

        if (empty($deviceInfo)) {
            return false;
        }

        //计费模式
        $chargeMode = (int)$deviceInfo['charge_mode'];

        //充值时间
        $addTimeHex = ($addTime >= 0) ? ExtraHelper::decHexPad($addTime, 4) : dechex($addTime & 0xffff);
        //充值流量
        $addFlowHex = ($addFlow >= 0) ? ExtraHelper::decHexPad($addFlow, 4) : dechex($addFlow & 0xffff);

        if ($protocolLength === 90) {
            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $addFlowHex, 18, 4);
            $cmdString = substr_replace($cmdString, $addTimeHex, 22, 4);
        } else if ($protocolLength === 104) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $addFlowHex, 18, 4);
            $cmdString = substr_replace($cmdString, $addTimeHex, 22, 4);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        } else if($protocolLength === 116) {
            //充值流量
            $addFlowHex = ($addFlow >= 0) ? ExtraHelper::decHexPad($addFlow, 8) : dechex($addFlow & 0xffffffff);

            printf("addFlow: %d, addFlowHex: %s\n", $addFlow, $addFlowHex);

            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $addFlowHex, 18, 8);
            $cmdString = substr_replace($cmdString, $addTimeHex, 26, 4);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
        }

        //事件名称
        $eventName = sprintf("%sReceipt_%s", $actionName, $deviceId);
        $eventDispatcherTtl = $this->eventDispatcherTtl;

        $chan = new Channel(1);
        Coroutine::create(function () use ($deviceId, $cmdString, $eventName, $eventDispatcherTtl, $chan) {
            $this->deleteLastEventReceipt($eventName);
            $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
            if (!$sendResult) {
                $message = [
                    'code' => 4003,
                    'message' => '设备不在线，指令发送失败!',
                    'data' => new \stdClass()
                ];
                $chan->push($message);
                return false;
            }
            $eventData = $this->waitEventReceipt($eventName, $eventDispatcherTtl);
            $chan->push($eventData);
        });
        $eventData = $chan->pop();
        if (!empty($eventData['code'])) {
            return false;
        }
        if (empty($eventData)) {
            return false;
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param array $args
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function query(int $deviceId, array $args, array $presetInfo): bool
    {
        //动作方法
        $actionName = 'query';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Ctrl
        $ctrlHex = '0d';

        if ($protocolLength === 90) {
            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
        } else if ($protocolLength === 104) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        } else if ($protocolLength === 116) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
        }

        //事件名称
        $eventName = sprintf("%sReceipt_%s", $actionName, $deviceId);
        $eventDispatcherTtl = $this->eventDispatcherTtl;

        $chan = new Channel(1);
        Coroutine::create(function () use ($deviceId, $cmdString, $eventName, $eventDispatcherTtl, $chan) {
            $this->deleteLastEventReceipt($eventName);
            $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
            if (!$sendResult) {
                $message = [
                    'code' => 4003,
                    'message' => '设备不在线，指令发送失败!',
                    'data' => new \stdClass()
                ];
                $chan->push($message);
                return false;
            }
            $eventData = $this->waitEventReceipt($eventName, $eventDispatcherTtl);
            $chan->push($eventData);
        });
        $eventData = $chan->pop();
        if (!empty($eventData['code'])) {
            return false;
        }
        if (empty($eventData)) {
            return false;
        }
        return $eventData;
    }

    /**
     * @param int $deviceId
     * @param array $args
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function systemInit(int $deviceId, array $args, array $presetInfo): bool
    {
        //动作方法
        $actionName = 'systemInit';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Ctrl
        $ctrlHex = '09';
        //计费模式
        $chargeMode = isset($args['chargeMode']) ? (int)$args['chargeMode'] : 0;
        //剩余流量
        $restFlow = !empty($args['restFlow']) ? $args['restFlow'] : 0;
        //剩余时长
        $restTime = !empty($args['restTime']) ? $args['restTime'] : 0;
        //已用流量
        $usedFlow = !empty($args['usedFlow']) ? $args['usedFlow'] : 0;
        //已用时长
        $usedTime = !empty($args['usedTime']) ? $args['usedTime'] : 0;

        $F1FluxMax = !empty($args['filters'][0]) ? $args['filters'][0] : 0;
        $F2FluxMax = !empty($args['filters'][1]) ? $args['filters'][1] : 0;
        $F3FluxMax = !empty($args['filters'][2]) ? $args['filters'][2] : 0;
        $F4FluxMax = !empty($args['filters'][3]) ? $args['filters'][3] : 0;
        $F5FluxMax = !empty($args['filters'][4]) ? $args['filters'][4] : 0;

        //滤芯剩余寿命
        $F1Flux = !empty($args['fluxes'][0]) ? $args['fluxes'][0] : $F1FluxMax;
        $F2Flux = !empty($args['fluxes'][1]) ? $args['fluxes'][1] : $F2FluxMax;
        $F3Flux = !empty($args['fluxes'][2]) ? $args['fluxes'][2] : $F3FluxMax;
        $F4Flux = !empty($args['fluxes'][3]) ? $args['fluxes'][3] : $F4FluxMax;
        $F5Flux = !empty($args['fluxes'][4]) ? $args['fluxes'][4] : $F5FluxMax;

        //计费模式Hex
        $chargeModeHex = ExtraHelper::decHexPad($chargeMode, 2);
        //剩余流量Hex
        $restFlowHex = ExtraHelper::decHexPad($restFlow, 4);
        //剩余时长Hex
        $restTimeHex = ExtraHelper::decHexPad($restTime, 4);
        //已用流量
        $usedFlowHex = ExtraHelper::decHexPad($usedFlow, 4);
        //已用时长
        $usedTimeHex = ExtraHelper::decHexPad($usedTime, 4);

        //滤芯总寿命Hex
        $F1FluxMaxHex = ExtraHelper::decHexPad($F1FluxMax, 4);
        $F2FluxMaxHex = ExtraHelper::decHexPad($F2FluxMax, 4);
        $F3FluxMaxHex = ExtraHelper::decHexPad($F3FluxMax, 4);
        $F4FluxMaxHex = ExtraHelper::decHexPad($F4FluxMax, 4);
        $F5FluxMaxHex = ExtraHelper::decHexPad($F5FluxMax, 4);

        //滤芯剩余寿命Hex
        $F1FluxHex = ExtraHelper::decHexPad($F1Flux, 4);
        $F2FluxHex = ExtraHelper::decHexPad($F2Flux, 4);
        $F3FluxHex = ExtraHelper::decHexPad($F3Flux, 4);
        $F4FluxHex = ExtraHelper::decHexPad($F4Flux, 4);
        $F5FluxHex = ExtraHelper::decHexPad($F5Flux, 4);

        if ($protocolLength === 90) {
            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $restFlowHex, 26, 4);
            $cmdString = substr_replace($cmdString, $restTimeHex, 30, 4);
            $cmdString = substr_replace($cmdString, $usedFlowHex, 34, 4);
            $cmdString = substr_replace($cmdString, $usedTimeHex, 38, 4);
            $cmdString = substr_replace($cmdString, $F1FluxHex, 50, 4);
            $cmdString = substr_replace($cmdString, $F2FluxHex, 54, 4);
            $cmdString = substr_replace($cmdString, $F3FluxHex, 58, 4);
            $cmdString = substr_replace($cmdString, $F4FluxHex, 62, 4);
            $cmdString = substr_replace($cmdString, $F5FluxHex, 66, 4);
            $cmdString = substr_replace($cmdString, $F1FluxMaxHex, 70, 4);
            $cmdString = substr_replace($cmdString, $F2FluxMaxHex, 74, 4);
            $cmdString = substr_replace($cmdString, $F3FluxMaxHex, 78, 4);
            $cmdString = substr_replace($cmdString, $F4FluxMaxHex, 82, 4);
            $cmdString = substr_replace($cmdString, $F5FluxMaxHex, 86, 4);

        } else if ($protocolLength === 104) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $restFlowHex, 26, 4);
            $cmdString = substr_replace($cmdString, $restTimeHex, 30, 4);
            $cmdString = substr_replace($cmdString, $usedFlowHex, 34, 4);
            $cmdString = substr_replace($cmdString, $usedTimeHex, 38, 4);
            $cmdString = substr_replace($cmdString, $F1FluxHex, 50, 4);
            $cmdString = substr_replace($cmdString, $F2FluxHex, 54, 4);
            $cmdString = substr_replace($cmdString, $F3FluxHex, 58, 4);
            $cmdString = substr_replace($cmdString, $F4FluxHex, 62, 4);
            $cmdString = substr_replace($cmdString, $F5FluxHex, 66, 4);
            $cmdString = substr_replace($cmdString, $F1FluxMaxHex, 70, 4);
            $cmdString = substr_replace($cmdString, $F2FluxMaxHex, 74, 4);
            $cmdString = substr_replace($cmdString, $F3FluxMaxHex, 78, 4);
            $cmdString = substr_replace($cmdString, $F4FluxMaxHex, 82, 4);
            $cmdString = substr_replace($cmdString, $F5FluxMaxHex, 86, 4);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        } else if ($protocolLength === 116) {
            //剩余流量Hex
            $restFlowHex = ExtraHelper::decHexPad($restFlow, 8);
            //已用流量
            $usedFlowHex = ExtraHelper::decHexPad($usedFlow, 8);

            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $restFlowHex, 30, 8);
            $cmdString = substr_replace($cmdString, $restTimeHex, 38, 4);
            $cmdString = substr_replace($cmdString, $usedFlowHex, 42, 8);
            $cmdString = substr_replace($cmdString, $usedTimeHex, 50, 4);
            $cmdString = substr_replace($cmdString, $F1FluxHex, 62, 4);
            $cmdString = substr_replace($cmdString, $F2FluxHex, 66, 4);
            $cmdString = substr_replace($cmdString, $F3FluxHex, 70, 4);
            $cmdString = substr_replace($cmdString, $F4FluxHex, 74, 4);
            $cmdString = substr_replace($cmdString, $F5FluxHex, 78, 4);
            $cmdString = substr_replace($cmdString, $F1FluxMaxHex, 82, 4);
            $cmdString = substr_replace($cmdString, $F2FluxMaxHex, 86, 4);
            $cmdString = substr_replace($cmdString, $F3FluxMaxHex, 90, 4);
            $cmdString = substr_replace($cmdString, $F4FluxMaxHex, 94, 4);
            $cmdString = substr_replace($cmdString, $F5FluxMaxHex, 98, 4);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
        }

        //事件名称
        $eventName = sprintf("%sReceipt_%s", $actionName, $deviceId);
        $eventDispatcherTtl = $this->eventDispatcherTtl;

        $chan = new Channel(1);
        Coroutine::create(function () use ($deviceId, $cmdString, $eventName, $eventDispatcherTtl, $chan) {
            $this->deleteLastEventReceipt($eventName);
            $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
            if (!$sendResult) {
                $message = [
                    'code' => 4003,
                    'message' => '设备不在线，指令发送失败!',
                    'data' => new \stdClass()
                ];
                $chan->push($message);
                return false;
            }
            $eventData = $this->waitEventReceipt($eventName, $eventDispatcherTtl);
            $chan->push($eventData);
        });
        $eventData = $chan->pop();
        if (!empty($eventData['code'])) {
            return false;
        }
        if (empty($eventData)) {
            return false;
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param array $args
     * @param array|null $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function reset(int $deviceId, array $args, ?array $presetInfo): bool
    {
        //动作方法
        $actionName = 'reset';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Redis缓存的信息
        $redisDeviceInfo = $this->getRedisDeviceInfo($deviceId);

        //预先记录fd与device_id关系
        $connKey = ExtraHelper::buildRedisTcpKey($redisDeviceInfo['fd']);
        $this->redis()->set($connKey, $deviceId);

        //Ctrl
        $ctrlHex = '0a';

        if ($protocolLength === 90) {
            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
        } else if ($protocolLength === 104) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        } else if ($protocolLength === 116) {
            //Seconds from zero
            $secondsFromZero = ExtraHelper::secondsFromZero();
            $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

            $cmdTemplate = str_repeat(0, $protocolLength);
            $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
            $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
            $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
        }

        $eventName = sprintf("RESET_%s", $deviceId);
        $this->redis()->set($eventName, true);
        $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
        if (!$sendResult) {
            return false;
        }
        return true;
    }

    /**
     * 配置硬件参数
     *
     * @param int $deviceId
     * @param array $args
     * @param array|null $presetInfo
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function setConfig(int $deviceId, array $args, ?array $presetInfo): bool
    {
        //动作方法
        $actionName = 'setConfig';
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];
        if ($protocolLength !== 116) {
            return false;
        }

        //滤芯计费模式，存在[0,1]，不能用empty判断。
        $filterChargeMode = isset($args['filterChargeMode']) ? (int)$args['filterChargeMode'] : $presetInfo['filter_charge_mode'];
        //流量计脉冲
        $flowmeter = !empty($args['flowmeter']) ? (int)$args['flowmeter'] : $presetInfo['flowmeter'];
        //水龙头
        $faucet = !empty($args['faucet']) ? (int)$args['faucet'] : $presetInfo['faucet'];
        //水泵
        $waterPump = !empty($args['waterPump']) ? (int)$args['waterPump'] : $presetInfo['water_pump'];
        //维修时间
        $maintenance = !empty($args['maintenance']) ? (int)$args['maintenance'] : $presetInfo['maintenance'];
        //最高温度
        $maximumTemperature = !empty($args['maximumTemperature']) ? (int)$args['maximumTemperature'] : $presetInfo['maximum_temperature'];
        //最低温度
        $minimumTemperature = !empty($args['minimumTemperature']) ? (int)$args['minimumTemperature'] : $presetInfo['minimum_temperature'];
        //热水流量脉冲
        $flowmeterHot = !empty($args['flowmeterHot']) ? (int)$args['flowmeterHot'] : $presetInfo['flowmeter_hot'];
        //热水水龙头
        $faucetHot = !empty($args['faucetHot']) ? (int)$args['faucetHot'] : $presetInfo['faucet_hot'];

        $maximumWaterAmount = !empty($args['maximumWaterAmount']) ? (int)$args['maximumWaterAmount'] : $presetInfo['maximum_water_amount'];

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);
        //Ctrl
        $ctrlHex = '04';

        $filterChargeModeHex = ExtraHelper::decHexPad($filterChargeMode, 2);
        $flowmeterHex = ExtraHelper::decHexPad($flowmeter, 8);
        $faucetHex = ExtraHelper::decHexPad($faucet, 4);
        $waterPumpHex = ExtraHelper::decHexPad($waterPump, 8);
        $maintenanceHex = ExtraHelper::decHexPad($maintenance, 4);
        $flowmeterHotHex = ExtraHelper::decHexPad($flowmeterHot, 4);
        $faucetHotHex = ExtraHelper::decHexPad($faucetHot, 4);
        $maximumWaterAmountHex = ExtraHelper::decHexPad($maximumWaterAmount, 4);

        //程序码
        $programCode = $presetInfo['program_code'];
        $programCodeHex = ExtraHelper::decHexPad($programCode, 2);

        //Seconds from zero
        $secondsFromZero = ExtraHelper::secondsFromZero();
        $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

        $cmdTemplate = str_repeat(0, $protocolLength);
        $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
        $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
        $cmdString = substr_replace($cmdString, $filterChargeModeHex, 12, 2);
        $cmdString = substr_replace($cmdString, $flowmeterHex, 18, 8);
        $cmdString = substr_replace($cmdString, $faucetHex, 26, 4);
        $cmdString = substr_replace($cmdString, $waterPumpHex, 30, 8);
        $cmdString = substr_replace($cmdString, $maintenanceHex, 38, 4);
        $cmdString = substr_replace($cmdString, $flowmeterHotHex, 58, 4);
        $cmdString = substr_replace($cmdString, $maximumWaterAmountHex, 66, 4);
        $cmdString = substr_replace($cmdString, $faucetHotHex, 74, 4);

        //如果冷热水分别计算
        if (in_array($presetInfo['device_sub_type'], [1, 2])) {
            $maximumTemperatureHex = ExtraHelper::decHexPad($maximumTemperature, 8);
            $minimumTemperatureHex = ExtraHelper::decHexPad($minimumTemperature, 4);
            $cmdString = substr_replace($cmdString, $maximumTemperatureHex, 42, 8);
            $cmdString = substr_replace($cmdString, $minimumTemperatureHex, 50, 4);
        } else if (in_array($presetInfo['device_sub_type'], [3])) {
            $maximumTemperatureHex = ExtraHelper::decHexPad($maximumTemperature, 8);
            $minimumTemperatureHex = ExtraHelper::decHexPad($minimumTemperature, 4);

            //热水费率
            $waterRateHot = $presetInfo['water_rate_hot'];
            //冷水费率
            $waterRateCold = $presetInfo['water_rate_cold'];
            //浮球参数
            $floatBall = $presetInfo['float_ball'];

            $waterRateHotHex = ExtraHelper::decHexPad($waterRateHot, 4);
            $waterRateColdHex = ExtraHelper::decHexPad($waterRateCold, 4);
            $floatBallHex = ExtraHelper::decHexPad($floatBall, 2);

            $cmdString = substr_replace($cmdString, $maximumTemperatureHex, 42, 8);
            $cmdString = substr_replace($cmdString, $minimumTemperatureHex, 50, 4);
            $cmdString = substr_replace($cmdString, $waterRateHotHex, 54, 4);
            $cmdString = substr_replace($cmdString, $waterRateColdHex, 58, 4);
            $cmdString = substr_replace($cmdString, $floatBallHex, 96, 2);
        }

        $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        $cmdString = substr_replace($cmdString, $programCodeHex, 98, 2);


        $eventName = sprintf("SETCONFIG_%s", $deviceId);
        $this->redis()->set($eventName, 1);
        $sendResult = $this->sendDataToDevice($deviceId, $cmdString);
        if (!$sendResult) {
            return false;
        }

        return true;
    }
}