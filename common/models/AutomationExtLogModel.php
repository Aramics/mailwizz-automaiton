<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the model class for table "reply_tracker_log".
 *
 * The followings are the available columns in table 'reply_tracker_log':
 * @property integer $log_id
 * @property integer $parent_log_id
 * @property integer $automation_id
 * @property integer $subject_id
 * @property integer $subject_type
 * @property integer $canvas_block_id
 * @property string $metadata
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property AutomationExtModel $automation
 */
class AutomationExtLogModel extends ActiveRecord
{
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{automation_logs}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            ['automation_id, canvas_block_id, status', 'required']
        );

        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'automation' => array(self::BELONGS_TO, 'AutomationExtModel', 'automation_id'),
        );
        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return CampaignBounceLog the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }
}