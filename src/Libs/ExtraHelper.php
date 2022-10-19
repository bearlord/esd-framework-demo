<?php

namespace App\Libs;

use ESD\Yii\Yii;

class ExtraHelper
{
    /**
     * 10进制转16进制，并左补齐0
     * 如10 => a => 000A
     *
     * @param int $n
     * @param int $len
     * @return string
     */
    public static function decHexPad(int $n, int $len)
    {
        //强制转为数字
        $n = intval($n);
        return str_pad(dechex($n), $len, 0, STR_PAD_LEFT);
    }

    /**
     * 16进制转10进制，并左补齐0
     * 如a => 10 => 0010
     *
     * @param string $n
     * @param int $len
     * @return string
     */
    public static function hexDecPad(string $n, int $len)
    {
        //强制转为数字
        return str_pad(hexdec($n), $len, 0, STR_PAD_LEFT);
    }

    /**
     * Second from zero
     *
     * @return false|float|int|string
     */
    public static function secondsFromZero()
    {
        $hour = date("H");
        $minute = date("i");
        $second = date("s");
        return $hour * 3600 + $minute * 60 + $second;
    }

    /**
     * 每2位一分隔，10进制的和，再转为16进制
     * @param string $string
     * @param int $length
     * @return string
     */
    public static function checkSum(string $string, int $length): string
    {
        $tempArray = [];
        for ($i = 0; $i <= $length; $i = $i + 2) {
            $tempArray[] = hexdec(substr($string, $i, 2));

        }
        $sum = array_sum($tempArray);
        return str_pad(dechex($sum), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Build redis device key
     *
     * @param string $eId
     * @return string
     */
    public static function buildRedisDeviceKey(string $eId): string
    {
        //设备ID，共8位. 16进制转10进制，0左补齐.
        return 'device_' . $eId;
    }

    /**
     * 产生redis的tcp key，十进制，8位，左补齐0
     *
     * @param int $fd
     * @return string
     */
    public static function buildRedisTcpKey(int $fd): string
    {
        return 'fd_' . $fd;
    }

    /**
     * @param string $data
     * @param array $presetInfo
     * @return array|bool
     */
    public static function hexDataToArray(string $data, array $presetInfo): array
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        switch ((int)$presetInfo['protocol_length']) {
            case 90:
                $result = self::hexData90($data, $presetInfo);
                break;

            case 104:
                $result = self::hexData104($data, $presetInfo);
                break;

            case 116:
                $result = self::hexData116($data, $presetInfo);
                break;
        }
        return $result;
    }

    /**
     * @param string $data
     * @return array
     */
    public static function hexData90(string $data, $presetInfo = []): array
    {
        $ctrl = substr($data, 10, 2);

        if ($ctrl == 'ee') {
            $result['cTrl'] = $ctrl;
            $result['signal'] = (int)hexdec(substr($data, 68, 2));
            $result['iccid'] = (string)substr($data, 70, 20);
            $result['programCode'] = '';
            return $result;
        }

        $result['deviceId'] = hexdec(substr($data, 0, 8));
        $result['chargeMode'] = hexdec(substr($data, 8, 2));
        $result['cTrl'] = $ctrl;
        $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);
        $result['thisFlow'] = hexdec(substr($data, 14, 4)) * 10;
        $result['addFlow'] = hexdec(substr($data, 18, 4));
        $result['addTime'] = hexdec(substr($data, 22, 4));
        $result['restFlow'] = hexdec(substr($data, 26, 4));
        $result['restTime'] = hexdec(substr($data, 30, 4));
        $result['usedFlow'] = hexdec(substr($data, 34, 4));
        $result['usedTime'] = hexdec(substr($data, 38, 4));

        $purityTDS = hexdec(substr($data, 42, 4));
        $rawTDS = hexdec(substr($data, 46, 4));
        $result['rawTDS'] = $rawTDS;
        $result['purityTDS'] = $purityTDS;

        $result['F1Flux'] = hexdec(substr($data, 50, 4));
        $result['F2Flux'] = hexdec(substr($data, 54, 4));
        $result['F3Flux'] = hexdec(substr($data, 58, 4));
        $result['F4Flux'] = hexdec(substr($data, 62, 4));
        $result['F5Flux'] = hexdec(substr($data, 66, 4));
        $result['F1FluxMax'] = hexdec(substr($data, 70, 4));
        $result['F2FluxMax'] = hexdec(substr($data, 74, 4));
        $result['F3FluxMax'] = hexdec(substr($data, 78, 4));
        $result['F4FluxMax'] = hexdec(substr($data, 82, 4));
        $result['F5FluxMax'] = hexdec(substr($data, 86, 4));
        $result['programCode'] = '';
        return $result;
    }

