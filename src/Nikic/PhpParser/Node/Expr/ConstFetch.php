<?php declare(strict_types=1);

namespace ESD\Nikic\PhpParser\Node\Expr;

use ESD\Nikic\PhpParser\Node\Expr;
use ESD\Nikic\PhpParser\Node\Name;

class ConstFetch extends Expr
{
    /** @var Name Constant name */
    public $name;

    /**
     * Constructs a const fetch node.
     *
     * @param Name  $name       Constant name
     * @param array $attributes Additional attributes
     */
    public function __construct(Name $name, array $attributes = []) {
        $this->attributes = $attributes;
        $this->name = $name;
    }

    public function getSubNodeNames() : array {
        return ['name'];
    }
    
    public function getType() : string {
        return 'Expr_ConstFetch';
    }
}
