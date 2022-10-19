<?php

namespace App\Controller;

use App\Model\Mqtt\ClientSubscribe;
use App\Model\Mqtt\PackageLog;
use ESD\Core\Server\Server;
use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\MqttController;
use ESD\Plugins\EasyRoute\Annotation\MqttMapping;
use ESD\Plugins\MQTT\Message\ConnAck;
use ESD\Plugins\MQTT\Message\PingResp;
use ESD\Plugins\MQTT\Message\PubAck;
use ESD\Plugins\MQTT\Message\Publish;
use ESD\Plugins\MQTT\Message\PubRec;
use ESD\Plugins\MQTT\Message\SubAck;
use ESD\Plugins\MQTT\Message\UnSubAck;
use ESD\Plugins\MQTT\Protocol\Types;
use ESD\Plugins\Redis\GetRedis;

/**
 * //@MqttController(portNames={"mqtt"});
 */
class MqttServiceController extends GoController
{

    use GetRedis;

    protected function getFdFromClientId(string $clientId)
    {
        $key = sprintf("MQTT_ClientId_Fd_Map_%s", $clientId);
        $value = getContextValue($key);
        if (!$value) {
            $value = $this->redis()->get($key);
            setContextValue($key, $value);
        }
        return $value;
    }

    /**
     * @param int $fd
     * @param string $clientId
     * @throws \ESD\Plugins\Redis\RedisException
     */
    protected function deleteFdMaps(int $fd, string $clientId): void
    {
        $fdKey = sprintf("MQTT_Fd_ClientId_Map_%s", $fd);
        deleteContextValue($fdKey);
        $this->redis()->del($fdKey);

        $clientKey = sprintf("MQTT_ClientId_Fd_Map_%s", $clientId);
        deleteContextValue($clientKey);
        $this->redis()->del($clientKey);
    }

    /**
     * @MqttMapping("onReceive")
     * @throws \Exception
     */
    public function actionOnTcpReceive()
    {
        $fd = $this->clientData->getFd();
        $receiveData = $this->clientData->getData();
        printf("fd: %s\n data:%s\n\n", $fd, var_export($receiveData, true));

        //类型
        $type = $receiveData['type'];
        //协议版本
        $protocolLevel = $receiveData['level'];
        //解包后数据
        $unpackedData = $receiveData['data'];
        //客户端标识
        $clientId = $receiveData['client_id'];

        $packageLogModel = new PackageLog();
        $packageLogModel->writeLog($fd, $receiveData);


        switch ($type) {
            case Types::CONNECT:
                $this->autoBoostSend(
                    $fd,
                    (new ConnAck())
                        ->setProtocolLevel($protocolLevel)
                        ->setCode(0)
                        ->setSessionPresent(0)
                );
                //TO DO,业务数据
                break;

            case Types::PUBLISH:
                $topic = $unpackedData['topic'];
                //订阅者
                $subscribers = (new ClientSubscribe())->getItemsByTopic($topic);
                if (empty($subscribers)) {
                    return true;
                }

                foreach ($subscribers as $key => $subscriber) {
                    $clientId = $subscriber['client_id'];
                    $subFd = $this->getFdFromClientId($clientId);
                    $this->autoBoostSend(
                        $subFd,
                        (new Publish())
                            ->setProtocolLevel($protocolLevel)
                            ->setTopic($topic)
                            ->setMessage($unpackedData['message'])
                            ->setDup($unpackedData['dup'])
                            ->setQos($unpackedData['qos'])
                            ->setRetain($unpackedData['retain'])
                            ->setMessageId($unpackedData['message_id'] ?? 0)
                    );
                }

                switch ($unpackedData['qos'])
                {
                    case 1:
                        $this->autoBoostSend(
                            $fd,
                            (new PubAck())
                                ->setProtocolLevel($protocolLevel)
                                ->setMessageId($unpackedData['message_id'])
                        );
                        break;

                    case 2:
                        $this->autoBoostSend(
                            $fd,
                            (new PubRec())
                                ->setProtocolLevel($protocolLevel)
                                ->setMessageId($unpackedData['message_id'])
                        );
                        break;
                }
                break;

            case Types::PUBACK:
                printf("PUBACK\n");

                //todo
                break;

            case Types::PUBREL:
                printf("PUBREL\n");

                break;

            case Types::SUBSCRIBE:
                foreach ($unpackedData['topics'] as $topic => $option) {
                    $clientSubscribeModel = new ClientSubscribe();
                    $clientSubscribeModel->subscribe($clientId, $topic, $option);
                }

                $payload = [];
                foreach ($unpackedData['topics'] as $topic => $option) {
                    if (is_numeric($option['qos']) && $option['qos'] < 3) {
                        $payload[] = $option['qos'];
                    } else {
                        $payload[] = 0x80;
                    }
                }

                $this->autoBoostSend(
                    $fd,
                    (new SubAck())->setProtocolLevel($protocolLevel)
                        ->setMessageId($unpackedData['message_id'] ?? 0)
                        ->setCodes($payload)
                );
                break;

            case Types::UNSUBSCRIBE:
                foreach ($unpackedData['topics'] as $key => $topic) {
                    $clientSubscribeModel = new ClientSubscribe();
                    $clientSubscribeModel->unsubscribe($clientId, $topic);
                }

                $this->autoBoostSend(
                    $fd,
                    (new UnSubAck())
                        ->setProtocolLevel($protocolLevel)
                        ->setMessageId($unpackedData['message_id'] ?? 0)
                );
                break;

            case Types::PINGREQ:
                $this->autoBoostSend(
                    $fd,
                    (new PingResp())
                );
                break;

            case Types::DISCONNECT:
//                //协议版本
//                $protocolLevel = $this->redis()->hGet($this->buildRedisFdKey($fd), 'protocol_level');
//                $this->autoBoostSend(
//                    $fd,
//                    (new DisConnect())
//                        ->setProtocolLevel($protocolLevel)
//                        ->setCode(0)
//                );
                Server::$instance->closeFd($fd);
                break;
        }


    }
}