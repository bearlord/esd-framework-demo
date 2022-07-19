<?php

namespace App\Controller;

use App\Actor\ManActor;
use App\Actor\WomanActor;
use App\Model\ContactForm;
use app\models\User;
use ESD\Core\DI\DI;
use ESD\Core\Message\Message;
use ESD\Core\Server\Beans\Http\Cookie;
use ESD\Core\Server\Server;
use ESD\Go\GoController;
use ESD\Plugins\Actor\Actor;
use ESD\Plugins\Actor\ActorManager;
use ESD\Plugins\Actor\ActorMessage;
use ESD\Plugins\Amqp\GetAmqp;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\PostMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use DI\Annotation\Inject;
use ESD\Plugins\Blade\Blade;
use ESD\Plugins\Redis\GetRedis;
use ESD\Yii\Db\Connection;
use ESD\Yii\Db\Expression;
use ESD\Yii\Db\Query;
use ESD\Yii\Plugin\Pdo\GetPdo;
use ESD\Yii\Plugin\Pdo\PdoPools;
use ESD\Yii\Yii;

/**
 * @RestController()
 * Class Index
 * @package ESD\Plugins\EasyRoute
 */
class Index extends GoController
{

    use GetRedis;
    use GetAmqp;
    use GetPdo;

    /**
     * @Inject()
     * @var Blade
     */
    protected $blade;

    /**
     * @GetMapping("/")
     * @return string
     */
    public function index()
    {
        return $this->blade->render("app::welcome");
    }

    /**
     * @GetMapping("hello")
     * @return string
     */
    public function actionHello()
    {
        return "hello";
    }

    /**
     * @GetMapping("actor")
     */
    public function actionActor()
    {
        if (!ActorManager::getInstance()->hasActor(WomanActor::class)) {
            Actor::create(WomanActor::class, [
                'name' => 'lucy'
            ]);
        }

        if (!ActorManager::getInstance()->hasActor(ManActor::class)) {
            Actor::create(ManActor::class, [
                'name' => 'jack'
            ]);
        }

        $this->response->withStatus(200)->end();
    }

    /**
     * @GetMapping("woman")
     * @throws \ESD\Plugins\Actor\ActorException
     */
    public function actionWoman()
    {
        $womanActor = Actor::getProxy(WomanActor::class);

        $message = new ActorMessage([
            'act' => 'hello',
            'desc' => '下午见'
        ]);
        $womanActor->sendMessage($message);

    }

    /**
     * @GetMapping("foo")
     */
    public function foo()
    {
        $content = 'foo';
        $content2 = Yii::$app->runRoute('a/b/c');
        return $content . $content2;
    }

    /**
     * @GetMapping("a/b/c")
     */
    public function bar()
    {
        return 'bar';
    }

    /**
     * @GetMapping("validate")
     * @ResponseBody()
     * @return mixed
     */
    public function actionValidate()
    {
        $post = [
            'name' => 'zhangsan',
            'email' => 'notemail'
        ];

        Yii::$app->setLanguage('de');
        $contactForm = new ContactForm();
        $contactForm->setScenario('register');
        $contactForm->setAttributes($post);
        $contactForm->validate();
        $error[] = $contactForm->errors;

        Yii::$app->setLanguage('zh-CN');
        $contactForm = new ContactForm();
        $contactForm->setScenario('signin');
        $contactForm->setAttributes($post, true);
        $contactForm->validate();
        $error[] = $contactForm->errors;

        return $error;

    }


    /**
     * @GetMapping("pdo-break")
     * @ResponseBody
     */
    public function actionPdoBreak()
    {
        try {
            $row = (new Query())
                ->from("p_customer")
                ->where([
                    'id' => 12
                ])
                ->one();
            return $row;
        } catch (\Exception $exception) {
            var_dump($exception->getCode(), $exception->getMessage());
            return [
                $exception->getCode(),
                $exception->getMessage(),
                $exception->getTrace()
            ];
        }

    }

    /**
     * @GetMapping("insert")
     * @ResponseBody
     */
    public function actionInsert()
    {
        (new Query())->createCommand()
            ->insert('p_customer', [
                'manu_pk_id' => 9,
                'wx_id' => mt_rand(100, 999),
                'customer_name' => mt_rand(100, 999),
                'customer_username' => mt_rand(100, 999),
                'customer_password_hash' => mt_rand(100, 999),
            ])
            ->execute();
    }

}