    /**
     * @param string $data
     * @param array $presetInfo
     * @return array|void
     */
    public static function hexData104(string $data, array $presetInfo)
    {
        $ctrl = substr($data, 10, 2);

        switch ($ctrl) {
            case 'ee':
                $result['cTrl'] = $ctrl;
                $result['signal'] = (int)hexdec(substr($data, 68, 2));

                if ($presetInfo['dependency_type'] == 3) {
                    $result['iccid'] = (string)substr($data, 70, 12);
                } else {
                    $result['iccid'] = (string)substr($data, 70, 20);
                }

                $result['filterChargeMode'] = (int)hexdec(substr($data, 12, 2));
                $result['flowmeter'] = (int)hexdec(substr($data, 18, 4));
                $result['flowmeterHot'] = (int)hexdec(substr($data, 58, 4));
                $result['faucet'] = (int)hexdec(substr($data, 22, 4));
                $result['waterPump'] = (int)hexdec(substr($data, 26, 4));
                $result['maintenance'] = hexdec(substr($data, 30, 4));
                $result['maximumTemperature'] = hexdec(substr($data, 34, 4));
                $result['minimumTemperature'] = hexdec(substr($data, 38, 4));
                $result['programCode'] = hexdec(substr($data, 98, 2));
                return $result;
                break;

            case '44':
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                $result['flowmeter'] = hexdec(substr($data, 18, 4));
                $result['flowmeterHot'] = hexdec(substr($data, 58, 4));
                $result['faucet'] = hexdec(substr($data, 22, 4));
                $result['faucet_hot'] = (int)hexdec(substr($data, 74, 4));
                $result['waterPump'] = hexdec(substr($data, 26, 4));
                $result['maintenance'] = hexdec(substr($data, 30, 4));
                $result['maximumTemperature'] = hexdec(substr($data, 34, 4));
                $result['minimumTemperature'] = hexdec(substr($data, 38, 4));

                $result['programCode'] = hexdec(substr($data, 98, 2));
                return $result;
                break;

            case '90':
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                //104字节协议，本次消费单位是ml
                $thiFlow = hexdec(substr($data, 14, 4));
                $result['thisFlow'] = $thiFlow;

                $result['thisFlowHot'] = hexdec(substr($data, 18, 4));
                $result['thisFlowCold'] = hexdec(substr($data, 22, 4));

                $result['restFlow'] = hexdec(substr($data, 26, 4));
                $result['restTime'] = hexdec(substr($data, 30, 4));
                $result['usedFlow'] = hexdec(substr($data, 34, 4));
                $result['usedTime'] = hexdec(substr($data, 38, 4));

                //TDS容错处理
                $result['purityTDS'] = hexdec(substr($data, 42, 4));
                $result['rawTDS'] = hexdec(substr($data, 46, 4));

                //网络通讯ID
                $result['communicationId'] = substr($data, 58, 10);
                $result['programCode'] = hexdec(substr($data, 98, 2));
                return $result;
                break;

            case '06':
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                //104字节协议，本次消费单位是ml
                $thiFlow = hexdec(substr($data, 14, 4));
                $result['thisFlow'] = $thiFlow;
                $result['restFlow'] = hexdec(substr($data, 26, 4));
                $result['restTime'] = hexdec(substr($data, 30, 4));
                $result['usedFlow'] = hexdec(substr($data, 34, 4));
                $result['usedTime'] = hexdec(substr($data, 38, 4));

                //TDS
                $result['purityTDS'] = hexdec(substr($data, 42, 4));
                $result['rawTDS'] = hexdec(substr($data, 46, 4));

                $result['programCode'] = hexdec(substr($data, 98, 2));
                if (in_array($presetInfo['device_sub_type'], [1, 2])) {
                    $result['thisFlowHot'] = hexdec(substr($data, 18, 4));
                    $result['thisFlowCold'] = hexdec(substr($data, 22, 4));

                    //网络通讯ID
                    $result['communicationId'] = substr($data, 58, 10);
                } else {
                    $result['F1Flux'] = hexdec(substr($data, 50, 4));
                    $result['F2Flux'] = hexdec(substr($data, 54, 4));
                    $result['F3Flux'] = hexdec(substr($data, 58, 4));
                    $result['F4Flux'] = hexdec(substr($data, 62, 4));
                    $result['F5Flux'] = hexdec(substr($data, 66, 4));
                    $result['F1FluxMax'] = hexdec(substr($data, 70, 4));
                    $result['F2FluxMax'] = hexdec(substr($data, 74, 4));
                    $result['F3FluxMax'] = hexdec(substr($data, 78, 4));
                    $result['F4FluxMax'] = hexdec(substr($data, 82, 4));
                    $result['F5FluxMax'] = hexdec(substr($data, 86, 4));
                }
                return $result;
                break;

            default:
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                //104字节协议，本次消费单位是ml
                $thiFlow = hexdec(substr($data, 14, 4));
                $result['thisFlow'] = $thiFlow;

                $result['addFlow'] = hexdec(substr($data, 18, 4));
                $result['addTime'] = hexdec(substr($data, 22, 4));
                $result['restFlow'] = hexdec(substr($data, 26, 4));
                $result['restTime'] = hexdec(substr($data, 30, 4));
                $result['usedFlow'] = hexdec(substr($data, 34, 4));
                $result['usedTime'] = hexdec(substr($data, 38, 4));

                //TDS容错处理
                $rawTDS = hexdec(substr($data, 42, 4));
                $purityTDS = hexdec(substr($data, 46, 4));
                $result['rawTDS'] = $rawTDS > $purityTDS ? $rawTDS : $purityTDS;
                $result['purityTDS'] = $rawTDS > $purityTDS ? $purityTDS : $rawTDS;

                $result['F1Flux'] = hexdec(substr($data, 50, 4));
                $result['F2Flux'] = hexdec(substr($data, 54, 4));
                $result['F3Flux'] = hexdec(substr($data, 58, 4));
                $result['F4Flux'] = hexdec(substr($data, 62, 4));
                $result['F5Flux'] = hexdec(substr($data, 66, 4));
                $result['F1FluxMax'] = hexdec(substr($data, 70, 4));
                $result['F2FluxMax'] = hexdec(substr($data, 74, 4));
                $result['F3FluxMax'] = hexdec(substr($data, 78, 4));
                $result['F4FluxMax'] = hexdec(substr($data, 82, 4));
                $result['F5FluxMax'] = hexdec(substr($data, 86, 4));
                $result['programCode'] = hexdec(substr($data, 98, 2));
                return $result;
                break;
        }
    }

