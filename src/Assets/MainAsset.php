<?php

namespace App\Assets;

use ESD\Yii\Web\AssetBundle;

class MainAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/site.css'
    ];
    public $js = [
    ];
    public $depends = [
        'ESD\Yii\Web\YiiAsset',
        'ESD\Yii\Bootstrap4\BootstrapAsset',
        'ESD\Yii\Bootstrap4\BootstrapPluginAsset',
    ];
}