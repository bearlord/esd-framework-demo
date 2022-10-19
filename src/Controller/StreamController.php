<?php


namespace App\Controller;


use App\Libs\ExtraHelper;
use App\Model\Iccid;
use App\Model\Stream\StreamModel;
use ESD\Core\Plugins\Event\Event;
use ESD\Server\Coroutine\Server;
use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\TcpController;
use ESD\Plugins\Pack\ClientData;
use ESD\Plugins\Pack\GetBoostSend;
use ESD\Plugins\Redis\GetRedis;
use ESD\Yii\Yii;

/**
 * @TcpController(portNames={"tcp"})
 * Class StreamController
 * @package App\Controller
 */
class StreamController extends GoController
{

    use GetBoostSend;
    use GetRedis;

//    public function beforeAction($action)
//    {
//        printf("before\n");
//        return parent::beforeAction($action);
//    }
//
//    public function afterAction($action, $result)
//    {
//        printf("after\n");
//        return parent::afterAction($action, $result);
//    }

    /**
     * @RequestMapping("onConnect")
     * @return void
     */
    public function actionOnConnect()
    {
        Server::$instance->getLog()->critical("on Connect!");
    }

    /**
     * @RequestMapping("onClose")
     * @return void
     */
    public function actionOnClose()
    {
        Server::$instance->getLog()->critical("on Close!");
    }


    /**
     * @RequestMapping("onReceive")
     * @throws \ESD\Plugins\Redis\RedisException
     */
    public function actionOnTcpReceive()
    {
        $fd = $this->clientData->getFd();
        $data = $this->clientData->getData();

        //调试信息
        ExtraHelper::debug($fd, 1, $data);

        //数据长度
        $dataLength = strlen($data);


        //如果数据长度不等于[8, 90, 104]，则是非法
        if (in_array($dataLength, [8, 90, 104, 116]) === false) {
            ExtraHelper::debug($fd, 3, sprintf("%s 数据不合法", $data));
            return false;
        }

        $this->subProcess($fd, $data, $dataLength);

        return true;
    }

    /**
     * @param int $fd
     * @param string $data
     * @param int $dataLength
     * @return false
     * @throws \ESD\Plugins\Redis\RedisException
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    protected function subProcess(int $fd, string $data, int $dataLength): bool
    {
        if (in_array($dataLength, [8, 90, 104, 116]) == false) {
            ExtraHelper::debug($fd, 3, sprintf("%s 数据不合法", $data));
            return false;
        }

        //设备ID，16进制
        $deviceIdHex = substr($data, 0, 8);
        //设备ID，10进制
        $deviceId = hexdec($deviceIdHex);

        /** @var Iccid $iccidModel */
        $iccidModel = new Iccid();

        if (!empty($deviceId)) {
            //预设信息
            $presetInfo = $iccidModel->getInfoByPresetDeviceId($deviceId);
        } else {
            if ($dataLength === 104) {
                //ICCID
                $iccid = substr($data, 70, 20);
                //Imei
                $imei = substr($data, 52, 15);

                ExtraHelper::debug($fd, 3, $iccid);
                ExtraHelper::debug($fd, 3, $imei);

                $presetInfo = $iccidModel->getInfoByIccidImei($iccid, $imei);
            } elseif ($dataLength === 116) {
                //ICCID
                $iccid = substr($data, 82, 20);
                //Imei
                $imei = substr($data, 64, 15);

                ExtraHelper::debug($fd, 3, $iccid);
                ExtraHelper::debug($fd, 3, $imei);

                $presetInfo = $iccidModel->getInfoByIccidImei($iccid, $imei);
            }
        }

        //没有预设信息 退出
        if (empty($presetInfo)) {
            printf("empty\n");
            return false;
        }

        $streamModel = new StreamModel();
        $streamModel->entry($data, $fd, $presetInfo);
        return true;
    }

    /**
     * 分割字符串
     * @param $string
     * @param int $len
     * @return mixed
     */
    protected function mbStrSplit($string, $len = 1)
    {
        $start = 0;
        $strlen = mb_strlen($string);

        $array = [];
        while ($strlen) {
            $array[] = mb_substr($string, $start, $len, "utf8");
            $string = mb_substr($string, $len, $strlen, "utf8");
            $strlen = mb_strlen($string);
        }
        return $array;
    }
}