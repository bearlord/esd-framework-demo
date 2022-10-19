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
use ESD\Plugins\Pack\PackTool\AbstractPack;
/**
 * Class StreamPack
 * @package ESD\Plugins\Pack\PackTool
 */
class StreamPack extends AbstractPack
{
    use GetLogger;

    /**
     * Packet encode
     *
     * @param $buffer
     * @return string
     */
    public function encode($buffer)
    {
        return !empty($buffer) ? hex2bin($buffer) : '';
//        return !empty($buffer) ? hex2bin($buffer . '1B') : '';
    }

    /**
     * Packet decode
     *
     * @param $buffer
     * @return string
     */
    public function decode($buffer)
    {
        $data = bin2hex($buffer);
        return $data;
    }

    /**
     * Data pack
     *
     * @param $data
     * @param PortConfig $portConfig
     * @param string|null $topic
     * @return string
     */
    public function pack($data, PortConfig $portConfig, ?string $topic = null)
    {
        $this->portConfig = $portConfig;
        return $this->encode($data);
    }

    /**
     * Packet unpack
     *
     * @param int $fd
     * @param string $data
     * @param PortConfig $portConfig
     * @return mixed
     * @throws \ESD\Core\Plugins\Config\ConfigException
     */
    public function unPack(int $fd, $data, PortConfig $portConfig): ?ClientData
    {
        $this->portConfig = $portConfig;
        //Value can be empty
        $value = $this->decode($data);

        return new ClientData($fd, $portConfig->getBaseType(), 'onReceive', $value);
    }

    /**
     * Change port config
     *
     * @param PortConfig $portConfig
     * @return bool
     * @throws \Exception
     */
    public static function changePortConfig(PortConfig $portConfig): bool
    {
        return true;
    }
}