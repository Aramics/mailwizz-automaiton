<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the model class for table "reply_tracker_log".
 *
 * The followings are the available columns in table 'reply_tracker_log':
 * @property integer $log_id
 * @property integer $customer_id
 * @property integer $campaign_id
 * @property integer $subscriber_id
 * @property integer $message_id
 * @property integer $server_id
 * @property string $message
 * @property string $from_name
 * @property string $from_email
 * @property string $reply_date
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property Customer $customer
 * @property Campaign $campaign
 * @property ReplyTrackerExtInbound $server
 */
class ReplyTrackerExtLogModel extends ActiveRecord
{
    public $customer_id;

    public $list_id;

    public $purifier;
    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return '{{reply_tracker_log}}';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $rules = array(
            ['campaign_id, message','required']
        );

        return CMap::mergeArray($rules, parent::rules());
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        $relations = array(
            'campaign' => array(self::BELONGS_TO, 'Campaign', 'campaign_id'),
            'subscriber' => array(self::BELONGS_TO, 'ListSubscriber', 'subscriber_id'),
        );
        return CMap::mergeArray($relations, parent::relations());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        $labels = array(
            'log_id' => Yii::t('campaigns', 'Log'),
            'campaign_id' => Yii::t('campaigns', 'Campaign'),
            'subscriber_id' => Yii::t('campaigns', 'Subscriber'),
            'message' => Yii::t('campaigns', 'Message'),
            'from_email' => Yii::t('campaigns', 'From Email'),
            'from_name' => Yii::t('campaigns', 'From Name'),
            'reply_date' => Yii::t('campaigns', 'Reply Date'),
        );

        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function customerSearch()
    {
        $criteria = new CDbCriteria;
        $criteria->compare('campaign_id', (int)$this->campaign_id);
        
        return new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $this->paginationOptions->getPageSize(),
                'pageVar' => 'page',
            ),
            'sort' => array(
                'defaultOrder' => array(
                    'log_id' => CSort::SORT_DESC,
                ),
            ),
        ));
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function search()
    {
        $criteria = new CDbCriteria;
        $criteria->select = 't.*';
        $criteria->with = array(
            'campaign' => array(
                'select' => 'campaign.name, campaign.list_id, campaign.segment_id, campaign.campaign_uid',
                'joinType' => 'INNER JOIN',
                'together' => true,
                'with' => array(
                    'list' => array(
                        'select' => 'list.name,list.list_uid',
                        'joinType' => 'INNER JOIN',
                        'together' => true,
                    ),
                    'customer' => array(
                        'select' => 'customer.customer_id, customer.first_name, customer.last_name',
                        'joinType' => 'INNER JOIN',
                        'together' => true,
                    ),
                ),
            ),
            'subscriber' => array(
                'select' => 'subscriber.email,subscriber.subscriber_uid',
                'joinType' => 'INNER JOIN',
                'together' => true,
            ),
        );

        if ($this->customer_id && is_numeric($this->customer_id)) {
            $criteria->with['campaign']['with']['customer'] = array_merge($criteria->with['campaign']['with']['customer'], array(
                'condition' => 'customer.customer_id = :customerId',
                'params' => array(':customerId' => $this->customer_id),
            ));
        } elseif ($this->customer_id && is_string($this->customer_id)) {
            $criteria->with['campaign']['with']['customer'] = array_merge($criteria->with['campaign']['with']['customer'], array(
                'condition' => 'CONCAT(customer.first_name, " ", customer.last_name) LIKE :customerName',
                'params' => array(':customerName' => '%' . $this->customer_id . '%'),
            ));
        }

        if ($this->campaign_id && is_numeric($this->campaign_id)) {
            $criteria->with['campaign'] = array_merge($criteria->with['campaign'], array(
                'condition' => 'campaign.campaign_id = :campaignId',
                'params' => array(':campaignId' => $this->campaign_id),
            ));
        } elseif ($this->campaign_id && is_string($this->campaign_id)) {
            $criteria->with['campaign'] = array_merge($criteria->with['campaign'], array(
                'condition' => 'campaign.name LIKE :campaignName',
                'params' => array(':campaignName' => '%' . $this->campaign_id . '%'),
            ));
        }

        if ($this->list_id && is_numeric($this->list_id)) {
            $criteria->with['campaign']['with']['list'] = array_merge($criteria->with['campaign']['with']['list'], array(
                'condition' => 'list.list_id = :listId',
                'params' => array(':listId' => $this->list_id),
            ));
        } elseif ($this->list_id && is_string($this->list_id)) {
            $criteria->with['campaign']['with']['list'] = array_merge($criteria->with['campaign']['with']['list'], array(
                'condition' => 'list.name LIKE :listName',
                'params' => array(':listName' => '%' . $this->list_id . '%'),
            ));
        }

        if ($this->subscriber_id && is_numeric($this->subscriber_id)) {
            $criteria->with['subscriber'] = array_merge($criteria->with['subscriber'], array(
                'condition' => 'subscriber.subscriber_id = :subscriberId',
                'params' => array(':subscriberId' => $this->subscriber_id),
            ));
        } elseif ($this->subscriber_id && is_string($this->subscriber_id)) {
            $criteria->with['subscriber'] = array_merge($criteria->with['subscriber'], array(
                'condition' => 'subscriber.email LIKE :subscriberId',
                'params' => array(':subscriberId' => '%' . $this->subscriber_id . '%'),
            ));
        }

        $criteria->compare('t.message', $this->message, true);
        
        $criteria->order = 't.log_id DESC';

        return new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $this->paginationOptions->getPageSize(),
                'pageVar' => 'page',
            ),
            'sort' => array(
                'defaultOrder' => array(
                    't.log_id' => CSort::SORT_DESC,
                ),
            ),
        ));
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function searchLight()
    {
        $criteria = new CDbCriteria;
        $criteria->order = 't.log_id DESC';

        return new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
            'pagination' => array(
                'pageSize' => $this->paginationOptions->getPageSize(),
                'pageVar' => 'page',
            ),
            'sort' => array(
                'defaultOrder' => array(
                    't.log_id' => CSort::SORT_DESC,
                ),
            ),
        ));
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

    /**
     * Returns the array of campaigns belonging to active customer.
     *
     * @param null
     * @return (array) [campaign_id=>campaignName]
     */
    public function getCustomerCampaigns()
    {
        $customer_id = (int)Yii::app()->customer->getId();
        $criteria=new CDbCriteria;
        $criteria->compare('customer_id', $customer_id);
        $campaigns = Campaign::model()->findAll($criteria);
        $campaigns_html = [];
        foreach ($campaigns as $key => $value) {
            $campaigns_html[$value->campaign_id] = $value->name;
        }
        return $campaigns_html;
    }
    
    /**
     * Use this method to clean user reply off malacious scritps.
     * The reply are cleaned already before saving but re-escape before displaying
     *
     * @param $data string
     *
     * @return (string)
    **/
    public function purify($data)
    {
        if ($this->purifier) {
            return $this->purifier->purify($data);
        }
        $purifier = new CHtmlPurifier();
        $purifier->options = array('HTML.Allowed'=>'p,div,b,i,a[href],u,ul,ol,li,h1,h2,h3,h4,h5,h6,br');
        $this->purifier = $purifier;
        return $purifier->purify($data);
    }
}