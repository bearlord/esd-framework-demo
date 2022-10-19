<?php

namespace App\Service\JsonRpc;

class TcpDataService extends \ESD\Plugins\JsonRpc\Service
{

    public function process($fd, $clientData, $remoteIp, $remotePort)
    {
//        printf("process Data: %s\n", $data);
        $clientData = str_replace("\r\n", "", $clientData);
        printf("fd: %s, clientData: %s, remoteIp: %s, remotePort: %s\n", $fd, $clientData, $remoteIp, $remotePort);

        return  "server response ok"  . $clientData;
    }
}