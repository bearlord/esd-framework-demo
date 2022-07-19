<?php

namespace App\Model;

use ESD\Yii\Base\Model;

class UploadFileProcess extends Model
{
    //Upload image file
    public $image;
    //Upload attachment file
    public $file;

    public function rules(): array
    {
        return [
            [['image'], 'file', 'skipOnEmpty' => true, 'extensions' => 'jpg,jpeg,png,gif', 'checkExtensionByMimeType' => false, 'maxSize' => 2000 * 1000],
            [['file'], 'file', 'skipOnEmpty' => true, 'extensions' => 'jpg,jpeg,png,gif,zip,rar,tar,gz,bz2,doc,docx,xls,xlsx,ppt,pptx,txt,pdf,md', 'checkExtensionByMimeType' => false, 'maxSize' => 2000 * 1000]
        ];
    }

    public function attributeLabels()
    {
        return [
            'image' => '图片',
            'file' => '文件'
        ];
    }

    /**
     * Get upload base path
     *
     * @param $type
     * @param $userId
     * @return string
     */
    public function getUploadBasePath($type)
    {
        switch ($type) {
            case 'avatar':
                $userInfo = AdminUserExtra::getSessionUserInfo();
                $path = sprintf("avatar/%s/%s/", $userInfo['id'], date("Ymd"));
                break;

            case 'image':
                $path = sprintf("uploads/image/%s/", date("Y-m-d"));
                break;

            case 'file':
            default:
                $path = sprintf("uploads/file/%s/", date("Y-m-d"));
        }
        return $path;
    }

    /**
     * Generate filename
     *
     * @return string
     */
    public function generateFilename()
    {
        $name = sha1(microtime(true) . mt_rand(1000, 9999));
        return $name;
    }
}