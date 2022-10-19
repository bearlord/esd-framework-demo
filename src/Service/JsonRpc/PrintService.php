<?php

namespace App\Service\JsonRpc;

use ESD\Plugins\JsonRpc\Service;
use ESD\Yii\Base\Behavior;

class PrintService extends Service
{

    /**
     * @param $word
     * @return int
     */
    public function echoWord($word)
    {
        return printf("Echo word: %s", $word);
    }
}