<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;

/**
 * @RestController("order")
 */
class OrderController extends GoController
{

    /**
     * @RequestMapping("/")
     * @ResponseBody()
     * @return array
     */
    public function actionIndex()
    {
        return [
            'name' => $this->request->input('name'),
            'server' => $this->request->getServers()
        ];
    }

}