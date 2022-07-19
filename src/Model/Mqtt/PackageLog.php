<?php

namespace App\Model\Mqtt;

use ESD\Yii\Mongodb\ActiveRecord;
use ESD\Yii\Yii;

class PackageLog extends ActiveRecord
{
    /**
     * @return string
     */
    public static function collectionName()
    {
        return 'esd_package_log';
    }

    /**
     * @param int $fd
     * @param array $data
     * @return bool
     * @throws \ESD\Yii\Db\Exception
     * @throws \ESD\Yii\Mongodb\Exception
     */
    public function writeLog(int $fd, array $data): bool
    {
        $insertData = $data;

        $insertData['created_at'] = time();
        $insertData['updated_at'] = time();

        $collection = Yii::$app->getMongodb()->getCollection(self::collectionName());
        $collection->insert($insertData);

        return true;
    }
}