<?php

namespace App\Actor;

use App\Libs\ExtraHelper;
use app\Models\V2\DeviceModel;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorMessage;
use ESD\Server\Coroutine\Server;

class ShouShuiJiActor extends Actor
{
    /**
     * @var mixed 数据
     */
    protected $data;

    protected $tcpData;

    protected $activeTtl = 1200;

    /**
     * @var array 预设信息
     */
    protected $presetInfo;

    /**
     * @var int 设备ID
     */
    protected $deviceId;

    /**
     * @var string 设备状态
     */
    protected $state;

    /**
     * @var int 设备是否占用
     */
    protected $isOccupied;

    /**
     * @var int 网络状态
     */
    protected $netState = 0;

    /**
     * @var int 链接标识
     */
    protected $fd;

    /**
     * @var int 上次通讯的时间
     */
    protected $lastCommTimestamp;

    /**
     * @var string 当前的sessionId
     */
    protected $nowSessionId;

    /**
     * @var string 上次的sessionId
     */
    protected $lastSession;

    //定时器ID
    protected $timerId;


    public function initData($data)
    {
        $this->data = $data;
        $this->presetInfo = $data['presetInfo'];
        $this->fd = $data['fd'];
    }

    protected function handleMessage(ActorMessage $message)
    {
        var_dump($message);
        var_dump($message->getData());
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }

    /**
     * 处理TCP数据
     * @throws \Exception
     */
    public function processTcpData(string $data)
    {
        $this->lastCommTimestamp = time();

        $deviceId = hexdec(substr($data, 0, 8));
        //控制
        $ctrl = substr($data, 10, 2);
        //设备状态
        $deviceState = substr($data, 12, 2);

        $this->deviceId = $deviceId;
        $this->state = $deviceState;

        $_data = ExtraHelper::hexDataToArray($data, $this->presetInfo);

        switch ($ctrl) {
            //刷卡，发送取水指令
            case 'a0':
                if ($this->isOccupied) {
                    Server::$instance->getLog()->critical("设备占用中，暂时不能取水");
                    return false;
                } else {
                    $this->isOccupied = 1;
                }
                $this->nowSessionId = ExtraHelper::generateCommunicationId();

                $this->timerId = addTimerAfter(1000 * 30, function() {
                    if ($this->lastSession != $this->nowSessionId) {
                        $this->lastSession = $this->nowSessionId;
                        $this->isOccupied = false;
                        Server::$instance->getLog()->critical("30秒未操作，本次取水结束");
                    }
                });
                $this->fetchWater();

                break;
            //放水的回执
            case 90:
                $this->fetchWaterReceipt();
                break;
            //用水同步
            case '06':
                $this->usedWaterSync();
                $this->lastSession = $this->nowSessionId;
                $this->isOccupied = false;

                if ($this->timerId) {
                    clearTimerTick($this->timerId);
                }
                Server::$instance->getLog()->critical("本次取水结束");
                break;
        }
    }

    /**
     * 小心跳
     * @return void
     */
    protected function heartbeatSmall()
    {

    }

    /**
     * 大心跳
     * @return void
     */
    protected function heartbeatBig()
    {

    }

    /**
     * 用水同步
     * @return void
     */
    protected function usedWaterSync()
    {

    }

    /**
     * 状态变更
     * @return void
     */
    protected function stateChanged()
    {

    }

    /**
     * 关机回执
     * @return void
     */
    protected function shutdownReceipt()
    {

    }

    /**
     * 开机回执
     * @return void
     */
    protected function bootReceipt()
    {

    }

    /**
     * 强冲回执
     * @return void
     */
    protected function forceFlushReceipt()
    {

    }

    /**
     * 配置回执
     * @return void
     */
    protected function setConfigReceipt()
    {

    }

    /**
     * 充值回执
     * @return void
     */
    protected function rechargeReceipt()
    {

    }