    public static function hexData116(string $data, array $presetInfo)
    {
        $ctrl = substr($data, 10, 2);

        switch ($ctrl) {
            case 'ee':
                $result['cTrl'] = $ctrl;
                $result['signal'] = (int)hexdec(substr($data, 80, 2));
                $result['iccid'] = (string)substr($data, 82, 20);
                $result['filterChargeMode'] = (int)hexdec(substr($data, 12, 2));
                $result['flowmeter'] = (int)hexdec(substr($data, 18, 8));
                $result['flowmeterHot'] = (int)hexdec(substr($data, 70, 4));
                $result['faucet'] = (int)hexdec(substr($data, 26, 4));
                $result['waterPump'] = (int)hexdec(substr($data, 30, 8));
                $result['maintenance'] = hexdec(substr($data, 38, 4));
                $result['maximumTemperature'] = hexdec(substr($data, 42, 8));
                $result['minimumTemperature'] = hexdec(substr($data, 50, 4));
                $result['programCode'] = hexdec(substr($data, 110, 2));
                return $result;
                break;

            case '44':
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                $result['flowmeter'] = hexdec(substr($data, 18, 8));
                $result['flowmeterHot'] = hexdec(substr($data, 70, 4));
                $result['faucet'] = hexdec(substr($data, 26, 4));
                $result['faucet_hot'] = (int)hexdec(substr($data, 74, 4));
                $result['waterPump'] = hexdec(substr($data, 30, 8));
                $result['maintenance'] = hexdec(substr($data, 38, 4));
                $result['maximumTemperature'] = hexdec(substr($data, 42, 4));
                $result['minimumTemperature'] = hexdec(substr($data, 50, 4));
                $result['programCode'] = hexdec(substr($data, 110, 2));
                return $result;
                break;

            case 'a0':
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                $result['way'] = hexdec(substr($data, 78, 4));
                $result['card'] = substr($data, 82, 8);
                return $result;
                break;

            case '06':
                $result['deviceId'] = hexdec(substr($data, 0, 8));
                $result['chargeMode'] = hexdec(substr($data, 8, 2));
                $result['cTrl'] = $ctrl;
                $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                //116字节协议，本次消费单位是ml
                $thiFlow = hexdec(substr($data, 14, 4));
                $result['thisFlow'] = $thiFlow;

                switch ($presetInfo['device_sub_type']) {
                    case 1:
                    case 2:
                    case 3:
                        //售水机
                        $result['thisFlowHot'] = hexdec(substr($data, 18, 8));
                        $result['thisFlowCold'] = hexdec(substr($data, 26, 4));

                        if ($result['chargeMode'] == 5) {
                            $result['amount'] = hexdec(substr($data, 30, 8));
                        } else {
                            $result['restTime'] = hexdec(substr($data, 38, 4));
                            $result['usedFlow'] = hexdec(substr($data, 42, 8));
                            $result['usedTime'] = hexdec(substr($data, 50, 4));
                        }

                        //TDS容错处理
                        $rawTDS = hexdec(substr($data, 54, 4));
                        $purityTDS = hexdec(substr($data, 58, 4));
                        $result['rawTDS'] = $rawTDS > $purityTDS ? $rawTDS : $purityTDS;
                        $result['purityTDS'] = $rawTDS > $purityTDS ? $purityTDS : $rawTDS;

                        //网络通讯ID
                        $result['communicationId'] = substr($data, 70, 10);
                        $result['programCode'] = hexdec(substr($data, 110, 2));
                        break;

                    case 10:    //低功耗水控
                        $result['restFlow'] = hexdec(substr($data, 30, 8));
                        $result['restTime'] = hexdec(substr($data, 38, 4));
                        $result['usedFlow'] = hexdec(substr($data, 42, 8));
                        $result['usedTime'] = hexdec(substr($data, 50, 4));
                        $result['F1Flux'] = 0;
                        $result['F2Flux'] = 0;
                        $result['F3Flux'] = 0;
                        $result['F4Flux'] = 0;
                        $result['F5Flux'] = 0;
                        $result['F1FluxMax'] = 0;
                        $result['F2FluxMax'] = 0;
                        $result['F3FluxMax'] = 0;
                        $result['F4FluxMax'] = 0;
                        $result['F5FluxMax'] = 0;
                        $result['programCode'] = hexdec(substr($data, 110, 2));

                        //纯水
                        $result['purityTDS'] = hexdec(substr($data, 54, 4));
                        //电压
                        $result['voltage'] = hexdec(substr($data, 58, 4));
                        //信号
                        $result['signal'] = (int)hexdec(substr($data, 80, 2));
                        $result['imei'] = (int)hexdec(substr($data, 64, 15));
                        $result['iccid'] = (int)hexdec(substr($data, 82, 20));
                        return $result;
                        break;

                    case 0:
                    default:
                        $result['restFlow'] = hexdec(substr($data, 30, 8));
                        $result['restTime'] = hexdec(substr($data, 38, 4));
                        $result['usedFlow'] = hexdec(substr($data, 42, 8));
                        $result['usedTime'] = hexdec(substr($data, 50, 4));

                        //TDS容错处理
                        $result['purityTDS'] = hexdec(substr($data, 54, 4));
                        $result['rawTDS'] = hexdec(substr($data, 58, 4));

                        $result['F1Flux'] = hexdec(substr($data, 62, 4));
                        $result['F2Flux'] = hexdec(substr($data, 66, 4));
                        $result['F3Flux'] = hexdec(substr($data, 70, 4));
                        $result['F4Flux'] = hexdec(substr($data, 74, 4));
                        $result['F5Flux'] = hexdec(substr($data, 78, 4));
                        $result['F1FluxMax'] = hexdec(substr($data, 82, 4));
                        $result['F2FluxMax'] = hexdec(substr($data, 86, 4));
                        $result['F3FluxMax'] = hexdec(substr($data, 90, 4));
                        $result['F4FluxMax'] = hexdec(substr($data, 94, 4));
                        $result['F5FluxMax'] = hexdec(substr($data, 98, 4));
                        $result['programCode'] = hexdec(substr($data, 110, 2));
                        return $result;
                        break;
                }
                break;

            default:
                switch ($presetInfo['device_sub_type']) {
                    case 1:
                    case 2:
                    case 3:
                        //todo
                        break;

                    case 10:
                        $result['deviceId'] = hexdec(substr($data, 0, 8));
                        $result['chargeMode'] = hexdec(substr($data, 8, 2));
                        $result['cTrl'] = $ctrl;
                        $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                        //116字节协议，本次消费单位是ml
                        if ($ctrl == '0c') {
                            $result['thisFlow'] = 0;
                        } else {
                            $result['thisFlow'] = hexdec(substr($data, 14, 4));
                        }

                        $result['restFlow'] = hexdec(substr($data, 30, 8));
                        $result['restTime'] = hexdec(substr($data, 38, 4));
                        $result['usedFlow'] = hexdec(substr($data, 42, 8));
                        $result['usedTime'] = hexdec(substr($data, 50, 4));
                        $result['F1Flux'] = 0;
                        $result['F2Flux'] = 0;
                        $result['F3Flux'] = 0;
                        $result['F4Flux'] = 0;
                        $result['F5Flux'] = 0;
                        $result['F1FluxMax'] = 0;
                        $result['F2FluxMax'] = 0;
                        $result['F3FluxMax'] = 0;
                        $result['F4FluxMax'] = 0;
                        $result['F5FluxMax'] = 0;
                        $result['programCode'] = hexdec(substr($data, 110, 2));

                        //纯水
                        $result['purityTDS'] = hexdec(substr($data, 54, 4));
                        //原水
                        $result['rawTDS'] = 0;
                        //电压
                        $result['voltage'] = hexdec(substr($data, 58, 4));
                        //信号
                        $result['signal'] = (int)hexdec(substr($data, 80, 2));
                        $result['iccid'] = (string)substr($data, 82, 20);
                        return $result;
                        break;

                    default:
                        $result['deviceId'] = hexdec(substr($data, 0, 8));
                        $result['chargeMode'] = hexdec(substr($data, 8, 2));
                        $result['cTrl'] = $ctrl;
                        $result['deviceState'] = self::hexDecPad(substr($data, 12, 2), 2);

                        //116字节协议，本次消费单位是ml
                        $thiFlow = hexdec(substr($data, 14, 4));
                        $result['thisFlow'] = $thiFlow;

                        $result['restFlow'] = hexdec(substr($data, 30, 8));
                        $result['restTime'] = hexdec(substr($data, 38, 4));
                        $result['usedFlow'] = hexdec(substr($data, 42, 8));
                        $result['usedTime'] = hexdec(substr($data, 50, 4));

                        //TDS容错处理
                        $result['purityTDS'] = hexdec(substr($data, 54, 4));
                        $result['rawTDS'] = hexdec(substr($data, 58, 4));

                        $result['F1Flux'] = hexdec(substr($data, 62, 4));
                        $result['F2Flux'] = hexdec(substr($data, 66, 4));
                        $result['F3Flux'] = hexdec(substr($data, 70, 4));
                        $result['F4Flux'] = hexdec(substr($data, 74, 4));
                        $result['F5Flux'] = hexdec(substr($data, 78, 4));
                        $result['F1FluxMax'] = hexdec(substr($data, 82, 4));
                        $result['F2FluxMax'] = hexdec(substr($data, 86, 4));
                        $result['F3FluxMax'] = hexdec(substr($data, 90, 4));
                        $result['F4FluxMax'] = hexdec(substr($data, 94, 4));
                        $result['F5FluxMax'] = hexdec(substr($data, 98, 4));
                        $result['programCode'] = hexdec(substr($data, 110, 2));
                        return $result;
                        break;
                }

        }
    }


