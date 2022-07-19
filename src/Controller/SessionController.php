<?php

namespace App\Controller;

use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Plugins\EasyRoute\Annotation\RequestMapping;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;

class SessionController extends GoController
{


    /**
     * @GetMapping("redis-session")
     */
    public function redisSession()
    {
        /*
        $this->redis()->set('a', 100);


        $foo = Yii::$app->cache->set('a', 100, 1000);
        */

//        error_reporting(E_ALL);
        if ($this->session->isExist()) {
            var_dump($this->session->getAttribute());
        } else {
            $this->session->create();
            $this->session->setAttribute('name', 'zhangsan');
            var_dump($this->session->getAttribute());
        }

        $this->response->withStatus(200)->end();
    }
}