    /**
     * 滤芯复位回执
     * @return void
     */
    protected function filterResetReceipt()
    {

    }

    /**
     * 切换模式回执
     * @return void
     */
    protected function switchModeReceipt()
    {

    }

    /**
     * 系统初始化回执
     * @return void
     */
    protected function systemInitReceipt()
    {

    }

    /**
     * 查询回执
     * @return void
     */
    protected function queryReceipt()
    {

    }

    /**
     * 获取信号
     * @return void
     */
    protected function fetchSignalAndICCIDReceipt()
    {

    }

    /**
     * 锁定回执
     * @deprecated
     * @return void
     */
    protected function lockReceipt()
    {

    }

    /**
     * 取水回执
     * @return void
     */
    protected function fetchWaterReceipt()
    {

    }

    /**
     * 开机
     * @return void
     */
    public function boot()
    {

    }

    /**
     * 关机
     * @return void
     */
    public function shutdown()
    {

    }

    /**
     * 查询
     * @return void
     */
    public function query()
    {

    }

    /**
     * 强冲
     * @return void
     */
    public function forceFlush()
    {

    }

    /**
     * 充值
     * @return void
     */
    public function recharge()
    {

    }

    /**
     * 滤芯复位
     * @return void
     */
    public function filterReset()
    {

    }

    /**
     * 切换计费模式
     * @deprecated
     * @return void
     */
    public function switchMode()
    {
    }

    /**
     * 系统初始化
     * @return void
     */
    public function systemInit()
    {

    }

    /**
     * 恢复出厂设置
     * @return void
     */
    public function reset()
    {

    }

    /**
     * 获取信号
     * @return void
     */
    public function fetchSignalAndICCID()
    {

    }

    /**
     * 用水同步
     * @return void
     */
    public function usedTimeSync()
    {

    }

    /**
     * 锁定
     * @return void
     */
    public function lock()
    {

    }

    /**
     * 取水
     * @return void
     */
    public function fetchWater()
    {
        $deviceId = $this->deviceId;
        $presetInfo = $this->presetInfo;
        $protocolLength = 116;

        //16进制，补齐8位
        $deviceIdHex = ExtraHelper::decHexPad($deviceId, 8);

        //Ctrl
        $ctrlHex = '80';

        //暂时留空
        $args = [

        ];

        //设备最大取水量, 毫升
        $maxFlow = !empty($args['maxFlow']) ? (int)$args['maxFlow'] : 0;
        //设备最大等待时间, 秒
        $maxIdleTime = !empty($args['maxIdleTime']) ? (int)$args['maxIdleTime'] : 0;
        //设置最大取水时间, 秒
        $maxWorkingTime = !empty($args['maxWorkingTime']) ? (int)$args['maxWorkingTime'] : 0;
        //设置最大暂停时间, 秒
        $maxPauseTime = !empty($args['maxPauseTime']) ? (int)$args['maxPauseTime'] : 0;
        //最高温度
        $maxHotTemperature = !empty($args['maxHotTemperature']) ? (int)$args['maxHotTemperature'] : 0;
        //最低温度
        $maxColdTemperature = !empty($args['maxColdTemperature']) ? (int)$args['maxColdTemperature'] : 0;
        //网络通讯id，无参数默认为10位UNIX时间戳
        $communicationId = !empty($args['communicationId']) ? (string)$args['communicationId'] : ExtraHelper::generateCommunicationId(1);

        //计费模式
        $chargeMode = 5;
        $chargeModeHex = "05";

        switch ($protocolLength) {
            case 104:
                $maxFlowHex = ExtraHelper::decHexPad($maxFlow, 4);
                $maxIdleTimeHex = ExtraHelper::decHexPad($maxIdleTime, 4);
                $maxWorkingTimeHex = ExtraHelper::decHexPad($maxWorkingTime, 4);
                $maxPauseTimeHex = ExtraHelper::decHexPad($maxPauseTime, 4);
                $maxHotTemperature = ExtraHelper::decHexPad($maxHotTemperature, 4);
                $maxColdTemperature = ExtraHelper::decHexPad($maxColdTemperature, 4);
                $communicationIdHex = $communicationId;

                //程序码
                $programCode = $presetInfo['program_code'];
                $programCodeHex = ExtraHelper::decHexPad($programCode, 2);

                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);

                $cmdString = substr_replace($cmdString, $maxFlowHex, 18, 4);
                $cmdString = substr_replace($cmdString, $maxIdleTimeHex, 22, 4);
                $cmdString = substr_replace($cmdString, $maxWorkingTimeHex, 26, 4);
                $cmdString = substr_replace($cmdString, $maxPauseTimeHex, 30, 4);
                $cmdString = substr_replace($cmdString, $maxHotTemperature, 34, 4);
                $cmdString = substr_replace($cmdString, $maxColdTemperature, 38, 4);
                if ($communicationIdHex) {
                    $cmdString = substr_replace($cmdString, $communicationIdHex, 58, 10);
                }

                $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 90, 8);
                $cmdString = substr_replace($cmdString, $programCodeHex, 98, 2);
                break;

