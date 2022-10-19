<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\Redis\GetRedis;
use ESD\Yii\Yii;

/**
 * @RestController("redis")
 */
class RedisController extends GoController
{
    use GetRedis;

    /**
     * @GetMapping("cache")
     * @return void
     */
    public function actionCache()
    {
        printf("getDbNum:%d\n", $this->redis()->getDbNum());
        $this->redis()->set('a', 100);
        var_dump($this->redis()->get('a'));

        printf("getDbNum:%d\n", $this->redis()->getDbNum());
        Yii::$app->getCache()->set('b', 200);
        var_dump(Yii::$app->getCache()->get('b'));

        printf("getDbNum:%d\n", $this->redis()->getDbNum());
        $this->redis()->set('c', 300);
        var_dump($this->redis()->get('c'));
        printf("getDbNum:%d\n", $this->redis()->getDbNum());
    }
}