    /**
     * 过滤响应至浏览器的数据
     *
     * @param array $data
     * @return array
     */
    public static function filterResponseData(array $data): array
    {
        //兼容V1版本
        if (!empty($data['addFlow']) && strlen($data['addFlow']) === 4) {
            if ($data['cTrl'] == 'ee') {
                $result['signal'] = (int)hexdec(substr($data, 68, 2));
                $result['iccid'] = (string)substr($data, 70, 20);
                return $result;
            } else {
                $result['deviceId'] = hexdec($data['deviceId']);
                $result['chargeMode'] = hexdec($data['chargeMode']);
                $result['deviceState'] = $data['deviceState'];
                $result['thisFlow'] = $data['thisFlow'] === 'ffff' ? 0 : hexdec($data['thisFlow']);
                $result['addFlow'] = hexdec($data['addFlow']);
                $result['addTime'] = hexdec($data['addTime']);
                $result['restFlow'] = hexdec($data['restFlow']);
                $result['restTime'] = hexdec($data['restTime']);
                $result['usedFlow'] = hexdec($data['usedFlow']);
                $result['usedTime'] = hexdec($data['usedTime']);

                $result['rawTDS'] = hexdec($data['rawTDS']);
                $result['purityTDS'] = hexdec($data['purityTDS']);

                $result['F1Flux'] = hexdec($data['F1Flux']);
                $result['F2Flux'] = hexdec($data['F2Flux']);
                $result['F3Flux'] = hexdec($data['F3Flux']);
                $result['F4Flux'] = hexdec($data['F4Flux']);
                $result['F5Flux'] = hexdec($data['F5Flux']);
                $result['F1FluxMax'] = hexdec($data['F1FluxMax']);
                $result['F2FluxMax'] = hexdec($data['F2FluxMax']);
                $result['F3FluxMax'] = hexdec($data['F3FluxMax']);
                $result['F4FluxMax'] = hexdec($data['F4FluxMax']);
                $result['F5FluxMax'] = hexdec($data['F5FluxMax']);
                return $result;
            }

        }

        if (isset($data['cTrl'])) {
            unset($data['cTrl']);
        }
        if (isset($data['programCode'])) {
            unset($data['programCode']);
        }
        return $data;
    }

