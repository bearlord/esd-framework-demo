<?php

/* @var $this \ESD\Yii\Web\View */
/* @var $content string */

use ESD\Yii\Yii;
use ESD\Yii\Bootstrap4\Alert;
use ESD\Yii\Helpers\Html;
use ESD\Yii\Bootstrap4\Nav;
use ESD\Yii\Bootstrap4\NavBar;
use ESD\Yii\Widgets\Breadcrumbs;
use App\Assets\MainAsset;

MainAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body>
<?php $this->beginBody() ?>

<div class="wrap">
    <?php
    NavBar::begin([
        'brandLabel' => "My Application",
        'brandUrl' => '/',
        'options' => [
            'class' => 'navbar-expand-sm bg-light',
        ],
    ]);
    echo Nav::widget([
        'options' => ['class' => 'navbar-nav navbar-right'],
        'items' => [
            ['label' => 'Home', 'url' => '/yii/site/index'],
            ['label' => 'About', 'url' => '/yii/site/about'],
            ['label' => 'Contact', 'url' => '/yii/site/contact'],
            Yii::$app->user->isGuest ? (
            ['label' => 'Login', 'url' => '/yii/site/login']
            ) : (
                '<li>'
                . Html::beginForm('/yii/site/logout', 'post')
                . Html::submitButton(
                    'Logout (' . Yii::$app->user->identity->username . ')',
                    ['class' => 'btn btn-link logout']
                )
                . Html::endForm()
                . '</li>'
            )
        ],
    ]);
    NavBar::end();
    ?>

    <div class="container">
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p class="pull-left">&copy; My Company <?= date('Y') ?></p>

        <p class="pull-right"><?= Yii::powered() ?></p>
    </div>
</footer>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
