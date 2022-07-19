<?php

namespace App\Model\Mqtt;

use ESD\Yii\Yii;

class ClientSubscribe extends \ESD\Yii\Mongodb\ActiveRecord
{
    /**
     * @return string
     */
    public static function collectionName()
    {
        return "esd_client_subscribe";
    }

    /**
     * @param string $clientId
     * @param string $topic
     * @return bool
     * @throws \ESD\Yii\Db\Exception
     */
    protected function isSubscribed(string $clientId, string $topic): bool
    {
        $collection = Yii::$app->getMongodb()->getCollection(self::collectionName());
        $item = $collection
            ->findOne([
                "client_id" => $clientId,
                "topic" => $topic
            ]);
        if ($item) {
            return true;
        }
        return false;
    }

    /**
     * @param string $clientId
     * @param string $topic
     * @param array $option
     * @return bool
     * @throws \ESD\Yii\Db\Exception
     * @throws \ESD\Yii\Mongodb\Exception
     */
    public function subscribe(string $clientId, string $topic, array $option): bool
    {
        $isSubscribed = $this->isSubscribed($clientId, $topic);
        if ($isSubscribed) {
            return true;
        }

        $insertData = [
            "client_id" => $clientId,
            "topic" => $topic,
            "option" => $option,
            "created_at" => time(),
            "updated_at" => time()
        ];
        $collection = Yii::$app->getMongodb()->getCollection(self::collectionName());
        $collection->insert($insertData);

        return true;
    }

    /**
     * @param string $clientId
     * @param string $topic
     * @return bool|int
     * @throws \ESD\Yii\Db\Exception
     * @throws \ESD\Yii\Mongodb\Exception
     */
    public function unsubscribe(string $clientId, string $topic)
    {
        $collection = Yii::$app->getMongodb()->getCollection(self::collectionName());
        return $collection
            ->remove([
                "client_id" => $clientId,
                "topic" => $topic
            ]);
    }

    /**
     * @param string $topic
     * @return array
     * @throws \ESD\Yii\Db\Exception
     */
    public function getItemsByTopic(string $topic)
    {
        $collection = Yii::$app->getMongodb()->getCollection(self::collectionName());
        return $collection
            ->find([
                "topic" => $topic
            ])
            ->toArray();
    }


}