    /**
     * 调试信息
     *
     * @param int $fd
     * @param int $type
     * @param mixed $message
     */
    public static function debug($fd, $type, $message)
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        switch ($type) {
            case 1:
                $typeDesc = '收到';
                break;
            case 2:
                $typeDesc = '发送';
                break;
            case 3:
            default:
                $typeDesc = '调试';
                break;
        }
        printf("%s 当前fd: %s, %s数据:%s\n", date("Y-m-d H:i:s"), $fd, $typeDesc, $message);
    }

    /**
     * 原水值
     *
     * @param $value
     * @return string
     */
    public static function getRawTdsLevel($value): string
    {
        if ($value < 100) {
            return '优良';
        } else if ($value < 300) {
            return '一般';
        } else if ($value < 600) {
            return '较差';
        } else {
            return '差';
        }
    }

    /**
     * 纯水值
     *
     * @param $value
     * @return string
     */
    public static function getPurityTdsLevel($value): string
    {
        if ($value < 100) {
            return '优良';
        } else if ($value < 300) {
            return '一般';
        } else if ($value < 600) {
            return '较差';
        } else {
            return '差';
        }
    }

    /**
     * 设备状态Maps
     */
    public static function deviceStates()
    {
        return [
            '00' => '初始化',
            '01' => '运行中',
            '02' => '欠费',
            '03' => '制水故障',
            '04' => '关机',
            '05' => '漏水',
            '06' => '待激活',
            '07' => '流量计故障',
            '08' => '频发故障',
            '09' => '制水',
            '10' => '冲洗',
            '11' => '缺水',
            '12' => '锁定',
            '13' => '保鲜',
            '14' => '暂停',//纯水溢出
            '30' => '溢水故障',
            '31' => '浮球异常',
            '32' => '加热超长',
            '33' => 'TDS报警',
            '34' => '放水故障',
            '35' => '热水故障',
            '36' => '加热干烧',
            '37' => '凉水故障',
            '55' => '水箱液位过低',
            '85' => '共享取水中'
        ];
    }

    /**
     * 设备中文名称
     *
     * @param $key
     * @return mixed
     */
    public static function deviceState($key)
    {
        $all = self::deviceStates();
        if (!empty($all[$key])) {
            return $all[$key];
        }
        return $key;
    }

    /**
     * @param $deviceId
     * @return string
     */
    public static function getWSKey($deviceId): string
    {
        return sprintf("ws_%s", $deviceId);
    }

    /**
     * @param $serverFd
     * @return string
     */
    public static function getFdKey($serverFd): string
    {
        return sprintf("fd_%s", $serverFd);
    }

    /**
     * 生成网络通讯ID
     *
     * @param int $type
     * @return string
     */
    public static function generateCommunicationId($type = 1)
    {
        if ($type === 1) {
            return date("his") . mt_rand(1000, 9999);
        }
    }
}