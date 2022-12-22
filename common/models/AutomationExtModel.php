<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the model class for table "automations".
 *
 * The followings are the available columns in table 'automations':
 * @property integer $automations_id
 * @property integer $customer_id
 * @property string $title
 * @property string $trigger
 * @property string $locked
 * @property string $canvas_data
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property Customer $customer
 */
class AutomationExtModel extends BounceServer
{

    /**
     * Staus flags
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_STOPED = 'stopped';
    const STATUS_COMPLETED = 'completed';

    /**
     * @inheritdoc
     */
    public function tableName()
    {
        return '{{automations}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = array(
            array('title', 'required'),
            array('locked', 'in', 'range' => array_keys($this->getYesNoOptions())),
        );

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        $labels = array(
            'title' => Yii::t('app', 'Name of the automation'),
        );

        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return ReplyTracker static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
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

        if (!empty($this->customer_id)) {
            if (is_numeric($this->customer_id)) {
                $criteria->compare('t.customer_id', $this->customer_id);
            } else {
                $criteria->with = array(
                    'customer' => array(
                        'joinType'  => 'INNER JOIN',
                        'condition' => 'CONCAT(customer.first_name, " ", customer.last_name) LIKE :name',
                        'params'    => array(
                            ':name'    => '%' . $this->customer_id . '%',
                        ),
                    )
                );
            }
        }

        $criteria->compare('t.title', $this->title, true);
        $criteria->compare('t.trigger', $this->trigger, true);
        $criteria->compare('t.status', $this->status);

        $criteria->order = 't.last_updated ASC';

