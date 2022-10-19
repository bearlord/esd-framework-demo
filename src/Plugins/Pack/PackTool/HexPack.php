<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace app\Plugins\Pack\PackTool;

use ESD\Core\Plugins\Logger\GetLogger;
use ESD\Core\Server\Config\PortConfig;
use ESD\Core\Server\Server;
use ESD\Plugins\Pack\ClientData;
use ESD\Plugins\Pack\PackTool\IPack;

class HexPack implements IPack
{
    use GetLogger;

    /**
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return false|string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null)
    {
        return hex2bin($data);
    }

    /**
     * @param int $fd
     * @param string $data
     * @param PortConfig $portConfig
     * @return ClientData
     * @throws \ESD\Core\Plugins\Config\ConfigException
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $value = bin2hex($data);
        if (empty($value)) {
            $this->warn('json unPack 失败');
            return null;
        }
        $clientData = new ClientData($fd, $portConfig->getBaseType(), 'stream', $value);
        return $clientData;
    }

    public function encode($buffer)
    {
        return $buffer;
    }

    public function decode($buffer)
    {
        return $buffer;
    }

    public static function changePortConfig(PortConfig $portConfig)
    {
        return ;
    }
}