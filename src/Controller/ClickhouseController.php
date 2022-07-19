<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Yii\Yii;

class ClickhouseController extends GoController
{


    /**
     * @GetMapping("clickhouse")
     */
    public function clickhouse()
    {
        /** @var \ESD\Yii\Clickhouse\Connection $client */
        $client = Yii::$app->clickhouse;
        $sql = 'select * from stat where counter_id=:counter_id';
        $client
            ->createCommand("SELECT 1 + 1")
            ->queryAll();

    }
}