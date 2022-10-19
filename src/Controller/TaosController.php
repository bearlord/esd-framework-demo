<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Yii\Db\Expression;
use ESD\Yii\Db\PdoValue;
use ESD\Yii\Db\Query;
use ESD\Yii\Db\Taos\Schema;
use ESD\Yii\Yii;
use PDO;

/**
 * //@RestController("taos")
 */
class TaosController extends GoController
{

    /**
     * @GetMapping("create-database")
     */
    public function actionCreateDatabase()
    {
        $taosdb = Yii::$app->getDb('taos');

        $seq = $this->request->input('seq');
        if (!$seq) {
            $seq = 100;
        }

        $databaseName = "demo_" . $seq;

        (new Query())->createCommand($taosdb)
            ->setRawSql("CREATE DATABASE {$databaseName} KEEP 3650 DAYS 365 precision 'us'")
            ->execute();
    }

    /**
     * @GetMapping("create-table")
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function actionCreateTable()
    {
        $taosdb = Yii::$app->getDb('taos');

        $seq = $this->request->input('seq');
        if (!$seq) {
            $seq = 100;
        }
        $tableName = "device_log_" . $seq;

        return (new Query())
            ->createCommand($taosdb)
            ->createTable($tableName, [
                "created_timestamp" => Schema::TYPE_TIMESTAMP,
                'device_id' => Schema::TYPE_INT,
                'ctrl' => Schema::TYPE_NCHAR . "(20)",
                'device_state' => Schema::TYPE_NCHAR . "(20)"
            ])
            ->execute();
    }

    /**
     * @GetMapping("create-stable")
     * @ResponseBody
     * @return string
     * @throws \Exception
     */
    public function actionCreateStable()
    {
        $taosdb = Yii::$app->getDb('taos');
        $tableName = "p_meters";

        return (new Query())
            ->createCommand($taosdb)
            ->createSTable($tableName, [
                'ts' => 'TIMESTAMP',
                'current ' => 'FLOAT',
                'voltage' => 'INT',
                'phase' => 'FLOAT'
            ], [
                'location' => 'binary(64)',
                'groupid' => 'int'
            ])
            ->execute();
    }

    /**
     * @GetMapping("create-subtable")
     * @ResponseBody
     * @return int
     * @throws \ESD\Yii\Db\Exception
     */
    public function actionCreateSubTable()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = 'p_d0';
        $stableName = "p_meters";

