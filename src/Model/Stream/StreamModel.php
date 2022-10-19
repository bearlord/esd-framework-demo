<?php

namespace App\Model\Stream;

use App\Actor\ShouShuiJiActor;
use App\Libs\ExtraHelper;
use App\Model\CustomerMessage;
use App\Model\Device;
use App\Model\DevicePackageLog;
use App\Model\DeviceService;
use App\Model\DeviceShowcase;
use App\Model\DeviceTask;
use App\Model\DeviceUsedWaterLog;
use App\Model\Iccid;
use App\Model\IccidLog;
use App\Model\IccidLogModel;
use App\Model\Manufacturer;
use ESD\Core\Exception;
use ESD\Core\Plugins\Event\Event;
use ESD\Core\Server\Server;
use ESD\Coroutine\Coroutine;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Pack\GetBoostSend;
use ESD\Plugins\Redis\GetRedis;
use ESD\Yii\Base\BaseObject;
use ESD\Yii\Helpers\ArrayHelper;
use ESD\Yii\Helpers\Json;
use ESD\Yii\Yii;

class StreamModel
{
    use GetRedis;
    use GetBoostSend;

    /**
     * @var int Redis缓存时间
     */
    protected $redisTtl = 3600;

    /**
     * @var int 时间捕获过期时间 60秒
     */
    protected $eventDispatcherTtl = 60000;

    /**
     * @var string 原数据
     */
    public $data;

    /**
     * @var int 连接标识
     */
    public $fd;

    /**
     * @var int 协议长度
     */
    protected $protocolLength;

    /**
     * @var array 预设信息
     */
    public $presetInfo;

    /**
     * 设置FD 当前连接
     *
     * @param int $fd
     */
    public function setFd(int $fd)
    {
        $this->fd = $fd;
    }

    /**
     * @return int
     */
    public function getFd(): int
    {
        return $this->fd;
    }

    /**
     * @return int
     */
    public function getProtocolLength(): int
    {
        return $this->protocolLength;
    }

    /**
     * @param int $protocolLength
     */
    public function setProtocolLength(int $protocolLength): void
    {
        $this->protocolLength = $protocolLength;
    }

    /**
     * @return array
     */
    public function getPresetInfo(): array
    {
        return $this->presetInfo;
    }

    /**
     * @param array $presetInfo
     */
    public function setPresetInfo(array $presetInfo): void
    {
        $this->presetInfo = $presetInfo;
    }

    /**
     * 发送 HEX DATA 到设备
     * @param int $fd
     * @param string $hexData
     * @return bool
     * @throws \Exception
     */
    protected function sendHexDataToFd(int $fd, string $hexData): bool
    {
        ExtraHelper::debug($fd, 2, $hexData);

        $deviceId = $ctrl = $deviceState = '';
        if (strlen($hexData) > 8) {
            $deviceId = hexdec(substr($hexData, 0, 8));
            $ctrl = substr($hexData, 10, 2);
            $deviceState = substr($hexData, 12, 2);
        }

        //记录数据库
        $this->logToMySQL($hexData, $fd, 2, $deviceId, $ctrl, $deviceState);
        $this->autoBoostSend($fd, $hexData);
        return true;
    }

    /**
     * 推送websocket消息
     *
     * @param int $deviceId
     * @param array $data
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function pushWSData(int $deviceId, array $data): bool
    {
        $wsKey = ExtraHelper::getWSKey($deviceId);
        $fdIds = $this->redis()->sMembers($wsKey);
        foreach ($fdIds as $fdId) {
            if (Server::$instance->isEstablished($fdId)) {
                $this->autoBoostSend($fdId, $data);
            }
        }
        return true;
    }

    /**
     * 保存回执数据到Redis
     *
     * @param string $eventName
     * @param array $eventData
     * @throws \ESD\Plugins\Redis\RedisException
     */
    protected function saveRedisReceiptData(string $eventName, array $eventData)
    {
        $this->redis()->set($eventName, json_encode($eventData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 25);
    }

    /**
     * 入口程序
     *
     * @param string $data
     * @param int $fd
     * @param int $protocolLength
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \Exception
     */
    public function entry(string $data, int $fd, array $presetInfo): bool
    {
        //设备ID，16进制
        $deviceIdHex = substr($data, 0, 8);
        //设备ID，10进制
        $deviceId = hexdec($deviceIdHex);

        if ($deviceId) {
            $actorName = sprintf("device_%d", $deviceId);
            $deviceActor = Actor::create(ShouShuiJiActor::class, $actorName, [
                'presetInfo' => $presetInfo,
                'fd' => $fd
            ]);
            $deviceActor->processTcpData($data);
        }

        return true;

        //设置fd
        $this->setFd($fd);

        //正常数据包的协议长度
        $this->setPresetInfo($presetInfo);

        //设备ID，16进制
        $deviceIdHex = substr($data, 0, 8);
        //设备ID，10进制
        $deviceId = hexdec($deviceIdHex);

        //小心跳包
        if (strlen($data) === 8) {
            //写入MySQl日志
            $this->logToMySQL($data, $fd, 1);
            $this->execDeviceTask($deviceId, $data, $presetInfo);
            call_user_func_array([$this, 'normalHeartbeatSmall'], [$deviceId, $data]);
            return true;
        }

        //控制
        $ctrl = substr($data, 10, 2);
        //设备状态
        $deviceState = substr($data, 12, 2);

        //存入Redis数据
        if ($ctrl !== 'ee' && $ctrl !== 'aa') {
            $this->saveToRedis($deviceId, $fd, $data);
        }

        //写入MySQl日志
        $this->logToMySQL($data, $fd, 1, $deviceId, $ctrl, $deviceState);

        /**
         * 根据不同的控制操作，调用模型的不同方法
         */
        switch ($ctrl) {
            //正常心跳
            case '00':
                call_user_func_array([$this, 'normalHeartbeatBig'], [$deviceId, $data, $presetInfo]);

                $this->execDeviceTask($deviceId, $data, $presetInfo);
                break;

            //设备ID为空，控制为04，设备状态 06 待激活，则走 请求平台分配ID
            case '04':
                if ($deviceId === 0 && $deviceState === '06') {
                    call_user_func_array([$this, 'requestAssignmentId'], [$data, $presetInfo]);
                }
                break;

            //用水同步
            case '06':
                call_user_func_array([$this, 'usedWaterSync'], [$deviceId, $data, $presetInfo]);
                $this->execDeviceTask($deviceId, $data, $presetInfo);
                break;

            //设备状态变更同步
            case '0c':
                call_user_func_array([$this, 'stateChanged'], [$deviceId, $data, $presetInfo]);
                break;

            //关机指令回执
            case '11':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("shutdownReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '关机',
                    'data' => [
                        'action' => 'shutdown',
                        'data' => $wsData
                    ]
                ]);
                break;

            //开机指令回执
            case '22':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("bootReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '开机',
                    'data' => [
                        'action' => 'boot',
                        'data' => $wsData
                    ]
                ]);
                break;

            //强冲指令回执
            case '33':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("forceFlushReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '强冲',
                    'data' => [
                        'action' => 'systemInit',
                        'data' => $wsData
                    ]
                ]);
                break;

