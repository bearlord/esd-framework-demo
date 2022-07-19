<?php

use App\Assets\MainAsset;
use ESD\Yii\Yii;
use ESD\Yii\Widgets\Menu;
use ESD\Yii\Helpers\Url;

MainAsset::register($this);

?>

<h2>
    <?= $hello ?>
</h2>


<?php

echo Menu::widget([
    'items' => [
        // Important: you need to specify url as 'controller/action',
        // not just as 'controller' even if default action is used.
        ['label' => 'Home', 'url' => '/site/index'],
        // 'Products' menu item will be selected as long as the route is 'product/index'
        ['label' => 'Products', 'url' => '/product/index', 'items' => [
            ['label' => 'New Arrivals', 'url' => '/product/index'],
            ['label' => 'Most Popular', 'url' => '/product/index'],
        ]],
        ['label' => 'Login', 'url' => ['site/login'], 'visible' => true],
    ],
]);
?>

<ul>
    <li>
        绝对地址 无协议：<?= Url::to('/a/c') ?>
    </li>

    <li>
        相对地址 无协议：<?= Url::to(['a/c', 'id' => 4], false) ?>
    </li>

    <li>
        相对地址 有协议：<?= Url::to(['a/c', 'id' => 4], true) ?>
    </li>
</ul>