        return (new Query())
            ->createCommand($taosdb)
            ->createSubTable($tableName, $stableName, [
                "shanghai", 1
            ])
            ->execute();
    }

    /**
     * @GetMapping("insert-subtable")
     * @ResponseBody
     */
    public function actionInsertSubTable()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = 'p_d14';
        $stableName = "p_meters";

        for ($i = 0; $i < 10000; $i++) {
            $t1 = intval(microtime(true) * 1000 * 1000);
            $res = (new Query())
                ->createCommand($taosdb)
                ->insertUsingSTable($tableName, [
                    'ts' => [$t1, PDO::PARAM_TAOS_TIMESTAMP],
                    'current' => [mt_rand(100, 9990) / 100, PDO::PARAM_TAOS_FLOAT],
                    'voltage' => [mt_rand(10, 999), PDO::PARAM_TAOS_INT],
                    'phase' => [mt_rand(100, 9990) / 100, PDO::PARAM_TAOS_FLOAT],
                ], $stableName, [
                    "beijing", 4
                ])
                ->execute();
        }


        return $res;
    }

    /**
     * @GetMapping("alter")
     *
     * @return int
     * @throws \ESD\Yii\Db\Exception
     */
    public function actionAlter()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = "device_log_1";

        return (new Query())
            ->createCommand($taosdb)
            ->alterColumn($tableName, "ta", "NCHAR(32)")
            ->execute();
    }

    /**
     * @GetMapping("insert2")
     * @ResponseBody
     * @return int
     * @throws \ESD\Yii\Db\Exception
     */
    public function actionInsert2()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = "device_log_2";

        $t1 = intval(microtime(true) * 1000);
        $res = (new Query())
            ->createCommand($taosdb)
            ->insert($tableName, [
                'created_timestamp' => [$t1, PDO::PARAM_TAOS_TIMESTAMP],
                'device_id' => [1002, PDO::PARAM_TAOS_INT],
                'ctrl' => ['boot', PDO::PARAM_TAOS_NCHAR],
                'device_state' => ['normal', PDO::PARAM_TAOS_NCHAR]
            ])
            ->execute();
        return $res;
    }

    /**
     * @GetMapping("insert3")
     * @ResponseBody
     * @return int
     * @throws \ESD\Yii\Db\Exception
     */
    public function actionInsert3()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = "device_log_2";

        $t1 = intval(microtime(true) * 1000);

        goWithContext(function () use ($taosdb, $tableName, $t1){
            $res = (new Query())
                ->createCommand($taosdb)
                ->insert($tableName, [
                    'created_timestamp' => new PdoValue($t1, PDO::PARAM_TAOS_TIMESTAMP),
                    'device_id' => new PdoValue(1002, PDO::PARAM_TAOS_INT),
                    'ctrl' => new PdoValue('boot', PDO::PARAM_TAOS_NCHAR),
                    'device_state' => new PdoValue('normal', PDO::PARAM_TAOS_NCHAR)
                ])
                ->execute();
        });
    }

    /**
     * @GetMapping("query2")
     * @ResponseBody
     * @throws \Exception
     */
    public function actionQuery2()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = "device_log_2";

        $t1 = intval(strtotime('2022-02-04 02:00:00') * 1000);
        $res = (new Query())
            ->from($tableName)
            ->where([
                '<=', 'created_timestamp', $t1
            ])
            ->limit(5)
            ->all($taosdb);

        return $res;
    }

    /**
     * @GetMapping("query3")
     * @ResponseBody
     * @return array
     * @throws \Exception
     */
    public function actionQuery3()
    {
        $taosdb = Yii::$app->getDb('taos');

        $tableName = "demo_001.log_001";

        $res = (new Query())
            ->from($tableName)
            ->limit(50)
            ->all($taosdb);

        return $res;
    }


    /**
     * @GetMapping("query")
     * @ResponseBody
     */
    public function actionQuery()
    {
        $taosdb = Yii::$app->getDb('taos');

        $row = (new Query())
            ->from('m1')
            ->limit(3)
            ->offset(2)
            ->all($taosdb);

        return $row;
    }

    /**
     * @GetMapping("meters")
     * @ResponseBody
     * @return array
     * @throws \Exception
     */
    public function actionMeters()
    {
        $taosdb = Yii::$app->getDb('taos');

        $count = (new Query())
            ->from("test.meters")
            ->where("location = :location", [
                ":location" => 'beijing'
            ])
            ->count("*", $taosdb);

        $avg = (new Query())
            ->from("test.meters")
            ->where("location = :location", [
                ":location" => 'beijing'
            ])
            ->select(new Expression("avg(current), max(voltage), min(phase)"))
            ->all($taosdb);

        return [
            'count' => $count,
            'avg' => $avg
        ];

    }

    /**
     * @GetMapping("meters2")
     * @ResponseBody
     * @return array
     * @throws \Exception
     */
    public function actionMeters2()
    {
        $taosdb = Yii::$app->getDb('taosw');

        $count = (new Query())
            ->from("test.meters")
            ->where("location = :location", [
                ":location" => 'beijing'
            ])
            ->count("*", $taosdb);

        $avg = (new Query())
            ->from("test.meters")
            ->where("location = :location", [
                ":location" => 'beijing'
            ])
            ->select(new Expression("avg(current), max(voltage), min(phase)"))
            ->all($taosdb);

        return [
            'count' => $count,
            'avg' => $avg
        ];
    }
}