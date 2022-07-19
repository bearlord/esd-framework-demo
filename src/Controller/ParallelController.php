<?php

namespace App\Controller;

use ESD\Coroutine\Coroutine;
use ESD\Parallel\Parallel;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Snowflake\IdGenerator;
use ESD\Snowflake\MetaGenerator\RandomMilliSecondMetaGenerator;

/**
 * @RestController("parallel")
 */
class ParallelController extends \ESD\Go\GoController
{

    /**
     * @GetMapping("t1")
     * @ResponseBody
     */
    public function actionT1()
    {
        $parallel = new Parallel();

        for ($i = 1; $i <= 10; $i++) {
            $parallel->add(function () use ($i) {
                Coroutine::sleep(mt_rand(1, 9) / 100);
                return sprintf("%d %s %s\n", $i, microtime(true), (new IdGenerator(new RandomMilliSecondMetaGenerator(time())))->generate());
            });
        }

        $result = $parallel->wait();

        foreach ($result as $key => $value) {
            printf("%s => %s\n", $key, $value);
        }
        return $result;
    }
}