<?php

namespace App\Service\JsonRpc;

use ESD\Plugins\JsonRpc\Service;
use ESD\Yii\Db\Query;

/**
 * Class CalculatorService
 * @package App\Service\JsonRpc
 */
class CalculatorService extends Service
{
    public function add(int $a, int $b): int
    {
        (new Query())
            ->createCommand()
            ->insert("p_calculator", [
                'a' => $a,
                'b' => $b,
                's' => $a + $b
            ])
            ->query();
        return $a + $b;
    }
}