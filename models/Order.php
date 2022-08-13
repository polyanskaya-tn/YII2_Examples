<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * This is the model class for table "order".
 *
 * @property int $id
 * @property string $order
 * @property int $status
 * @property int $sum_order
 * @property string|null $path
 * @property int $created_at
 * @property int $updated_at
 */
class Order extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public $upFile;

    public static function tableName()
    {
        return 'order';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::className(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['order', 'status', 'sum_order'], 'required'],
            [['status', 'sum_order'], 'integer'],
            [['order', 'path'], 'string', 'max' => 255],
            [['upFile'], 'file', 'skipOnEmpty' => true, 'extensions' => ['jpeg', 'jpg', 'png']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'order' => 'Order',
            'status' => 'Status',
            'sum_order' => 'Sum Order',
            'path' => 'Path',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    /**
     * {@inheritdoc}
     * @return OrderQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new OrderQuery(get_called_class());
    }
    
    public function upload()
    {
        if ($this->validate()) {
            if ($this->upFile) {
                $path = $_SERVER['DOCUMENT_ROOT'] . "uploads/";
                $path = $path . $this->upFile->baseName . '.' . $this->upFile->extension;
                $this->upFile->saveAs($path);
                $this->path = $path;
            }            
            return true;
        } else {
            return false;
        }
    }
}
