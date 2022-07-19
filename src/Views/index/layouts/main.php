<?php

use ESD\Yii\Yii;
use ESD\Yii\Helpers\Html;

?>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Html::encode($this->title) ?></title>
    <?= Html::csrfMetaTags() ?>
    <?php $this->head() ?>
</head>

    <body class="fixed-sidebar full-height-layout gray-bg" style="overflow:hidden">
<?php $this->beginBody() ?>

<div class="wrapper wrapper-content">
    <?= $content ?>
</div>

<?php $this->endBody() ?>
    </body>
</html>
<?php $this->endPage() ?>
