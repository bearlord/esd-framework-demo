<?php

namespace App\Service;

use App\Service\JsonRpc\TcpDataService;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\TcpController;
use App\Service\JsonRpc\CalculatorService;
use ESD\Plugins\JsonRpc\Annotation\ResponeJsonRpc;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\PostMapping;
use ESD\Plugins\EasyRoute\Annotation\RequestRawJson;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\JsonRpc\ServiceController;

/**
 * @TcpController(portNames={"jsonRpc"})
 *
 * Class JsonTcpServiceController
 * @package App\Service
 */
class JsonTcpServiceController extends ServiceController
{
    protected $serviceProvider = [
        'CalculatorService' => CalculatorService::class,
        'TcpDataService' => TcpDataService::class,
    ];

    /**
     * @RequestMapping("process")
     * @ResponseBody()
     */
    public function process()
    {
        var_dump($this->clientData->getClientInfo());
        return 'ok';
    }

}