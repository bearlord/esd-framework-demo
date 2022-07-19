<?php


namespace App\Controller;

use ESD\Go\GoController;
use ESD\Yii\Base\Controller;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Yii\Base\DynamicModel;
use ESD\Yii\Yii;

/**
 * @RestController("console")
 * Class Index
 * @package ESD\Plugins\EasyRoute
 */
class ConsoleController extends GoController
{

    /**
     * @GetMapping("sign")
     * @ResponseBody
     * @return array
     */
    public function actionSign()
    {
        $t1 = microtime(true);
        $dynamicModel = new DynamicModel([
            'username',
            'password'
        ]);
        $dynamicModel->addRule('username', 'required');
        $dynamicModel->addRule('password', 'required');
        $dynamicModel->addRule('username', function ($attribute) use ($dynamicModel) {
            if ($dynamicModel->username !== 'guest') {
                $dynamicModel->addError('username', '用户名不正确');
                return false;
            }
            return true;
        });
        $dynamicModel->addRule('password', function ($attribute) use ($dynamicModel) {
            $definePassword = '123456';
            $definePasswordHash = Yii::$app->security->generatePasswordHash($definePassword, 10);
            if (Yii::$app->security->validatePassword($dynamicModel->password, $definePasswordHash) === false) {
                $dynamicModel->addError('password', '密码错误');
                return false;
            }
            return true;
        });
        if (Yii::$app->request->getMethod() === 'GET') {
            $username = Yii::$app->request->input('username');
            $password = Yii::$app->request->input('password');
            $dynamicModel->setAttributes([
                'username' => $username,
                'password' => $password
            ]);
            $validate = $dynamicModel->validate();
            $t2 = microtime(true);
            if (!$validate) {
                return [
                    'code' => 200,
                    'message' => $dynamicModel->errors,
                    'time' => $t2 - $t1
                ];
            }
            return [
                'code' => 200,
                'message' => 'success',
                'time' => $t2 - $t1
            ];
        }
    }
}