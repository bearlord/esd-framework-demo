<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Yii\Db\Query;
use ESD\Yii\Yii;
use PDO;

/**
 * @RestController()
 */
class ShowCaseController extends GoController
{
    /**
     * @GetMapping("defines")
     * @ResponseBody()
     * @return array
     */
    public function actionDefines()
    {
        /**
         *
        PARAM_TAOS_TIMESTAMP	6009
        PARAM_TAOS_BOOL	6001
        PARAM_TAOS_TINYINT	6002
        PARAM_TAOS_SMALLINT	6003
        PARAM_TAOS_INT	6004
        PARAM_TAOS_BIGINT	6005
        PARAM_TAOS_FLOAT	6006
        PARAM_TAOS_DOUBLE	6007
        PARAM_TAOS_BINARY	6008
        PARAM_TAOS_NCHAR	6010
        PARAM_TAOS_JSON	6015
         */

        return [
            'PARAM_TAOS_TIMESTAMP' => PDO::PARAM_TAOS_TIMESTAMP,
            'PARAM_TAOS_BOOL' => PDO::PARAM_TAOS_BOOL,
            'PARAM_TAOS_TINYINT' => PDO::PARAM_TAOS_TINYINT,
            'PARAM_TAOS_SMALLINT' => PDO::PARAM_TAOS_SMALLINT,
            'PARAM_TAOS_INT' => PDO::PARAM_TAOS_INT,
            'PARAM_TAOS_BIGINT' => PDO::PARAM_TAOS_BIGINT,
            'PARAM_TAOS_FLOAT' => PDO::PARAM_TAOS_FLOAT,
            'PARAM_TAOS_DOUBLE' => PDO::PARAM_TAOS_DOUBLE,
            'PARAM_TAOS_BINARY' => PDO::PARAM_TAOS_BINARY,
            'PARAM_TAOS_NCHAR' => PDO::PARAM_TAOS_NCHAR,
            'PARAM_TAOS_JSON' => PDO::PARAM_TAOS_JSON,
        ];
    }

    /**
     * @GetMapping("t2")
     * @return void
     * @throws \ESD\Yii\Db\Exception
     */
    public function t2()
    {
        $taosw = Yii::$app->getDb('taos');

        $tableName = "bike.bike_log";

        $timestamp = intval(microtime(true) * 1000);
        $bikeId = 1;
        $ctrl = 're';
        $state = 12;
        $longitude = 100.1;
        $latitude = 20.2;
        $imei = '1234567890';

        (new Query())
            ->createCommand($taosw)
            ->insert($tableName, [
                'ts' => [$timestamp, PDO::PARAM_TAOS_TIMESTAMP],
                'bike_id' => [$bikeId, PDO::PARAM_TAOS_INT],
                'bike_ctrl' => [$ctrl, PDO::PARAM_TAOS_BINARY],
                'bike_state' => [$state, PDO::PARAM_TAOS_BINARY],
                'bike_longitude' => [$longitude, PDO::PARAM_TAOS_DOUBLE],
                'bike_latitude' => [$latitude, PDO::PARAM_TAOS_DOUBLE],
                'imei' => [$imei, PDO::PARAM_TAOS_BINARY]
            ])
            ->execute();
    }

}