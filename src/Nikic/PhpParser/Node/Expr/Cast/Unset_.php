<?php declare(strict_types=1);

namespace ESD\Nikic\PhpParser\Node\Expr\Cast;

use ESD\Nikic\PhpParser\Node\Expr\Cast;

class Unset_ extends Cast
{
    public function getType() : string {
        return 'Expr_Cast_Unset';
    }
}
