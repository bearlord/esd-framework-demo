<?php

namespace App\Plugins\Pack\PackTool;

use ESD\Core\Plugins\Logger\GetLogger;
use ESD\Core\Server\Config\PortConfig;
use ESD\Core\Server\Server;
use ESD\Plugins\Pack\ClientData;
use ESD\Plugins\Pack\PackTool\IPack;
use ESD\Yii\Helpers\Json;

class WsJsonPack implements IPack
{
    use GetLogger;

    /**
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null)
    {
        return Json::encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param int $fd
     * @param string $data
     * @param PortConfig $portConfig
     * @return ClientData
     * @throws \ESD\Core\Plugins\Config\ConfigException
     * @throws \Exception
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $value = Json::decode($data, true);
        if (empty($value)) {
            $this->warn('json unPack 失败');
            return null;
        }
        if (empty($value['action'])) {
            $this->warn('参数错误');
            return null;
        }

        return new ClientData($fd, $portConfig->getBaseType(), $value['action'], $value);
    }

    public function encode($buffer)
    {
        return $buffer;
    }

    public function decode($buffer)
    {
        return $buffer;
    }

    /**
     * @throws \Exception
     */
    public static function changePortConfig(PortConfig $portConfig)
    {
        return;
    }
}