<?php


namespace App\Controller;


use App\Job\BaseJob;
use App\Model\UploadFileProcess;
use ESD\Go\GoController;
use ESD\Plugins\EasyRoute\Annotation\GetMapping;
use ESD\Plugins\EasyRoute\Annotation\PostMapping;
use ESD\Plugins\EasyRoute\Annotation\ResponseBody;
use ESD\Plugins\EasyRoute\Annotation\RestController;
use ESD\Yii\Helpers\FileHelper;
use ESD\Yii\Helpers\Json;
use ESD\Yii\I18n\Formatter;
use ESD\Yii\Plugin\Queue\GetQueue;
use ESD\Yii\Web\UploadedFile;
use Swoole\Coroutine\Channel;

/**
 * @RestController("queue")
 * Class Queue
 * @package App\Controller
 */
class QueueController extends GoController
{

    use GetQueue;

    /**
     * @GetMapping("test")
     * @ResponseBody()
     * @throws \Exception
     */
    public function actionTest()
    {
        $queue = $this->queue();
        //队列添加任务
        $orderSn =  date("YmdHis") . mt_rand(100, 999);
        $id  = $queue->push(new BaseJob([
            'orderSn' => $orderSn
        ]));

        return [$orderSn, $id];

//        $chan = new Channel(1);
//        goWithContext(function () use ($chan) {
//            $queue = $this->queue();
//            //队列添加任务
//            $orderSn =  date("YmdHis") . mt_rand(100, 999);
//            $id  = $queue->push(new BaseJob([
//                'orderSn' => $orderSn
//            ]));
//            $chan->push([$orderSn, $id]);
//        });
//
//        return $chan->pop();
    }

    /**
     * @PostMapping("post-test")
     * @throws \Exception
     */
    public function actionPost()
    {
        $fileProcessModel = new UploadFileProcess();
        $uploadFile = new UploadedFile();
        //设置上传保存后的相对路径
        $uploadFile->setUploadBasePath(sprintf("uploads/file/%s/", date("Ymd")));
        //模型属性file赋值
        $fileProcessModel->file = $uploadFile->instanceByName('file');
        //验证上传的文件是否符合
        if (!$fileProcessModel->validate()) {
            //显示报错信息
            var_dump($fileProcessModel->errors);
        }
        //文件名
        $fileName = sprintf("%s.%s", $uploadFile->generateFilename(), $fileProcessModel->file->extension);
        //文件保存相对路径
        $filePath = $uploadFile->getUploadBasePath() . $fileName;
        //文件保存物理路径
        $fileSavePath = $uploadFile->getUploadSavePath() . $fileName;
        //保存文件
        $fileProcessModel->file->saveAs($fileSavePath, true);
        //返回给浏览器的文件URL
        $fileUrl = $uploadFile->getFileUrl($filePath);
        $data = [
            'code' => 200,
            'message' => '上传成功',
            'data' => [
                [
                    'name' => $fileProcessModel->file->name,
                    'path' => $filePath,
                    'save_name' => $fileName,
                    'url' => $fileUrl,
                    'size' => $fileProcessModel->file->size,
                    'short_size' => (new Formatter())->asShortSize($fileProcessModel->file->size)
                ]
            ]
        ];
        $this->response
            ->setHeaders([
                'Content-Type' => 'application/json;charset=utf8'
            ])
            ->withContent(Json::encode($data))->end();
    }

}