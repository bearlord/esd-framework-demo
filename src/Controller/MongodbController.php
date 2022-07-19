<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;

/**
 * @RestController
 */
class MongodbController extends GoController
{

    /**
     * @GetMapping("mongodb")
     * @ResponseBody
     */
    public function mongodb()
    {
        $list = (new \ESD\Yii\Mongodb\Query())
            ->from('package_log')
            ->all();
        $jsonString = var_export($list, true);
        $this->response->withContent($jsonString)->withStatus(200)->end();

    }
}