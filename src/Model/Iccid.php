<?php

namespace App\Model;

use ESD\Yii\Db\ActiveRecord;
use ESD\Yii\Db\Query;
use ESD\Yii\Yii;

/**
 * This is the model class for table "{{%iccid}}".
 *
 * @property int $id
 * @property int|null $manu_id 厂商ID
 * @property string|null $ICCID ICCID
 * @property string|null $IMEI IMEI
 * @property string|null $sim_type SIM卡类型
 * @property int|null $preset_device_id 默认ICCID
 * @property string|null $onenet_device_id OneNet设备ID
 * @property string|null $entry_time 入库时间
 * @property string|null $renew_time 续费时间
 * @property int|null $filter_charge_mode 滤芯计算方式
 * @property int|null $flowmeter 流量计
 * @property int|null $faucet 水龙头
 * @property int|null $water_pump 水泵
 * @property int|null $maintenance 维修时间
 * @property int|null $created_at
 * @property int|null $updated_at
 */
class Iccid extends ActiveRecord
{

    /**
     * Iccid file
     *
     * @var string
     */
    public $iccid_file;

    public function behaviors()
    {
        return [
            [
                'class' => \ESD\Yii\Behaviors\TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => time(),
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%iccid}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['manu_id', 'preset_device_id', 'filter_charge_mode', 'flowmeter', 'faucet', 'water_pump', 'maintenance'], 'integer'],
            [['entry_time', 'renew_time'], 'safe'],
            [['ICCID', 'IMEI'], 'string', 'max' => 40],
            [['sim_type'], 'string', 'max' => 30],
            [['manu_id', 'iccid_file', 'sim_type'], 'required'],
            ['iccid_file', 'file'],
            [['filter_charge_mode', 'flowmeter', 'faucet', 'water_pump', 'maintenance'], 'safe']
        ];
    }

    public function scenarios()
    {
        return [
            'search' => [
                'manu_id', 'ICCID', 'sim_type'
            ],
            'create' => [
                'manu_id', 'ICCID', 'sim_type', 'iccid_file', 'filter_charge_mode', 'flowmeter', 'faucet', 'water_pump', 'maintenance'
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'manu_id' => '厂商ID',
            'ICCID' => 'ICCID',
            'IMEI' => 'IMEI',
            'sim_type' => 'SIM卡类型',
            'preset_device_id' => '默认ICCID',
            'entry_time' => '入库时间',
            'renew_time' => '续费时间',
            'filter_charge_mode' => '滤芯计算方式',
            'flowmeter' => '流量计',
            'faucet' => '水龙头',
            'water_pump' => '水泵',
            'maintenance' => '维修时间',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * 获取预设设备的信息
     *
     * @param int $presetDeviceId
     * @return array|ActiveRecord|null
     * @throws \Exception
     */
    public function getInfoByPresetDeviceId(int $presetDeviceId)
    {
        $tableName = self::tableName();
        return Yii::$app->getDb()
            ->createCommand("SELECT * FROM {$tableName} WHERE preset_device_id = {$presetDeviceId}")
            ->query()
            ->read();
    }

    /**
     * @param string $iccid
     * @return array
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function getInfoByIccid(string $iccid)
    {
        $tableName = self::tableName();
        return Yii::$app->getDb()
            ->createCommand("SELECT * FROM {$tableName} WHERE ICCID = '{$iccid}'")
            ->query()
            ->read();
    }

    /**
     * @param string $iccid
     * @return array
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function getInfoByImei(string $imei)
    {
        $tableName = self::tableName();
        return Yii::$app->getDb()
            ->createCommand("SELECT * FROM {$tableName} WHERE IMEI = '{$imei}'")
            ->query()
            ->read();
    }

    /**
     * 根据ICCID或者IMEI获取信息
     *
     * @param string $iccid
     * @param string $imei
     * @return array|ActiveRecord|null
     * @throws \ESD\Yii\Db\Exception
     * @throws \Exception
     */
    public function getInfoByIccidImei(string $iccid, string $imei)
    {
        $tableName = self::tableName();
        return Yii::$app->getDb()
            ->createCommand("SELECT * FROM {$tableName} WHERE (dependency_type = 1 AND ICCID = '{$iccid}') OR (dependency_type = 2 AND IMEI = '{$imei}')")
            ->query()
            ->read();
    }

    /**
     * 更新IMEI
     *
     * @param int $id
     * @param string $imei
     * @throws \Exception
     */
    public function updateImei(int $id, string $imei)
    {
        $tableName = self::tableName();
        Yii::$app->getDb()->createCommand()->update($tableName, [
            'IMEI' => $imei
        ], [
            'id' => $id
        ])
        ->execute();
    }

    /**
     * @param int $id
     * @param string $iccid
     * @throws \Exception
     */
    public function updateIccid(int $id, string $iccid)
    {
        $tableName = self::tableName();
        Yii::$app->getDb()->createCommand()->update($tableName, [
            'ICCID' => $iccid
        ], [
            'id' => $id
        ])
        ->execute();
    }

    /**
     * @param int $id
     * @param string $imei
     * @param string $iccid
     * @throws \Exception
     */
    public function updateImeiOrIccid(int $id, string $imei, string $iccid)
    {
        $tableName = self::tableName();
        Yii::$app->getDb()->createCommand()->update($tableName, [
            'IMEI' => $imei,
            'ICCID' => $iccid
        ], [
            'id' => $id
        ])
        ->execute();
    }

}