        return new CActiveDataProvider(get_class($this), array(
            'criteria'      => $criteria,
            'pagination'    => array(
                'pageSize'  => $this->paginationOptions->getPageSize(),
                'pageVar'   => 'page',
            ),
            'sort'  => array(
                'defaultOrder'  => array(
                    't.server_id' => CSort::SORT_DESC,
                ),
            ),
        ));
    }

    /**
     * Gets the meta data for a schedule.
     *
     * @param      string  $key    The key
     *
     * @return     mixed  The meta.
     */
    public function getMeta($key = '')
    {
        $meta = [];

        try {
            $meta = (array) unserialize($this->meta_data);
        } catch (\Exception $e) {
        }


        return empty($key) ? $meta : @$meta[$key];
    }


    /**
     * Sets the meta for the schedule and save.
     *
     * @param      string|int  $key    The key
     * @param      mixed  $value  The value
     *
     * @return     boolean
     */
    public function setMeta($key, $value)
    {
        $meta = $this->getMeta();
        $meta[$key] = $value;

        $this->meta_data = serialize($meta);
        return $this->save();
    }

    public function dateInUserTimeZone($date, $format = 'H:i:s')
    {
        $customer = $this->customer;
        $sourceTimeZone         = new DateTimeZone(app()->getTimeZone());
        $destinationTimeZone    = new DateTimeZone($customer->timezone);

        if ($date) {
            $dateTime = new DateTime((string)$date, $sourceTimeZone);
            $dateTime->setTimezone($destinationTimeZone);
            $date = (string)$dateTime->format($format);
        }

        return $date;
    }


    /**
     * we use this method to find subscriber by email address and list id
     * @param $list_id (integer), $email (string)
     *
     * @return object subscriber
     */
    public function findSubscriber($list_id, $email)
    {
        $criteria = new CDbCriteria();
        $criteria->compare('t.email', $email);
        $criteria->compare('t.list_id', $list_id);
        $subscriber = ListSubscriber::model()->find($criteria);

        return $subscriber;
    }

    /**
     * This method is used send mail to subscriber
     * when action send-email is activated
     *
     * @param $list_id (integer), $email (string)
     *
     * @return (object)
     */
    public function sendEmail($campaign, $subscriber, $action)
    {
        $dsParams = array('useFor' => DeliveryServer::USE_FOR_CAMPAIGNS);
        $list = $campaign->list;

        if (!($automation = DeliveryServer::pickServer(0, $campaign, $dsParams))) {
            if (!($automation = DeliveryServer::pickServer(0, $list))) {
                return false;
            }
        }


        $content = $action['content'];
        $subject = $action['subject'];

        $searchReplace = CampaignHelper::getCommonTagsSearchReplace($content, $campaign);

        $content = str_replace(array_keys($searchReplace), array_values($searchReplace), $content);
        $subject = str_replace(array_keys($searchReplace), array_values($searchReplace), $subject);

        // 1.5.3
        if (CampaignHelper::isTemplateEngineEnabled()) {
            $content = CampaignHelper::parseByTemplateEngine($content, $searchReplace);
            $subject = CampaignHelper::parseByTemplateEngine($subject, $searchReplace);
        }

        $params = array(
            'to'        => $subscriber->email,
            'fromName'  => $list->default->from_name,
            'subject'   => $subject,
            'body'      => $content,
        );

        $sent = false;
        for ($i = 0; $i < 3; ++$i) {
            if ($sent = $automation->setDeliveryFor(DeliveryServer::DELIVERY_FOR_LIST)->setDeliveryObject($list)->sendEmail($params)) {
                break;
            }
            if (!($automation = DeliveryServer::pickServer($automation->server_id, $list))) {
                break;
            }
        }

        return $sent;
    }

    /**
     * @return BounceServer|null
     * @throws CException
     */
    public function copy(): ?self
    {
        $copied = null;

        if ($this->getIsNewRecord()) {
            return null;
        }

        $transaction = db()->beginTransaction();

        try {

            /** @var AutomationExtModel $automation */
            $automation = clone $this;
            $automation->automation_id = null;
            $automation->setIsNewRecord(true);
            $automation->status       = self::STATUS_DRAFT;
            $automation->date_added   = MW_DATETIME_NOW;
            $automation->last_updated = MW_DATETIME_NOW;

            if (preg_match('/#(\d+)$/', $automation->title, $matches)) {
                $counter = (int)$matches[1];
                $counter++;
                $automation->title = (string)preg_replace('/#(\d+)$/', '#' . $counter, $automation->title);
            } else {
                $automation->title .= ' #1';
            }

            if (!$automation->save(false)) {
                throw new CException($automation->shortErrors->getAllAsString());
            }

            $transaction->commit();
            $copied = $automation;
        } catch (Exception $e) {

            $transaction->rollback();
            exit($e->getMessage());
        }

        return $copied;
    }


    /**
     * @return bool
     */
    public function getIsStopped(): bool
    {
        return $this->getStatusIs(self::STATUS_STOPED);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function stop()
    {
        if (!$this->getIsActive()) {
            return false;
        }

        return $this->saveStatus(self::STATUS_STOPED);
    }

    /**
     * @return bool
     */
    public function getIsDraft(): bool
    {
        return $this->getStatusIs(self::STATUS_DRAFT);
    }
















    public function __process(array $params = []): bool
    {
        /*$mutexKey = sha1('automationext' . serialize($this->getAttributes(array('automation_id', 'customer_id'))) . date('Ymd'));
        if (!Yii::app()->mutex->acquire($mutexKey, 5)) {
            return false;
        }*/

        try {
            if (!$this->getIsActive()) {
                // throw new Exception('The automation is inactive!', 1);
            }


            $canvas = new AutomationExtCanvas($this->canvas_data);

            $logger = !empty($params['logger']) && is_callable($params['logger']) ? $params['logger'] : null;

            // put proper status
            //$this->saveStatus(self::STATUS_CRON_RUNNING);


            $extension = Yii::app()->extensionsManager->getExtensionInstance('automation');
            //$filterAutoresponder = $extension->getOption('exclude_autoresponse', 'yes') !== 'no';

            $blocks = $canvas->getBlocks();
            $trigger_block = $canvas->getTriggerBlock();
            $trigger_block_id = $canvas->getBlockId($trigger_block);
            $isATree = $canvas->blockIsTree($trigger_block);

            $canvas->run();
            $block = $canvas->getNextBlock($trigger_block);
            var_dump($block->id);
            while ($block != null) {
                $block = $canvas->moveToNextBlock($block);
            }
            $subscriber = $params['subscriber'];
            dd($blocks);

            while ($block != null) {
                # code...
                $block_id = $canvas->getBlockId($block);
                $is_trigger = $block_id == $trigger_block_id;


                $block_id = $canvas->getBlockId($block);
                $block_type = $canvas->getBlockType($block);
                $block_group = $canvas->getBlockGroup($block);

                if (!in_array($block_group, [AutomationExtBlockGroups::ACTION, AutomationExtBlockGroups::LOGIC])) {
                    throw new Exception("Can only process action or logic block", 1);
                }

                //check if executed.
                $c_criteria = new CDbCriteria();
                if ($subscriber) {
                    $c_criteria->compare('subject_id', $subscriber->subscriber_uid);
                    $c_criteria->compare('subject_type', 'subscriber');
                }
                $c_criteria->compare('canvas_block_id', (int)$block_id);
                $c_criteria->compare('automation_id', $this->id);
                $log = AutomationExtLogModel::model()->find($c_criteria);

                if (!$log) {

                    $log = new AutomationExtLogModel();
                    $log->automation_id = $this->id;
                    $log->canvas_block_id = $block_id;
                    $log->subject_id = $subscriber ? $subscriber->subscriber_uid : NULL;
                    $log->subject_type = $subscriber ? 'subscriber' : NULL;
                    $log->status = self::STATUS_DRAFT;
                    if (!$log->save()) {
                        throw new Exception("Error saving automation:$this->id log for block:$block_id", 1);
                    }
                }

                if ($log->status == self::STATUS_CRON_RUNNING) {
                    //move to anothe branch or break;
                }


                if (!in_array($log->status, [self::STATUS_COMPLETED])) {
                    //good for processing. process either action or logic block
                    //if ($canvas->blockIsTree($block)){
                    //get the three and run. till exausted

                    if ($canvas->blockIsTree($block)) {
                    } else {
                        $tmp_block = $canvas->processBlock($block, $log); //return next block or null.
                    }
                }
                //}

                else {
                    $block = null;
                }
            }
        } catch (Exception $e) {
            if ($e->getCode() == 0) {
                Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
            }
        }

        // mark the automation as active once again
        //$this->saveStatus(self::STATUS_ACTIVE);

        //release lock
        //Yii::app()->mutex->release($mutexKey);

        return true;
    }
}