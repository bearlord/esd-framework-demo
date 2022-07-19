<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Snowflake\IdGenerator;
use ESD\Snowflake\MetaGenerator\RandomMilliSecondMetaGenerator;

/**
 * @RestController("snowflake")
 */
class SnowflakeController extends GoController
{
    /**
     * @GetMapping("test1")
     */
    public function actionIndex()
    {
        $time = time();
        $idGenerator = new IdGenerator(new RandomMilliSecondMetaGenerator($time));

        $id = $idGenerator->generate();

        printf("id: %s, beginTimestamp: %s\n", $id, date("Y-m-d H:i:s", $time));

        $meta = $idGenerator->degenerate($id);
        var_dump($meta);
        printf("beginTimestamp: %s, %s\n\n\n", $meta->getBeginTimestamp(), date("Y-m-d H:i:s", $meta->getBeginTimestamp() / 1000));


        $arr = [];
        for ($i = 0; $i < 1000000; $i++) {
            $id = $idGenerator->generate();
            $arr[] = $id;
//            printf("%s\n", $id);
        }
        $uniqueArr = array_unique($arr);
        $count = count($uniqueArr);
        printf("%d\n", $count);
    }
}