            case 116:
                $maxFlowHex = ExtraHelper::decHexPad($maxFlow, 8);
                $maxIdleTimeHex = ExtraHelper::decHexPad($maxIdleTime, 4);
                $maxWorkingTimeHex = ExtraHelper::decHexPad($maxWorkingTime, 8);
                $maxPauseTimeHex = ExtraHelper::decHexPad($maxPauseTime, 4);
                $maxHotTemperature = ExtraHelper::decHexPad($maxHotTemperature, 8);
                $maxColdTemperature = ExtraHelper::decHexPad($maxColdTemperature, 4);

                $balance = !empty($args['balance']) ? $args['balance'] : 0;
                $balanceHex = ExtraHelper::decHexPad($balance, 8);

                //Seconds from zero
                $secondsFromZero = ExtraHelper::secondsFromZero();
                $secondsFromZeroHex = ExtraHelper::decHexPad($secondsFromZero, 8);

                $cmdTemplate = str_repeat(0, $protocolLength);
                $cmdString = substr_replace($cmdTemplate, $deviceIdHex, 0, 8);
                $cmdString = substr_replace($cmdString, $chargeModeHex, 8, 2);
                $cmdString = substr_replace($cmdString, $ctrlHex, 10, 2);

                $cmdString = substr_replace($cmdString, $maxFlowHex, 18, 8);
                $cmdString = substr_replace($cmdString, $maxIdleTimeHex, 26, 4);
                $cmdString = substr_replace($cmdString, $maxWorkingTimeHex, 30, 8);
                $cmdString = substr_replace($cmdString, $maxPauseTimeHex, 38, 4);
                $cmdString = substr_replace($cmdString, $maxHotTemperature, 42, 8);
                $cmdString = substr_replace($cmdString, $maxColdTemperature, 50, 4);
                $cmdString = substr_replace($cmdString, $balanceHex, 62, 8);

                $cmdString = substr_replace($cmdString, $secondsFromZeroHex, 102, 8);
                break;
        }
        $this->sendDataToDevice($cmdString);
    }

    /**
     * 刷卡
     * @return void
     */
    public function shuaKa()
    {

    }


    /**
     * @param string $cmdString
     * @return bool
     */
    protected function sendDataToDevice(string $cmdString): bool
    {
        if (time() - $this->lastCommTimestamp > $this->activeTtl) {
            return false;
        }
        return $this->sendDataToFd($this->fd, $cmdString);
    }

    /**
     * @param int $fd
     * @param string $data
     * @return bool
     */
    protected function sendDataToFd(int $fd, string $data): bool
    {
        ExtraHelper::debug($fd, 2, $data);
        $bin = hex2bin($data);
        Server::$instance->autoSend($fd, $bin);

        return true;
    }

}