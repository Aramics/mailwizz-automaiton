<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the model class for table "automations".
 *
 * The followings are the available columns in table 'automations':
 * @property integer $automation_id
 * @property integer $customer_id
 * @property string $title
 * @property string $trigger
 * @property string $trigger_value
 * @property string $locked
 * @property string $canvas_data
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 * @property string $last_run
 *
 * The followings are the available model relations:
 * @property Customer $customer
 */
class AutomationExtModel extends ActiveRecord
{

    /**
     * Staus flags
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_CRON_RUNNING = 'cron_running';
    const STATUS_ACTIVE = 'active';
    const STATUS_DISABLED = 'disabled';
    const STATUS_STOPPED = 'stopped';

    /**
     * @inheritDoc
     */
    public function getStatusesList(): array
    {
        return [
            self::STATUS_DRAFT       => ucfirst(t('app', self::STATUS_DRAFT)),
            self::STATUS_CRON_RUNNING   => ucfirst(t('app', self::STATUS_CRON_RUNNING)),
            self::STATUS_ACTIVE         => ucfirst(t('app', self::STATUS_ACTIVE)),
            self::STATUS_DISABLED       => ucfirst(t('app', self::STATUS_DISABLED)),
            self::STATUS_STOPPED       => ucfirst(t('app', self::STATUS_STOPPED)),
        ];
    }

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
            array('title, trigger, status, customer_id', 'safe', 'on' => 'search'),
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

        if (is_array($this->status)) {
            $criteria->addInCondition('t.status', $this->status);
        } elseif (is_string($this->status)) {
            $criteria->compare('t.status', $this->status);
        } else {
            $criteria->compare('t.status', $this->status);
        }

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
    public function getIsActive(): bool
    {
        return $this->getStatusIs(self::STATUS_ACTIVE);
    }

    /**
     * @return bool
     */
    public function getIsDisabled(): bool
    {
        return $this->getStatusIs(self::STATUS_DISABLED);
    }


    /**
     * @return bool
     */
    public function getIsDraft(): bool
    {
        return $this->getStatusIs(self::STATUS_DRAFT);
    }

    /**
     * @return bool
     */
    public function getIsStopped(): bool
    {
        return $this->getStatusIs(self::STATUS_STOPPED);
    }

    /**
     * @return bool
     */
    public function getIsLocked(): bool
    {
        return (string)$this->locked === self::TEXT_YES;
    }

    /**
     * @return bool
     */
    public function getCanBeDeleted(): bool
    {
        return $this->getCanBeUpdated() && !$this->getIsLocked();
    }

    /**
     * @return bool
     */
    public function getCanBeUpdated(): bool
    {
        return !in_array($this->status, [self::STATUS_CRON_RUNNING]);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function enable(): bool
    {
        if (!$this->getIsDisabled() && !$this->getIsDraft()) {
            return false;
        }
        return $this->saveStatus(self::STATUS_ACTIVE);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function disable()
    {
        if (!$this->getIsActive()) {
            return false;
        }
        return $this->saveStatus(self::STATUS_DISABLED);
    }


    public static function campaignBlockActionsList()
    {
        return [
            'copy'                => t('automations', 'Copy'),
            Campaign::STATUS_PAUSED       => t('automations', 'Pause'),
            Campaign::STATUS_SENDING           => t('automations', 'Mark sending'),
            Campaign::STATUS_PENDING_SENDING          => t('automations', 'Mark pending sending'),
            Campaign::STATUS_SENT          => t('automations', 'Mark sent'),
            Campaign::STATUS_DRAFT       => t('automations', 'Mark draft'),
            Campaign::STATUS_BLOCKED       => t('automations', 'Blocked'),
        ];
    }

    public function processCanvasFromCron(array $params = []): bool
    {
        /*$mutexKey = sha1('automationext' . serialize($this->getAttributes(array('automation_id', 'customer_id'))) . date('Ymd'));
        if (!Yii::app()->mutex->acquire($mutexKey, 5)) {
            return false;
        }*/

        try {
            if (!$this->getIsActive()) {
                // throw new Exception('The automation is inactive!', 1);
            }

            $logger = !empty($params['logger']) && is_callable($params['logger']) ? $params['logger'] : null;
            $extension = Yii::app()->extensionsManager->getExtensionInstance('automation');

            // put proper status
            $this->saveStatus(self::STATUS_CRON_RUNNING);


            //$filterAutoresponder = $extension->getOption('exclude_autoresponse', 'yes') !== 'no';
            $canvas = new AutomationExtCanvas($this->canvas_data);
            $trigger = $canvas->getTriggerBlock();
            $trigger_type = $trigger->getType();
            $trigger_value = $trigger->getTriggerValue();

            $subscribers_info = AutomationExtCanvasBlockGroupTrigger::getTriggerSubscribers($trigger_type, $this->last_run, $trigger_value);
            $failedList = [];
            foreach ($subscribers_info as $row) {
                try {
                    $subscriber = ListSubscriber::model()->findByPk($row->subscriber_id);
                    $canvas->run([
                        'logger' => [$this, 'lo'],
                        'subscriber' => $subscriber
                    ]);
                } catch (\Throwable $th) {
                    //throw $th;
                    $failedList[] = @$row->subscriber_id;
                }
            }
        } catch (Exception $e) {


            Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
        }

        // mark the automation as active once again
        //$this->saveStatus(self::STATUS_ACTIVE);

        //release lock
        //Yii::app()->mutex->release($mutexKey);

        return true;
    }
}