            //设备请求分配ID回执
            case '44':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);
                if (in_array($presetInfo['protocol_length'], [108, 116])) {
                    $resetEventName = sprintf("RESET_%s", $deviceId);
                    $setConfigEventName = sprintf("SETCONFIG_%s", $deviceId);
                    if ($this->redis()->get($setConfigEventName)) {
                        $this->redis()->del($setConfigEventName);
                    } else if ($this->redis()->get($resetEventName)) {
                        $this->redis()->del($resetEventName);
                        $deviceModel = new Device();
                        $manufacturer = new Manufacturer();
                        $deviceInfo = $deviceModel->getItemByDeviceId($deviceId);
                        $manuInfo = $manufacturer->getItemById($deviceInfo['manu_key_id']);
                        $deviceSyncInfo = $deviceInfo;

                        if ($manuInfo['reserved_charge_mode'] == 1) {
                            $deviceSyncInfo['charge_mode'] = $manuInfo['reserved_charge_mode'];
                            $deviceSyncInfo['rest_flow'] = 0;
                            $deviceSyncInfo['used_flow'] = 0;
                            $deviceSyncInfo['rest_time'] = 0;
                            $deviceSyncInfo['used_time'] = 0;
                        }
                        $this->sendBindCommand($deviceId, $presetInfo, $deviceSyncInfo);
                        (new DeviceShowcase())->factoryRecovery($deviceId);

                    } else {
                        $deviceModel = new Device();
                        $manufacturer = new Manufacturer();
                        $deviceInfo = $deviceModel->getItemByDeviceId($deviceId);
                        $manuInfo = $manufacturer->getItemById($deviceInfo['manu_key_id']);
                        $deviceSyncInfo = $deviceInfo;

                        if ($manuInfo['reserved_charge_mode'] == 1) {
                            $deviceSyncInfo['charge_mode'] = $manuInfo['reserved_charge_mode'];
                            $deviceSyncInfo['rest_flow'] = $manuInfo['first_reserved_flow'];
                            $deviceSyncInfo['used_flow'] = 0;
                            $deviceSyncInfo['rest_time'] = 0;
                            $deviceSyncInfo['used_time'] = 0;
                        }
                        $this->sendBindCommand($deviceId, $presetInfo, $deviceSyncInfo);
                    }
                }
                break;

            //充值回执
            case '55':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("rechargeReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '充值',
                    'data' => [
                        'action' => 'recharge',
                        'data' => $wsData
                    ]
                ]);
                break;

            //滤芯复位回执
            case '77':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("filterResetReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '滤芯复位',
                    'data' => [
                        'action' => 'filterReset',
                        'data' => $wsData
                    ]
                ]);
                break;

            //模式切换回执
            case '88':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("switchModeReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '切换模式',
                    'data' => [
                        'action' => 'switchMode',
                        'data' => $wsData
                    ]
                ]);
                break;

            //系统初始化回执
            case '99':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("systemInitReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '初始化',
                    'data' => [
                        'action' => 'systemInit',
                        'data' => $wsData
                    ]
                ]);
                break;

            //恢复出厂设置回执
            //回执数据eId为 00000000，忽略此回执
            case 'aa':
                //do nothing
                break;

            //用时同步回执
            case 'bb':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);
                break;

            //查询指令回执
            case 'dd':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("queryReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '查询',
                    'data' => [
                        'action' => 'query',
                        'data' => $wsData
                    ]
                ]);
                break;

            //查询指令回执，跟普通的数据结构不一样，单独对待
            case 'ee':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("fetchSignalAndICCIDReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);
                break;

            //锁定/解锁回执
            case 'ff':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                $eventName = sprintf("lockReceipt_%s", $deviceId);
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                $this->saveRedisReceiptData($eventName, $eventData);

                $wsData = $eventData;
                $wsData['deviceRealState'] = $wsData['deviceState'];
                if ($wsData['deviceState'] == '07') {
                    $wsData['deviceState'] = '14';
                }
                $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
                $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
                $this->pushWSData($deviceId, [
                    'code' => 200,
                    'message' => '解锁/锁定',
                    'data' => [
                        'action' => 'lock',
                        'data' => $wsData
                    ]
                ]);
                break;

            //保鲜命令回执
            case '03':
            case '30':
                $this->saveReceiptHexData($deviceId, $data, $presetInfo);

                //事件名称
                $eventName = sprintf("freshReceipt_%s", $deviceId);
                //事件派发器
                $eventDispatcher = Server::$instance->getEventDispatcher();
                //事件数据
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                //事件
                $event = new Event($eventName, $eventData);
                //事件全进程派发
                $eventDispatcher->dispatchProcessEvent($event, ...Server::$instance->getProcessManager()->getProcesses());
                break;

            //测试模式回执
            case '9b':
                //事件名称
                $eventName = sprintf("testModeReceipt_%s", $deviceId);
                //事件派发器
                $eventDispatcher = Server::$instance->getEventDispatcher();
                //事件数据
                $eventData = ExtraHelper::filterResponseData(ExtraHelper::hexDataToArray($data, $presetInfo));
                //事件
                $event = new Event($eventName, $eventData);
                //事件全进程派发
                $eventDispatcher->dispatchProcessEvent($event, ...Server::$instance->getProcessManager()->getProcesses());
                break;
        }
        return true;
    }

    /**
     * @param $deviceId
     * @param $data
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function saveReceiptHexData($deviceId, $data, array $presetInfo): bool
    {
        $decData = ExtraHelper::hexDataToArray($data, $presetInfo);
        $currentTime = date("Y-m-d H:i:s");
        $timestamp = time();
        $deviceModel = new Device();

        switch ($decData['cTrl']) {
            case '44':
            case 'aa':
                return true;
                break;

            case '55':
                $decData['deviceRealState'] = $decData['deviceState'];
                if ($decData['deviceState'] == '07') {
                    $decData['deviceState'] = '14';
                }
                $updateData = [
                    'used_time' => $decData['usedTime'],
                    'rest_time' => $decData['restTime'],
                    'used_flow' => $decData['usedFlow'],
                    'rest_flow' => $decData['restFlow'],
                    'device_state' => $decData['deviceState'],
                    'device_real_state' => $decData['deviceRealState'],
                    'raw_tds' => $decData['rawTDS'],
                    'purity_tds' => $decData['purityTDS'],
                    'f1flux' => $decData['F1Flux'],
                    'f2flux' => $decData['F2Flux'],
                    'f3flux' => $decData['F3Flux'],
                    'f4flux' => $decData['F4Flux'],
                    'f5flux' => $decData['F5Flux'],
                    'last_recharge_datetime' => $currentTime
                ];

                $deviceModel->updateDataByDeviceId($updateData, $deviceId);
                break;

            case '77':
            case '99':
                $decData['deviceRealState'] = $decData['deviceState'];
                if ($decData['deviceState'] == '07') {
                    $decData['deviceState'] = '14';
                }
                $updateData = [
                    'charge_mode' => $decData['chargeMode'],
                    'used_time' => $decData['usedTime'],
                    'rest_time' => $decData['restTime'],
                    'used_flow' => $decData['usedFlow'],
                    'rest_flow' => $decData['restFlow'],
                    'device_state' => $decData['deviceState'],
                    'device_real_state' => $decData['deviceRealState'],
                    'raw_tds' => $decData['rawTDS'],
                    'purity_tds' => $decData['purityTDS'],
                    'f1flux' => $decData['F1Flux'],
                    'f2flux' => $decData['F2Flux'],
                    'f3flux' => $decData['F3Flux'],
                    'f4flux' => $decData['F4Flux'],
                    'f5flux' => $decData['F5Flux'],
                    'f1fluxmax' => $decData['F1FluxMax'],
                    'f2fluxmax' => $decData['F2FluxMax'],
                    'f3fluxmax' => $decData['F3FluxMax'],
                    'f4fluxmax' => $decData['F4FluxMax'],
                    'f5fluxmax' => $decData['F5FluxMax']
                ];

                $deviceModel->updateDataByDeviceId($updateData, $deviceId);
                break;

            case '88':
                $updateData = [
                    'charge_mode' => $decData['chargeMode'],
                    'device_state' => $decData['deviceState'],
                ];
                $deviceModel->updateDataByDeviceId($updateData, $deviceId);
                break;

            case 'ee':
                $updateData = [
                    'device_signal' => $decData['signal']
                ];
                $deviceModel->updateDataByDeviceId($updateData, $deviceId);

                //如果ICCID变化了，更新ICCID
                $deviceModel->updateIccidByDeviceId($decData['iccid'], $deviceId);
                break;

            default:
                $decData['deviceRealState'] = $decData['deviceState'];
                if ($decData['deviceState'] == '07') {
                    $decData['deviceState'] = '14';
                }
                $updateData = [
                    'used_time' => $decData['usedTime'],
                    'rest_time' => $decData['restTime'],
                    'used_flow' => $decData['usedFlow'],
                    'rest_flow' => $decData['restFlow'],
                    'device_state' => $decData['deviceState'],
                    'device_real_state' => $decData['deviceRealState'],
                    'raw_tds' => $decData['rawTDS'],
                    'purity_tds' => $decData['purityTDS'],
                    'f1flux' => $decData['F1Flux'],
                    'f2flux' => $decData['F2Flux'],
                    'f3flux' => $decData['F3Flux'],
                    'f4flux' => $decData['F4Flux'],
                    'f5flux' => $decData['F5Flux']
                ];
                $deviceModel->updateDataByDeviceId($updateData, $deviceId);
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param string $data
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \Exception
     */
    public function normalHeartbeatSmall(int $deviceId, $data = "")
    {
        //redis key值
        $deviceKey = ExtraHelper::buildRedisDeviceKey($deviceId);

        //更新updated_at时间戳，避免检测到断网
        $this->redis()->hSet($deviceKey, 'fd', $this->fd);
        $this->redis()->hSet($deviceKey, 'eId', $deviceId);
        $this->redis()->hSet($deviceKey, 'updated_at', time());

        //更改设备状态
        $deviceModel = new Device();
        $data = [];
        $deviceModel->updateDataByDeviceId($data, $deviceId);
    }

    /**
     * @param int $deviceId
     * @param string $data
     * @param int $protocolLength
     * @return bool
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function normalHeartbeatBig(int $deviceId, string $data, array $presetInfo): bool
    {
        $protocolLength = $presetInfo['protocol_length'];

        $decData = ExtraHelper::hexDataToArray($data, $presetInfo);

        $deviceModel = new Device();

        //数据库保存的设备信息
        $deviceInfo = $deviceModel->getItemByDeviceId($deviceId);
        if (empty($deviceInfo)) {
            return true;
        }

        //同步时间
        $syncTime = (int)$deviceInfo['sync_time'];
        //现在时间戳
        $currentTimestamp = time();

        switch ($deviceInfo['device_exist']) {
            //离线解绑
            case 'no':
                $this->sendUnbindCommand($deviceId, $protocolLength);
                return true;
                break;

            case 'yes':
                //离线绑定
                if ($decData['deviceState'] === '06') {
                    $this->sendBindCommand($deviceId, $presetInfo, $deviceInfo);
                    return true;
                }

                //用时同步
                if (($currentTimestamp - $syncTime) >= 86400) {
                    $this->sendUsedWaterSyncCommand($deviceId, $protocolLength, $currentTimestamp, $deviceInfo, $decData);
                    return true;
                }
                break;
        }

        //更改设备状态
        $data = [
            'rest_flow' => $decData['restFlow'],
            'rest_time' => $decData['restTime'],
            'used_flow' => $decData['usedFlow'],
            'used_time' => $decData['usedTime'],
            'purity_tds' => $decData['purityTDS'],
            'raw_tds' => $decData['rawTDS'],
            'f1flux' => $decData['F1Flux'],
            'f2flux' => $decData['F2Flux'],
            'f3flux' => $decData['F3Flux'],
            'f4flux' => $decData['F4Flux'],
            'f5flux' => $decData['F5Flux'],
            'net_state' => 1,
            'sync_time' => $syncTime
        ];

        if (isset($decData['signal'])) {
            $data['device_signal'] = $decData['signal'];
            $data['ICCID'] = $decData['iccid'];

            if ($decData['iccid'] != '00000000000000000000'
                && !empty($deviceInfo['ICCID'])
                && ($deviceInfo['ICCID'] != $decData['iccid'])
            ) {
                (new IccidLog())->recordLog($deviceId, $deviceInfo['ICCID'], $decData['iccid']);
            }
        }

        $deviceModel->updateDataByDeviceId($data, $deviceId);


        $wsData = $decData;
        $wsData['deviceRealState'] = $wsData['deviceState'];
        if ($wsData['deviceState'] == '07') {
            $wsData['deviceState'] = '14';
        }
        $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
        $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);
        $this->pushWSData($deviceId, [
            'code' => 200,
            'message' => '设备联网成功',
            'data' => [
                'action' => 'heartbeat',
                'data' => $wsData
            ]
        ]);

        return true;
    }

    /**
     * @param int $deviceId
     * @param int $protocolLength
     * @return bool
     * @throws \Exception
     */
    protected function sendUnbindCommand(int $deviceId, int $protocolLength): bool
    {
        $deviceModel = new Device();
        $deviceModel->updateDataByDeviceId([
            'dealer_id' => '',
//            'device_model_id' => '',
//            'charge_mode' => '',
            'device_province' => '',
            'device_city' => '',
            'device_district' => '',
            'device_address' => '',
            //设备待激活状态
            'device_state' => '02',
            'this_flow' => 0,
            'rest_flow' => 0,
            'used_flow' => 0,
            'used_time' => 0,
            'purity_tds' => 0,
            'raw_tds' => 0,
            'net_state' => 0,
            'f1flux' => 0,
            'f2flux' => 0,
            'f3flux' => 0,
            'f4flux' => 0,
            'f5flux' => 0,
            'f1fluxmax' => 0,
            'f2fluxmax' => 0,
            'f3fluxmax' => 0,
            'f4fluxmax' => 0,
            'f5fluxmax' => 0,
            'last_recharge_datetime' => '',
            'activation_time' => '',
            'activation_latitude' => '',
            'activation_longitude' => '',
            //解除关联
            'device_exist' => 'unbind',
            'last_recharge_time' => 0,
            'last_recharge_flow' => 0,
            'device_hash' => '',
        ], $deviceId);

        //离线解绑
        //设备ID，16进制，8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);
        //控制命令
        $ctrlHex = '0a';

        switch ($protocolLength) {
            case 90:
                $cmdString = str_repeat(0, $protocolLength);
                //替换设备ID部分
                $cmdString = substr_replace($cmdString, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;

            case 104:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdString = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdString, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;

            case 116:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
                break;
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param int $protocolLength
     * @param array $deviceSyncInfo
     * @return bool
     * @throws \Exception
     */
    protected function sendBindCommand(int $deviceId, array $presetInfo, array $deviceSyncInfo): bool
    {
        //协议长度
        $protocolLength = $presetInfo['protocol_length'];
        //设备ID，16进制，8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);
        //控制命令
        $ctrlHex = '09';

        //addFlow
        $addFlow = 0;
        $addFlowHex = ExtraHelper::decHexPad($addFlow, 4);
        //addTime
        $addTime = 0;
        $addTimeHex = ExtraHelper::hexDecPad($addTime, 4);

        //计费模式Hex
        $chargeModeHex = ExtraHelper::decHexPad($deviceSyncInfo['charge_mode'], 2);
        //剩余流量Hex
        $restFlowHex = ExtraHelper::decHexPad($deviceSyncInfo['rest_flow'], 4);
        //剩余时长Hex
        $restTimeHex = ExtraHelper::decHexPad($deviceSyncInfo['rest_time'], 4);
        //已用流量
        $usedFlowHex = ExtraHelper::decHexPad($deviceSyncInfo['used_flow'], 4);
        //已用时长
        $usedTimeHex = ExtraHelper::decHexPad($deviceSyncInfo['used_time'], 4);

        //滤芯总寿命Hex
        $F1FluxMaxHex = ExtraHelper::decHexPad($deviceSyncInfo['f1fluxmax'], 4);
        $F2FluxMaxHex = ExtraHelper::decHexPad($deviceSyncInfo['f2fluxmax'], 4);
        $F3FluxMaxHex = ExtraHelper::decHexPad($deviceSyncInfo['f3fluxmax'], 4);
        $F4FluxMaxHex = ExtraHelper::decHexPad($deviceSyncInfo['f4fluxmax'], 4);
        $F5FluxMaxHex = ExtraHelper::decHexPad($deviceSyncInfo['f5fluxmax'], 4);

        //滤芯剩余寿命Hex
        $F1FluxHex = ExtraHelper::decHexPad($deviceSyncInfo['f1fluxmax'], 4);
        $F2FluxHex = ExtraHelper::decHexPad($deviceSyncInfo['f2fluxmax'], 4);
        $F3FluxHex = ExtraHelper::decHexPad($deviceSyncInfo['f3fluxmax'], 4);
        $F4FluxHex = ExtraHelper::decHexPad($deviceSyncInfo['f4fluxmax'], 4);
        $F5FluxHex = ExtraHelper::decHexPad($deviceSyncInfo['f5fluxmax'], 4);

        switch ($protocolLength) {
            case 90:
                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $addFlowHex, 18, 4);
                $cmdString = substr_replace($cmdString, $addTimeHex, 22, 4);
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

                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;

            case 104:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $addFlowHex, 18, 4);
                $cmdString = substr_replace($cmdString, $addTimeHex, 22, 4);
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
                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;

            case 116:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                //剩余流量Hex
                $restFlowHex = ExtraHelper::decHexPad($deviceSyncInfo['rest_flow'], 8);
                //已用流量
                $usedFlowHex = ExtraHelper::decHexPad($deviceSyncInfo['used_flow'], 8);

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

                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;
        }
        return true;
    }

    /**
     * @param int $deviceId
     * @param int $protocolLength
     * @param int $syncTimestamp
     * @param array $deviceInfo
     * @return bool
     * @throws \Exception
     */
    protected function sendUsedWaterSyncCommand(int $deviceId, int $protocolLength, int $syncTimestamp, array $deviceInfo, array $decData): bool
    {
        //从激活日算起到今天的天数
        $usedTime = round(($syncTimestamp - strtotime($deviceInfo['activation_time'])) / 86400);
        //避免round bug
        $usedTime = intval($usedTime);

        $restTime = $deviceInfo['rest_time'] + $deviceInfo['used_time'] - $usedTime;
        if ($restTime <= 0) {
            $restTime = 0;
        }

        //避免round bug
        $restTime = intval($restTime);
        if ($restTime <= 0) {
            $restTime = 0;
        }

        //设备ID，16进制，8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);
        //控制命令
        $ctrlHex = '0b';

        //addFlow字段对应热水温度，默认98
        $addFlow = 0;
        $addFlowHex = ExtraHelper::decHexPad($addFlow, 4);
        //addTime字段对应冷水温度，默认13
        $addTime = 0;
        $addTimeHex = ExtraHelper::hexDecPad($addTime, 4);

        //计费模式Hex
        $chargeModeHex = ExtraHelper::decHexPad($deviceInfo['charge_mode'], 2);
        //剩余流量Hex
        $restFlowHex = ExtraHelper::decHexPad($deviceInfo['rest_flow'], 4);
        //剩余时长Hex
        $restTimeHex = ExtraHelper::decHexPad($restTime, 4);
        //已用流量
        $usedFlowHex = ExtraHelper::decHexPad($deviceInfo['used_flow'], 4);
        //已用时长
        $usedTimeHex = ExtraHelper::decHexPad($usedTime, 4);

        //滤芯总寿命Hex
        $F1FluxMaxHex = ExtraHelper::decHexPad($deviceInfo['f1fluxmax'], 4);
        $F2FluxMaxHex = ExtraHelper::decHexPad($deviceInfo['f2fluxmax'], 4);
        $F3FluxMaxHex = ExtraHelper::decHexPad($deviceInfo['f3fluxmax'], 4);
        $F4FluxMaxHex = ExtraHelper::decHexPad($deviceInfo['f4fluxmax'], 4);
        $F5FluxMaxHex = ExtraHelper::decHexPad($deviceInfo['f5fluxmax'], 4);

        //滤芯剩余寿命Hex
        $F1FluxHex = ExtraHelper::decHexPad($deviceInfo['f1fluxmax'], 4);
        $F2FluxHex = ExtraHelper::decHexPad($deviceInfo['f2fluxmax'], 4);
        $F3FluxHex = ExtraHelper::decHexPad($deviceInfo['f3fluxmax'], 4);
        $F4FluxHex = ExtraHelper::decHexPad($deviceInfo['f4fluxmax'], 4);
        $F5FluxHex = ExtraHelper::decHexPad($deviceInfo['f5fluxmax'], 4);

        switch ($protocolLength) {
            case 90:
                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $addFlowHex, 18, 4);
                $cmdString = substr_replace($cmdString, $addTimeHex, 22, 4);
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

                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;

            case 104:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $addFlowHex, 18, 4);
                $cmdString = substr_replace($cmdString, $addTimeHex, 22, 4);
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
                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;
        }
        $deviceModel = new Device();
        $purityTDS = $decData['purityTDS'];
        $rawTDS = $decData['rawTDS'];

        //更改设备状态
        $data = [
            'rest_flow' => $decData['restFlow'],
            'rest_time' => $decData['restTime'],
            'used_flow' => $decData['usedFlow'],
            'used_time' => $decData['usedTime'],
            'purity_tds' => $purityTDS,
            'raw_tds' => $rawTDS,
            'f1flux' => $decData['F1Flux'],
            'f2flux' => $decData['F2Flux'],
            'f3flux' => $decData['F3Flux'],
            'f4flux' => $decData['F4Flux'],
            'f5flux' => $decData['F5Flux'],
            'net_state' => 1,
            'sync_time' => $syncTimestamp
        ];
        $deviceModel->updateDataByDeviceId($data, $deviceId);

        return true;
    }

    /**
     * 发送查询Iccid命令
     *
     * @throws \Exception
     */
    protected function sendIccidCommand(int $deviceId, int $protocolLength)
    {
        //设备ID，16进制，8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);
        //控制命令
        $ctrlHex = '0e';

        switch ($protocolLength) {
            case 90:
                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);

                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;

            case 104:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);

                $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;
            case 116:
                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);
                $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
                $this->sendHexDataToFd($this->getFd(), $cmdString);
                break;
                break;
        }
    }

    /**
     * 设备请求分配ID
     * @param $data
     * @param array $presetInfo
     * @return bool
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function requestAssignmentId($data, array $presetInfo): bool
    {
        $dataLength = strlen($data);
        switch ($dataLength) {
            case 116:
                //信号强度
                $signal = hexdec(substr($data, 80, 2));
                //ICCID
                $iccid = substr($data, 82, 20);
                //Imei
                $imei = substr($data, 64, 15);
                break;

            case 90:
            case 104:
            default:
                //信号强度
                $signal = hexdec(substr($data, 68, 2));
                //ICCID
                $iccid = substr($data, 70, 20);
                //Imei
                $imei = substr($data, 52, 15);
                break;
        }

        $iccidModel = new Iccid();
        $deviceModel = new Device();
        $iccidLogModel = new IccidLog();

        if (!$presetInfo) {
            return false;
        }

        //预设设备ID
        $presetDeviceId = $presetInfo['preset_device_id'];
        //协议长度
        $protocolLength = (int)$presetInfo['protocol_length'];

        switch ($presetInfo['dependency_type']) {
            case 1:
                //是否需要获取IMEI
                if ($presetInfo['fetch_imei']) {
                    if ($presetInfo['IMEI'] != $imei) {
                        $iccidModel->updateImei($presetInfo['id'], $imei);
                    }
                }
                break;

            case 2:
                if ($presetInfo['ICCID'] != $iccid) {
                    $iccidModel->updateIccid($presetInfo['id'], $iccid);

                    $deviceModel->updateDataByDeviceId([
                        'ICCID' => $iccid
                    ], $presetDeviceId);

                    if (!empty($presetInfo['ICCID'])) {
                        $iccidLogModel->recordLog($presetDeviceId, $presetInfo['ICCID'], $iccid);
                    }
                }
                break;
        }

        //是否需要获取IMEI
        if ($presetInfo['fetch_imei']) {
            //ICCID更新
            $iccidModel->updateIccid($presetInfo['id'], $iccid);
            $deviceModel->updateDataByDeviceId([
                'ICCID' => $iccid
            ], $presetDeviceId);
        }

        switch ($protocolLength) {
            case 90:
                //本次流量，用来判断滤芯是时长还是流量计费
                $thisFlow = substr($this->data, 14, 4);
                //滤芯计算方式, 鹤庭滤芯按流量计算
                $filterChargeMode = ($thisFlow === '0000') ? 1 : 0;
                //设备ID，16进制，8位
                $deviceIdHex = ExtraHelper::decHexPad($presetDeviceId, 8);
                //控制命令
                $ctrlHex = '04';
                $sendData = str_repeat(0, $protocolLength);
                //替换设备ID部分
                $sendData = substr_replace($sendData, $deviceIdHex, 0, 8);
                $sendData = substr_replace($sendData, $ctrlHex, 10, 2);
                $this->sendHexDataToFd($this->fd, $sendData);

                //更新设备数据
                $deviceModel->updateDataByDeviceId([
                    'device_signal' => $signal,
                    'filter_charge_mode' => $filterChargeMode,
                    'device_state' => '02'
                ], $presetDeviceId);
                break;

            case 104:
                //滤芯计费模式
                $filterChargeMode = $presetInfo['filter_charge_mode'];
                //流量计脉冲
                $flowmeter = (int)$presetInfo['flowmeter'];
                //水龙头
                $faucet = (int)$presetInfo['faucet'];
                //水泵
                $waterPump = (int)$presetInfo['water_pump'];
                //维修时间
                $maintenance = (int)$presetInfo['maintenance'];

                //秒数
                $seconds = ExtraHelper::secondsFromZero();

                //设备ID16进制
                $deviceIdHex = ExtraHelper::decHexPad($presetDeviceId, 8);
                //控制命令
                $ctrlHex = '04';

                $filterChargeModeHex = ExtraHelper::decHexPad($filterChargeMode, 2);
                $flowmeterHex = ExtraHelper::decHexPad($flowmeter, 4);
                $faucetHex = ExtraHelper::decHexPad($faucet, 4);
                $waterPumpHex = ExtraHelper::decHexPad($waterPump, 4);
                $maintenanceHex = ExtraHelper::decHexPad($maintenance, 4);
                $secondsHex = ExtraHelper::decHexPad($seconds, 8);

                $sendData = str_repeat(0, $protocolLength);
                $sendData = substr_replace($sendData, $deviceIdHex, 0, 8);
                $sendData = substr_replace($sendData, $ctrlHex, 10, 2);
                $sendData = substr_replace($sendData, $filterChargeModeHex, 12, 2);
                $sendData = substr_replace($sendData, $flowmeterHex, 18, 4);
                $sendData = substr_replace($sendData, $faucetHex, 22, 4);
                $sendData = substr_replace($sendData, $waterPumpHex, 26, 4);
                $sendData = substr_replace($sendData, $maintenanceHex, 30, 4);

                $sendData = substr_replace($sendData, $secondsHex, 90, 8);
                $sendData = substr_replace($sendData, '0000', 100, 4);

                $this->sendHexDataToFd($this->getFd(), $sendData);

                //更新设备数据
                $deviceModel->updateDataByDeviceId([
                    'device_signal' => $signal,
                    'filter_charge_mode' => $filterChargeMode,
                    'flowmeter' => $flowmeter,
                    'faucet' => $faucet,
                    'water_pump' => $waterPump,
                    'maintenance' => $maintenance,
                    'device_state' => '02'
                ], $presetDeviceId);

                break;

            case 116:
                //滤芯计费模式
                $filterChargeMode = $presetInfo['filter_charge_mode'];
                //流量计脉冲
                $flowmeter = $presetInfo['flowmeter'];
                //水龙头, 冷水阀放水一升所需的时间
                $faucet = $presetInfo['faucet'];
                //水泵
                $waterPump = $presetInfo['water_pump'];
                //维修时间
                $maintenance = $presetInfo['maintenance'];
                //最高温度
                $maximumTemperature = $presetInfo['maximum_temperature'];
                //最低温度
                $minimumTemperature = $presetInfo['minimum_temperature'];
                //热水流量脉冲
                $flowmeterHot = $presetInfo['flowmeter_hot'];
                //热水阀放水一升所需的时间
                $faucetHot = $presetInfo['faucet_hot'];
                //(单次/每天)消费最大放水量
                $maximumWaterAmount = $presetInfo['maximum_water_amount'];

                //秒数
                $seconds = ExtraHelper::secondsFromZero();

                //设备ID16进制
                $deviceIdHex = ExtraHelper::decHexPad($presetDeviceId, 8);
                //控制命令
                $ctrlHex = '04';

                $filterChargeModeHex = ExtraHelper::decHexPad($filterChargeMode, 2);
                $flowmeterHex = ExtraHelper::decHexPad($flowmeter, 8);
                $faucetHex = ExtraHelper::decHexPad($faucet, 4);
                $waterPumpHex = ExtraHelper::decHexPad($waterPump, 8);
                $maintenanceHex = ExtraHelper::decHexPad($maintenance, 4);
                $flowmeterHotHex = ExtraHelper::decHexPad($flowmeterHot, 4);
                $faucetHotHex = ExtraHelper::decHexPad($faucetHot, 4);
                $maximumWaterAmountHex = ExtraHelper::decHexPad($maximumWaterAmount, 4);
                $secondsHex = ExtraHelper::decHexPad($seconds, 8);

                $sendData = str_repeat(0, $protocolLength);
                $sendData = substr_replace($sendData, $deviceIdHex, 0, 8);
                $sendData = substr_replace($sendData, $ctrlHex, 10, 2);
                $sendData = substr_replace($sendData, $filterChargeModeHex, 12, 2);
                $sendData = substr_replace($sendData, $flowmeterHex, 18, 8);
                $sendData = substr_replace($sendData, $faucetHex, 26, 4);
                $sendData = substr_replace($sendData, $waterPumpHex, 30, 8);
                $sendData = substr_replace($sendData, $maintenanceHex, 38, 4);
                $sendData = substr_replace($sendData, $maximumWaterAmountHex, 66, 4);
                $sendData = substr_replace($sendData, $flowmeterHotHex, 70, 4);
                $sendData = substr_replace($sendData, $faucetHotHex, 86, 4);

                //如果冷热水分别计算
                if (in_array($presetInfo['device_sub_type'], [1, 2])) {
                    $maximumTemperatureHex = ExtraHelper::decHexPad($maximumTemperature, 8);
                    $minimumTemperatureHex = ExtraHelper::decHexPad($minimumTemperature, 4);
                    $sendData = substr_replace($sendData, $maximumTemperatureHex, 42, 8);
                    $sendData = substr_replace($sendData, $minimumTemperatureHex, 50, 4);
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

                    $sendData = substr_replace($sendData, $maximumTemperatureHex, 42, 8);
                    $sendData = substr_replace($sendData, $minimumTemperatureHex, 50, 4);
                    $sendData = substr_replace($sendData, $waterRateHotHex, 54, 4);
                    $sendData = substr_replace($sendData, $waterRateColdHex, 58, 4);
                    $sendData = substr_replace($sendData, $floatBallHex, 96, 2);
                }

                $sendData = substr_replace($sendData, $secondsHex, 102, 8);
                $this->sendHexDataToFd($this->fd, $sendData);

                //更新设备数据
                $deviceModel->updateDataByDeviceId([
                    'device_signal' => $signal,
                    'filter_charge_mode' => $filterChargeMode,
                    'flowmeter' => $flowmeter,
                    'faucet' => $faucet,
                    'water_pump' => $waterPump,
                    'maintenance' => $maintenance,
                    'maximum_temperature' => $maximumTemperature,
                    'minimum_temperature' => $minimumTemperature,
                    'device_state' => '02'
                ], $presetDeviceId);
                break;

        }
        return true;
    }

    /**
     * 设备推送 用水同步
     * 数据库存10进制数据
     * @param $deviceId
     * @param $data
     * @param array $presetInfo
     * @return bool
     * @throws \Exception
     */
    public function usedWaterSync($deviceId, $data, array $presetInfo): bool
    {
        $decData = ExtraHelper::hexDataToArray($data, $presetInfo);

        $deviceModel = new Device();
        $deviceUsedWaterLogModel = new DeviceUsedWaterLog();

        $currentTime = date("Y-m-d H:i:s");
        $currentTimestamp = time();

        switch ($presetInfo['device_sub_type']) {
            case 10:
                $deviceModel->updateDataByDeviceId([
                    'this_flow' => $decData['thisFlow'],
                    'rest_flow' => $decData['restFlow'],
                    'rest_time' => $decData['restTime'],
                    'used_flow' => $decData['usedFlow'],
                    'used_time' => $decData['usedTime'],
                    'purity_tds' => $decData['purityTDS'],
                    'f1flux' => 0,
                    'f2flux' => 0,
                    'f3flux' => 0,
                    'f4flux' => 0,
                    'f5flux' => 0,
                    'F1FluxMax' => 0,
                    'F2FluxMax' => 0,
                    'F3FluxMax' => 0,
                    'F4FluxMax' => 0,
                    'F5FluxMax' => 0,
                    'voltage' => $decData['voltage'],
                    'device_signal' => $decData['signal'],
                    'device_state' => $decData['deviceState'],
                    'net_state' => 1
                ], $deviceId);

                $usedWaterData = [
                    'device_id' => $deviceId,
                    'used_flow' => $decData['thisFlow'],
                    'rest_flow' => $decData['restFlow'],
                    'purity_tds' => $decData['purityTDS'],
                    'raw_tds' => 0,
                    'event_time' => $currentTime,
                    'event_timestamp' => $currentTimestamp,
                ];
                $showcaseInfo = (new DeviceShowcase())->getItemByDeviceId($deviceId);
                if (!empty($showcaseInfo)) {
                    $usedWaterData['manu_pk_id'] = $showcaseInfo['manu_pk_id'];
                    $usedWaterData['dealer_pk_id'] = $showcaseInfo['dealer_pk_id'];
                    $usedWaterData['project_pk_id'] = $showcaseInfo['project_pk_id'];
                    $usedWaterData['showcase_sn'] = $showcaseInfo['showcase_sn'];
                }

                $deviceUsedWaterLogModel->createItem($usedWaterData);
                break;
            case 1:
            case 2:
            case 3:
                //todo
                break;

            case 0:
            default:
                $deviceModel->updateDataByDeviceId([
                    'this_flow' => $decData['thisFlow'],
                    'purity_tds' => $decData['purityTDS'],
                    'raw_tds' => $decData['rawTDS'],
                    'f1flux' => $decData['F1Flux'],
                    'f2flux' => $decData['F2Flux'],
                    'f3flux' => $decData['F3Flux'],
                    'f4flux' => $decData['F4Flux'],
                    'f5flux' => $decData['F5Flux'],
                    'F1FluxMax' => $decData['F1FluxMax'],
                    'F2FluxMax' => $decData['F2FluxMax'],
                    'F3FluxMax' => $decData['F3FluxMax'],
                    'F4FluxMax' => $decData['F4FluxMax'],
                    'F5FluxMax' => $decData['F5FluxMax'],
                    'device_state' => $decData['deviceState'],
                    'net_state' => 1
                ], $deviceId);

                //2.插入设备用水日志
                $usedWaterData = [
                    'device_id' => $deviceId,
                    'used_flow' => $decData['thisFlow'],
                    'rest_flow' => $decData['restFlow'],
                    'purity_tds' => $decData['purityTDS'],
                    'raw_tds' => $decData['rawTDS'],
                    'event_time' => $currentTime,
                    'event_timestamp' => $currentTimestamp,
                ];

                $showcaseInfo = (new DeviceShowcase())->getItemByDeviceId($deviceId);
                if (!empty($showcaseInfo)) {
                    $usedWaterData['manu_pk_id'] = $showcaseInfo['manu_pk_id'];
                    $usedWaterData['dealer_pk_id'] = $showcaseInfo['dealer_pk_id'];
                    $usedWaterData['project_pk_id'] = $showcaseInfo['project_pk_id'];
                    $usedWaterData['showcase_sn'] = $showcaseInfo['showcase_sn'];
                }
                $deviceUsedWaterLogModel->createItem($usedWaterData);


                break;
        }

        $wsData = ExtraHelper::filterResponseData($decData);
        $wsData['deviceRealState'] = $wsData['deviceState'];
        if ($wsData['deviceState'] == '07') {
            $wsData['deviceState'] = '14';
        }
        $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
        $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);

        $this->pushWSData($deviceId, [
            'code' => 200,
            'message' => '用水消费',
            'data' => [
                'action' => 'usedWaterSync',
                'data' => $wsData
            ]
        ]);
        return true;

    }

    /**
     * @param int $deviceId
     * @param string $data
     * @param array $presetInfo
     * @return bool
     * @throws \Exception
     */
    public function stateChanged(int $deviceId, string $data, array $presetInfo): bool
    {
        $decData = ExtraHelper::hexDataToArray($data, $presetInfo);

        //只上传四种状态
        if (in_array($decData['deviceState'], ['01', '02', '03', '07', '14']) === false) {
            return false;
        }

        $wsData = ExtraHelper::filterResponseData($decData);
        $wsData['deviceRealState'] = $wsData['deviceState'];
        if ($wsData['deviceState'] == '07') {
            $wsData['deviceState'] = '14';
        }
        $wsData['deviceStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceState']);
        $wsData['deviceRealStateCN'] = (new Device())->getPrettyDeviceState($wsData['deviceRealState']);

        $deviceModel = new Device();
        //数据库更改
        $deviceModel->updateDataByDeviceId([
            'rest_flow' => $wsData['restFlow'],
            'rest_time' => $wsData['restTime'],
            'used_flow' => $wsData['usedFlow'],
            'used_time' => $wsData['usedTime'],
            'purity_tds' => $wsData['purityTDS'],
            'raw_tds' => $wsData['rawTDS'],
            'f1flux' => $wsData['F1Flux'],
            'f2flux' => $wsData['F2Flux'],
            'f3flux' => $wsData['F3Flux'],
            'f4flux' => $wsData['F4Flux'],
            'f5flux' => $wsData['F5Flux'],
            'device_state' => ($wsData['deviceState'] == '07') ? '14' : $wsData['deviceState'],
            'device_real_state' => $wsData['deviceRealState'],
            'net_state' => 1
        ], $deviceId);


        $this->pushWSData($deviceId, [
            'code' => 200,
            'message' => '设备状态改变',
            'data' => [
                'action' => 'stateChanged',
                'data' => $wsData
            ]
        ]);

        return true;
    }

    /**
     * 处理信号等级
     * @param int $signal
     * @return false|float|int
     */
    public function processSignalLevel(int $signal)
    {
        //总分
        $fullMarks = 32;
        $level = ceil($signal / $fullMarks * 4);
        if ($level > 4) {
            $level = 4;
        }
        return $level;
    }

    /**
     * 写入Redis
     *
     * @param int $deviceId
     * @param int $fd
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function saveToRedis(int $deviceId, int $fd)
    {
        $connKey = ExtraHelper::buildRedisTcpKey($deviceId);
        if ($deviceId !== '00000000') {
            $this->redis()->set($connKey, $deviceId);
        }

        $deviceKey = ExtraHelper::buildRedisDeviceKey($deviceId);

        //设备信息写入Redis
        $this->redis()->hMset($deviceKey, [
            'fd' => $fd,
            'eId' => $deviceId,
            'updated_at' => time()
        ]);
    }

    /**
     * 写入MySQL日志
     *
     * @param string $stream
     * @param int $fd
     * @param int $way
     * @param string $deviceId
     * @param string $ctrl
     * @param string $deviceState
     * @throws \Exception
     */
    public function logToMySQL(string $stream, int $fd, int $way = 1, string $deviceId = "", string $ctrl = "", string $deviceState = "")
    {
        if (empty($deviceId)) {
            $deviceId = ExtraHelper::hexDecPad(substr($stream, 0, 8), 8);
        }

        $data['device_id'] = $deviceId;
        $data['ctrl'] = $ctrl;
        $data['device_state'] = $deviceState;
        $data['data'] = $stream;
        $data['way'] = $way;

        $currentUnixTimestamp = time();
        if (!empty($fd)) {
            $clientInfo = Server::$instance->getClientInfo($fd);
            $data['fd'] = $fd;
            $data['remote_ip'] = $clientInfo->getRemoteIp();
            $data['remote_port'] = $clientInfo->getRemotePort();
            $data['created_time'] = date("Y-m-d H:i:s");
            $data['created_at'] = $currentUnixTimestamp;
            $data['updated_at'] = $currentUnixTimestamp;
        }

        $tableName = DevicePackageLog::tableName();

        Yii::$app->getDb()
            ->createCommand()
            ->insert($tableName, $data)
            ->execute();
    }

    /**
     * @param int $deviceId
     * @param string $data
     * @param int $protocolLength
     * @throws \Exception
     */
    public function tempUnbind(int $deviceId, string $data, int $protocolLength)
    {
        //设备ID，16进制，8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);
        //控制命令
        $ctrlHex = '0a';

        //Seconds from zero
        $secondsFromZero = ExtraHelper::secondsFromZero();
        $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

        $cmdTemplate = str_repeat(0, $protocolLength);
        $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
        $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);

        $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
        $this->sendHexDataToFd($this->getFd(), $cmdString);
    }

    /**
     * @throws \ESD\Yii\Db\Exception
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \Exception
     */
    public function execTask($deviceId, $presetInfo)
    {
        $list = (new DeviceTask())->getTodoListByDeviceId($deviceId);
        if (empty($list)) {
            return false;
        }

        //开始时间戳
        $fromTime = $list[0]['created_time'];

        $filterList = [];

        $indexList = ArrayHelper::index($list, null, 'action');

        $bootList = !empty($indexList['boot']) ? $indexList['boot'] : [];
        $bootListCount = count($bootList);

        $shutdownList = !empty($indexList['shutdown']) ? $indexList['shutdown'] : [];
        $shutdownListCount = count($shutdownList);

        if ($bootListCount > $shutdownListCount) {
            $filterList[100] = [
                'device_id' => $deviceId,
                'action' => 'boot',
                'args' => []
            ];
        } else if ($bootListCount < $shutdownListCount) {
            $filterList[101] = [
                'device_id' => $deviceId,
                'action' => 'shutdown',
                'args' => []
            ];
        } else {
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 3,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "action IN ('boot', 'shutdown') AND created_time >= :created_time", [
                    ":created_time" => $fromTime
                ])
                ->execute();
        }

        $setConfigList = !empty($indexList['setConfig']) ? $indexList['setConfig'] : [];
        $setConfigListCount = count($setConfigList);
        if (!empty($setConfigList)) {
            $_setConfig = $setConfigList[$setConfigListCount - 1];
            $_params = Json::decode($_setConfig['params'], true);

            $filterList[80] = [
                'device_id' => $deviceId,
                'action' => 'setConfig',
                'args' => $_params
            ];
        }


        $rechargeList = !empty($indexList['recharge']) ? $indexList['recharge'] : [];
        if (!empty($rechargeList)) {
            $addFlows = 0;
            foreach ($rechargeList as $key => $value) {
                $_params = Json::decode($value['params']);
                $addFlows += $_params['addFlow'];
            }
            $filterList[50] = [
                'device_id' => $deviceId,
                'action' => 'recharge',
                'args' => [
                    'addFlow' => $addFlows
                ]
            ];
        }

        $systemInitList = !empty($indexList['systemInit']) ? $indexList['systemInit'] : [];
        $systemInitListCount = count($systemInitList);
        if (!empty($systemInitList)) {
            $_systemInit = $systemInitList[$systemInitListCount - 1];
            $_params = Json::decode($_systemInit['params'], true);
            $filterList[20] = [
                'device_id' => $deviceId,
                'action' => 'systemInit',
                'args' => $_params
            ];
        }

        $resetList = !empty($indexList['reset']) ? $indexList['reset'] : [];
        $resetListCount = count($resetList);
        if ($resetList) {
            $_reset = $resetList[$resetListCount - 1];
            $filterList[10] = [
                'device_id' => $deviceId,
                'action' => 'reset',
                'args' => [],
                'endpoint_time' => $_reset['created_time']
            ];
            if (!empty($filterList[50])) {
                unset($filterList[50]);
            }
            if (!empty($filterList[100])) {
                unset($filterList[100]);
            }
            if (!empty($filterList[101])) {
                unset($filterList[101]);
            }
        }

        $asyncDeviceService = new AsyncDeviceService();
        if (!empty($filterList[10])) {
            $asyncDeviceService->reset($deviceId, $filterList[10]['args'], $presetInfo);
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 2,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action = 'reset' AND created_time >= :created_time AND created_time <= :endpoint_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime,
                    ":endpoint_time" => $filterList[10]['endpoint_time']
                ])
                ->execute();

            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 3,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action != 'reset' AND created_time >= :created_time AND created_time <= :endpoint_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime,
                    ":endpoint_time" => $filterList[10]['endpoint_time']
                ])
                ->execute();

            return true;
        }

        if (!empty($filterList[20])) {
            $asyncDeviceService->systemInit($deviceId, $filterList[20]['args'], $presetInfo);
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 2,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action = 'systemInit' AND created_time >= :created_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime
                ])
                ->execute();

            Coroutine::sleep(1);
        }

        if (!empty($filterList[50])) {
            $asyncDeviceService->recharge($deviceId, $filterList[50]['args'], $presetInfo);
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 2,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action = 'recharge' AND created_time >= :created_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime
                ])
                ->execute();
            Coroutine::sleep(1);
        }

        if (!empty($filterList[80])) {
            $asyncDeviceService->setConfig($deviceId, $filterList[80]['args'], $presetInfo);
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 2,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action = 'setConfig' AND created_time >= :created_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime
                ])
                ->execute();
            Coroutine::sleep(1);
        }

        if (!empty($filterList[100])) {
            $asyncDeviceService->boot($deviceId, $filterList[100]['args'], $presetInfo);
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 2,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action IN ('boot', 'shutdown') AND created_time >= :created_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime
                ])
                ->execute();
        }

        if (!empty($filterList[101])) {
            $asyncDeviceService->shutdown($deviceId, $filterList[101]['args'], $presetInfo);
            Yii::$app->getDb()->createCommand()
                ->update(DeviceTask::tableName(), [
                    'exec_state' => 2,
                    'exec_time' => date("Y-m-d H:i:s"),
                    'updated_at' => time()
                ], "device_id = :device_id AND action IN ('boot', 'shutdown') AND created_time >= :created_time", [
                    ":device_id" => $deviceId,
                    ":created_time" => $fromTime
                ])
                ->execute();
        }

    }

    /**
     * @param int $deviceId
     * @param string $data
     * @param array $presetInfo
     * @return false|void
     * @throws \ESD\Yii\Db\Exception
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function execDeviceTask(int $deviceId, string $data, array $presetInfo)
    {
        $this->execTask($deviceId, $presetInfo);
        /*
        $todoList = (new DeviceTask())->getTodoListByDeviceId($deviceId);
        if (empty($todoList)) {
            return false;
        }
        foreach ($todoList as $key => $value) {
            $this->execOneDeviceTask($value, $presetInfo);
            Coroutine::sleep(1);
        }
        */
    }

    /**
     * @param $taskInfo
     * @param $presetInfo
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \ESD\Yii\Db\Exception
     */
    protected function execOneDeviceTask($taskInfo, $presetInfo)
    {
        $id = $taskInfo['id'];
        $deviceId = $taskInfo['device_id'];
        $params = Json::decode($taskInfo['params'], true);
        $asyncDeviceService = new AsyncDeviceService();
        switch ($taskInfo['action']) {
            case 'boot':
                $execRes = $asyncDeviceService->boot($deviceId, $params, $presetInfo);
                break;

            case 'shutdown':
                $execRes = $asyncDeviceService->shutdown($deviceId, $params, $presetInfo);
                break;

            case 'recharge':
                $execRes = $asyncDeviceService->recharge($deviceId, $params, $presetInfo);
                break;

            case 'systemInit':
                $execRes = $asyncDeviceService->systemInit($deviceId, $params, $presetInfo);
                break;

            case 'reset':
                $execRes = $asyncDeviceService->reset($deviceId, $params, $presetInfo);
                break;

            case 'setConfig':
                $execRes = $asyncDeviceService->setConfig($deviceId, $params, $presetInfo);
                break;

        }
        (new DeviceTask())->finishTask($id);
//        if ($execRes) {
//            (new DeviceTask())->finishTask($id);
//        }
    }
}