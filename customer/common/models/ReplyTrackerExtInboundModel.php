<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the model class for table "reply_trackers".
 *
 * The followings are the available columns in table 'reply_trackers':
 * @property integer $server_id
 * @property integer $customer_id
 * @property string $hostname
 * @property string $username
 * @property string $password
 * @property string $service
 * @property integer $port
 * @property string $protocol
 * @property string $validate_ssl
 * @property string $locked
 * @property string $meta_data
 * @property string $status
 * @property string $date_added
 * @property string $last_updated
 *
 * The followings are the available model relations:
 * @property Customer $customer
 */
class ReplyTrackerExtInboundModel extends EmailBoxMonitor
{
    public $email_box_monitor_id;
    public $email;
    public $purifier;
    /**
     * Actions list
     */
    const ACTION_SEND_EMAIL           = 'send-email';
    const ACTION_MOVE             = 'move';
    const ACTION_COPY            = 'copy';
    const ACTION_UPDATE_SUBSCRIBER_FIELD = 'update-subscriber-field';
    const ACTION_TYPE_ACTION = 'action';
    const ACTION_TYPE_TRANSACTION = 'transaction';
    const ACTION_TYPE_KEY_VALUE = 'key-value';

    /**
     * @inheritdoc
     */
    public function tableName()
    {
        return '{{reply_trackers}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = array(
            array('hostname, username, password, port, service, protocol, validate_ssl', 'required'),

            array('hostname, username, password', 'length', 'min' => 3, 'max' => 150),
            array('port', 'numerical', 'integerOnly' => true),
            array('port', 'length', 'min' => 2, 'max' => 5),
            array('protocol', 'in', 'range' => array_keys($this->getProtocolsArray())),
            array('customer_id', 'exist', 'className' => 'Customer', 'attributeName' => 'customer_id', 'allowEmpty' => false),
            array('locked', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('hostname, username, service, port, protocol, status, customer_id', 'safe', 'on' => 'search'),
            array('conditions, search_charset', 'safe', 'on' => 'update'),
            array('conditions', '_validateConditions'),
        );

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        $labels = array(
            'username' => Yii::t('servers', 'Email address or Identity'),
            'email_box_monitor' => Yii::t('server', 'Create from existing EmailBoxMonitor Server')
        );

        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeHelpTexts()
    {
        $labels = array();

        return CMap::mergeArray($labels, parent::attributeHelpTexts());
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
     * We get inbound server action with this method. Default are fetch
     * if inbound server has no any action.
     * The actions are 3 which are executed when a subscriber match is
     * found for a reply.
     * @return array of action [act=>[conditions[]]]
     */
    public function actions()
    {
        try {
            $actions = $this->getConditions();
            if (!$actions) {
                throw new \Exception("Error Processing Request", 1);
            }

            return $actions;
        } catch (\Exception $e) {
        }
        return $this->defaultActions();
    }

    /**
     * Check if an action/condition can be executed.
     * @param array
     * @return bool
     */
    public function allowAction($action)
    {
        return isset($action['status']) && $action['status'] == "1";
    }

    /**
     * Get preset action for inbounds.
     * @return array default actions (action=>[])
     */
    public function defaultActions()
    {

        $actions = [];
        $actions[self::ACTION_COPY] = [
            'status' => 0,
            'list_id' => null,
            'type' => self::ACTION_TYPE_ACTION,
            'description' => 'Copy Subscriber To:'
        ];

        $actions[self::ACTION_MOVE] = [
            'status' => 0,
            'list_id' => null,
            'type' => self::ACTION_TYPE_ACTION,
            'description' => 'Move Subscriber To:'
        ];

        $actions[self::ACTION_UPDATE_SUBSCRIBER_FIELD] = [
            'status' => 0,
            'keys' => [],
            'values' => [],
            'type' => self::ACTION_TYPE_KEY_VALUE,
            'description' => 'Update subscriber field'
        ];

        $actions[self::ACTION_SEND_EMAIL] = [
            'status' => 0,
            'subject' => null,
            'content' => null,
            'type' => self::ACTION_TYPE_TRANSACTION,
            'description' => 'Send Email To Subscriber'
        ];
        return $actions;
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

        $criteria->compare('t.hostname', $this->hostname, true);
        $criteria->compare('t.username', $this->username, true);
        $criteria->compare('t.service', $this->service);
        $criteria->compare('t.port', $this->port);
        $criteria->compare('t.protocol', $this->protocol);
        $criteria->compare('t.status', $this->status);

        $criteria->addNotInCondition('t.status', array(self::STATUS_HIDDEN));

        $criteria->order = 't.hostname ASC';

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
     * Update action/condition for the inbound
     * @param $value
     * @return $this
     */
    public function setConditions(array $value = []): void
    {
        $this->modelMetaData->getModelMetaData()->add('conditions', (array)$this->filterConditions($value));
    }

    /**
     * Fetch actions/condition of Inbound
     * @return array
     */
    public function getConditions(): array
    {
        $conditions = (array)$this->modelMetaData->getModelMetaData()->itemAt('conditions');
        return $this->filterConditions($conditions);
    }

    /**
     * @param $attribute
     * @param $params
     */
    public function _validateConditions(string $attribute, array $params = []): void
    {
        $value = $this->getConditions();
        if (empty($value)) {
            $this->addError($attribute, Yii::t('servers', 'Please enter at least one valid condition'));
            return;
        }
    }

    /**
     * Filter condition from users before saving, ensure valid known actions are presented
     * @param $value
     * @return array
     */
    protected function filterConditions($conditions): array
    {

        $conditions_sorted = [];
        foreach ($this->defaultActions() as $index => $action) {

            if (in_array($index, array_keys($conditions))) {
                $val = $conditions[$index];
            } else {
                $val = $action;
            }

            $conditions_sorted[$index] = $val;

            if (!isset($val['status'])) {
                $conditions_sorted[$index]['status'] = 0;
            }

            if (Yii::app()->apps->isAppName('customer')) {
                $conditions_sorted[$index]['customer_id'] = (int)Yii::app()->customer->getId();
            }

            if (isset($val['list_id']) && (empty($val['list_id']) || !is_numeric($val['list_id']) || $val['list_id'] == 0)) {
                $conditions_sorted[$index]['status'] = 0;
                continue;
            }

            if (isset($val['type']) && $val['type'] == self::ACTION_TYPE_ACTION) {
                if (!isset($val['list_id'])) {
                    $conditions_sorted[$index]['status'] = 0;
                    continue;
                }

                $list = Lists::model()->findByPk($val['list_id']);

                if (empty($list)) {
                    $conditions_sorted[$index]['status'] = 0;
                    continue;
                }
            } elseif (isset($val['type']) && $val['type'] == self::ACTION_TYPE_KEY_VALUE) {

                if (!isset($val['keys']) || empty($val['keys'][0] ?? '')) {
                    $conditions_sorted[$index]['keys'] = [''];
                    $conditions_sorted[$index]['values'] = [''];
                    $conditions_sorted[$index]['status'] = 0;
                    continue;
                }
            } elseif (isset($val['type']) && $val['type'] == self::ACTION_TYPE_TRANSACTION) {
                $conditions_sorted[$index]['content'] = $this->purify($val['content']);
                $conditions_sorted[$index]['subject'] = $this->purify(strip_tags($val['subject']));

                if (empty($val['subject']) || empty($val['content'])) {
                    $conditions_sorted[$index]['status'] = 0;
                    continue;
                }
            } else { //unsupported action
                unset($conditions_sorted[$index]);
            }
        }

        return $conditions_sorted;
    }
    /**
     * This method test if the inbound imap credentials
     * are correct before saving to database
     * @return bool
     */
    public function testConnection(): bool
    {
        $this->validate();
        if ($this->hasErrors()) {
            return false;
        }

        if (!CommonHelper::functionExists('imap_open')) {
            $this->addError('hostname', Yii::t('servers', 'The IMAP extension is missing from your PHP installation.'));
            return false;
        }
        $errors = [];
        $error  = null;
        $conn   = imap_open($this->getConnectionString(), $this->username, $this->password, OP_READONLY, 1, $this->getImapOpenParams());
        $errors = imap_errors();

        if (!empty($errors) && is_array($errors)) {
            $errors = array_unique(array_values($errors));
            $error  = implode('<br />', $errors);

            // since 1.3.5.8
            if (stripos($error, 'insecure server advertised') !== false) {
                $error = null;
            }
        }

        if (empty($error) && empty($conn)) {
            $error = Yii::t('servers', 'Unknown error while opening the connection!');
        }

        // since 1.3.5.9
        if (!empty($error) && stripos($error, 'Mailbox is empty') !== false) {
            $error = null;
        }
        if (!empty($error)) {
            $this->addError('hostname', $error);
            return false;
        }

        $error   = null;
        $results = imap_search($conn, "NEW", SE_UID, $this->getSearchCharset());
        imap_close($conn);
        $errors  = imap_errors();

        if (!empty($errors) && is_array($errors)) {
            $errors = array_unique(array_values($errors));
            $error = implode('<br />', $errors);
        }

        // since 1.3.5.7
        if (!empty($error) && stripos($error, 'Mailbox is empty') !== false) {
            $error = null;
        }

        if (!empty($error)) {
            $this->addError('hostname', $error);
            return false;
        }

        return true;
    }


    /**
     * inherited
     * Always return false as we dont want any email box content deleted
     * we only want to read. Email box monitor could be used for deletion if needed.
     * @return bool
     */
    public function getDeleteAllMessages(): bool
    {
        return false;
    }

    /**
     * This method is called from the comman track-reply as child of BounceServer
     * It the main method that read mail box and perform any of the activated actions/conditions
     *
     * @param array $params
     * @return null
     */
    protected function _processRemoteContents(array $params = []): bool
    {
        $mutexKey = sha1('imappop3box' . serialize($this->getAttributes(array('hostname', 'username', 'password'))) . date('Ymd'));
        if (!Yii::app()->mutex->acquire($mutexKey, 5)) {
            return false;
        }

        try {
            if (!$this->getIsActive()) {
                throw new Exception('The server is inactive!', 1);
            }
            // make sure the BounceHandler class is loaded
            Yii::import('common.vendors.BounceHandler.*');

            $c_criteria = new CDbCriteria();
            $c_criteria->compare('reply_to', $this->username);
            $c_criteria->order    = 't.campaign_id DESC';

            $campaigns = Campaign::model()->findAll($c_criteria);

            if (!$campaigns) {
                throw new Exception('No campaigns attached to this inbound!', 1);
            }

            $connectionStringSearchReplaceParams = array();
            if (!empty($params['mailbox'])) {
                $connectionStringSearchReplaceParams['[MAILBOX]'] = $params['mailbox'];
            }
            $connectionString = $this->getConnectionString($connectionStringSearchReplaceParams);


            // 1.4.4
            $logger = !empty($params['logger']) && is_callable($params['logger']) ? $params['logger'] : null;

            // put proper status
            $this->saveStatus(self::STATUS_CRON_RUNNING);

            $force = isset($params['read_all']) ? $params['read_all'] : ''; //read all replies beyond current day

            $extension = Yii::app()->extensionsManager->getExtensionInstance('reply-tracker');

            $processDaysBack = (int)$extension->getOption('days_back', 3); //daysback
            $strictness = $extension->getOption('strictness', 'low'); //daysback
            $filterAutoresponder = $extension->getOption('exclude_autoresponse', 'yes') !== 'no';

            foreach ($campaigns as $campaign) {
                $subscribers_id = [];
                //ensure only sending or sent campaign is tracked.
                if (!in_array($campaign->status, ['sending', 'sent', 'paused'])) {
                    continue;
                }

                $list = $campaign->list;
                //ensure campaign list exist
                if (!$list || !$campaign || !isset($campaign->campaign_id)) {
                    continue;
                }

                //determine the date to use for searching.
                $date = isset($params['date']) ? $params['date'] : ''; //date string
                $date = $processDaysBack > 0 ? date("d-M-Y", strtotime("-" . $processDaysBack . " day")) : ($date == '' ? date('d-M-Y') : date('d-M-Y', strtotime($date)));

                //filter by campaign subject
                //@TODO : clean subject line from special characters from full subject match
                $keyword = isset($params['keyword']) ? $params['keyword'] : $campaign->subject; //keyword to search

                //filter string
                //make sure search string is set to make $processDaysBack void;
                $filter = array('SINCE "' . $date . '"');

                if ($strictness == "high") {
                    $filter[] =  'SUBJECT "RE: ' . addslashes($keyword) . '"';
                } else {
                    $filter[] =  'SUBJECT "RE:"';
                }


                if (stripos($campaign->template->content, '[REPLY_TRACKING_PIXEL]') !== false || stripos($campaign->template->content, '[REPLY_TRACKING_PIXEL_PLAIN]') !== false) {

                    if ($strictness == "high") {

                        $filter[] = 'TEXT "' . $list->list_uid . '/' . $campaign->campaign_uid . '"';
                    } else {

                        $filter[] = 'TEXT "' . $list->list_uid . '" TEXT "' . $campaign->campaign_uid . '"';
                    }
                } else if (stripos($campaign->template->content, '[UNSUBSCRIBE_URL]') !== false) {

                    if ($strictness == "high") {

                        $filter[] = 'TEXT "lists/' . $list->list_uid . '/unsubscribe/" TEXT "/' . $campaign->campaign_uid . '"';
                    } else {

                        $filter[] = 'TEXT "' . $list->list_uid . '" TEXT "' . $campaign->campaign_uid . '"';
                    }
                }

                if ($force) { //to read beyond current day
                    unset($filter[0]);
                }

                if (is_array($filter)) { //convert filter to string
                    $filter = trim(implode(" ", $filter));
                }


                // close the db connection because it will time out!
                Yii::app()->getDb()->setActive(false);

                //open imap/pop3 connection to the server
                $bounceHandler = new BounceHandler($connectionString, $this->username, $this->password, array(
                    'deleteMessages'    => false,
                    'deleteAllMessages' => $this->getDeleteAllMessages(),
                    'searchCharset'     => $this->getSearchCharset(),
                    'searchString'      => $filter,
                    'imapOpenParams'    => $this->getImapOpenParams(),
                    'processDaysBack'   => $processDaysBack,
                    'logger'            => $logger,
                ));
                $connection = $bounceHandler->getConnection();

                if ($logger) {
                    $mailbox = isset($connectionStringSearchReplaceParams['[MAILBOX]']) ? $connectionStringSearchReplaceParams['[MAILBOX]'] : $this->mailBox;
                    call_user_func($logger, sprintf('Searching for results in the "%s" mailbox...', $mailbox));
                }

                // fetch the results
                $results = (array)$bounceHandler->getSearchResults();

                // done
                if (empty($results)) {
                    if ($logger) {
                        call_user_func($logger, sprintf('Skipping, No result found for campaign: %s!', $campaign->campaign_uid));
                    }
                    continue;
                }


                // re-open the db connection
                Yii::app()->getDb()->setActive(true);
                foreach ($results as $result) {
                    if ($logger) {
                        call_user_func($logger, sprintf('Processing message id: %s!', $result));
                    }

                    $body = (string) imap_fetchbody($connection, $result, "1.1"); //start with palin text body for easy parsing
                    if (!strlen($body) > 2 || empty($body)) {
                        $body = (string)trim(imap_fetchbody($connection, $result, "1.2")); //html body
                    }
                    if (!strlen($body) > 2 || empty($body)) {
                        // load the full message
                        $body = (string)imap_fetchbody($connection, $result, "1");
                    }

                    //clean message off previous mails. get last reply
                    $cleaned = $this->cleanMessage($body);
                    $body = empty($cleaned) ? $body : $cleaned; //fallback to original body if cleaned empty

                    $body = $this->purify($body); //clean html of malaicious tags

                    if (empty($body)) { //skip if message body is empty ...rare since we use filter
                        if ($logger) {
                            call_user_func($logger, sprintf('Cannot fetch content for message id: %s!', $result));
                        }
                        continue;
                    }

                    // get the header info
                    $headerInfo = imap_headerinfo($connection, $result);
                    $raw_header = imap_fetchheader($connection, $result); //get raw header for autoresponder detection
                    if (empty($headerInfo) || empty($headerInfo->from) || empty($headerInfo->from[0]->mailbox) || empty($headerInfo->from[0]->host)) {
                        if ($logger) {
                            call_user_func($logger, sprintf('Cannot fetch header info for message id: %s!', $result));
                        }
                        continue;
                    }

                    //filter autoresponder and skip
                    if ($filterAutoresponder) {
                        $is_autoresponder = $this->detectAutoresponder($raw_header);
                        if ($is_autoresponder) {
                            if ($logger) {
                                call_user_func($logger, sprintf('Skipping Autoresponder: %s!', $result));
                            }
                            continue;
                        }
                    }


                    $to = $this->extractEmail($headerInfo->toaddress); //get the sender email address. not much needed
                    $from = $this->extractEmail($headerInfo->fromaddress);  //subcriber email should be here, we can also get full name
                    if (empty($from)) { //required from email, else skip
                        if ($logger) {
                            call_user_func($logger, sprintf('Skipping, no from_email found: %s!', $result));
                        }
                        continue;
                    }

                    $subscriber = $this->findSubscriber($list->list_id, $from); //get the subscriber
                    $fromName = $this->extractName($headerInfo->fromaddress);
                    $time = $this->getTime($headerInfo);
                    $date = $time ? date("Y-m-d H:i:s", $time) : '';

                    $criteria = new CDbCriteria();
                    $criteria->compare('t.campaign_id', $campaign->campaign_id);
                    $criteria->compare('t.message', $body);
                    if ($subscriber) {
                        $criteria->compare('t.subscriber_id', $subscriber->subscriber_id);
                    } else {
                        $criteria->compare('t.from_email', $from);
                    }
                    $check = ReplyTrackerExtLogModel::model()->count($criteria);

                    if ((int)$check > 0) { //already logged same message skip
                        if ($logger) {
                            call_user_func($logger, sprintf('Skipping, logged already: %s!', $result));
                        }
                        continue;
                    }

                    //now we can save as reply after all filter passed.
                    if ($subscriber) {  //save subscriber found list for later use.
                        array_push($subscribers_id, $subscriber->subscriber_id);
                    }
                    $replyLog = new ReplyTrackerExtLogModel();
                    $replyLog->message_id = $result;

                    try {
                        $replyLog->to = $to;
                        $replyLog->from_email = $from;
                        $replyLog->from_name = $fromName;
                        $replyLog->message = $body;
                        $replyLog->subscriber_id = $subscriber->subscriber_id;
                        $replyLog->campaign_id = $campaign->campaign_id;
                        $replyLog->server_id = $this->server_id;
                        $replyLog->customer_id = $this->customer_id;
                        $replyLog->reply_date = $date;
                        if (!$replyLog->save()) {
                            throw new \Exception("Unable to save log", 1);
                        } //ensure process


                        if ($logger) {
                            call_user_func($logger, sprintf('Saved to log successfully: %s!', $result));
                        }
                    } catch (\Exception $e) {
                        if ($logger) {
                            call_user_func($logger, sprintf('Error saving to log: %s!', $result . ' ' . $e->getMessage()));
                        }
                        continue;
                    }





                    if ($subscriber) {
                        $move = $this->conditions[self::ACTION_MOVE];
                        $copy = $this->conditions[self::ACTION_COPY];
                        $sendMail = $this->conditions[self::ACTION_SEND_EMAIL];
                        $updateSubscriber = $this->conditions[self::ACTION_UPDATE_SUBSCRIBER_FIELD];

                        //start with copy incase move is needed
                        if ($this->allowAction($copy) && !empty($copy['list_id'])) {
                            //wrap in try catch incase list has been moved in previos search
                            try {
                                $subscriber->copyToList($copy['list_id']);
                            } catch (\Exception $e) {
                                if ($logger) {
                                    call_user_func($logger, sprintf('Error saving to log: %s!', $result . ' ' . $e->getMessage()));
                                }
                            }
                        }


                        //move subscriber
                        if ($this->allowAction($move) && !empty($move['list_id'])) {
                            //wrap in try catch incase list has been moved in previos search
                            try {
                                $subscriber->moveToList($move['list_id']);
                            } catch (\Exception $e) {
                                if ($logger) {
                                    call_user_func($logger, sprintf('Error saving to log: %s!', $result . ' ' . $e->getMessage()));
                                }
                            }
                        }


                        //send mail action
                        if ($this->allowAction($sendMail)) {
                            //wrap in try catch incase list has been moved in previos search
                            try {
                                $send = $this->sendEMail($campaign, $subscriber, $sendMail);
                            } catch (\Exception $e) {
                                if ($logger) {
                                    call_user_func($logger, sprintf('Error saving to log: %s!', $result . ' ' . $e->getMessage()));
                                }
                            }
                        }


                        //update subscriber custom field
                        if ($this->allowAction($updateSubscriber)) {

                            $action_keys = (array)$updateSubscriber['keys'];
                            $action_values = (array)$updateSubscriber['values'];

                            $list_fields = ListField::getAllByListId((int)$list->list_id);

                            foreach ($list_fields as $field) {

                                $field = (object)$field;

                                $index = array_search($field->tag, $action_keys);

                                if (
                                    !in_array($field->tag, $action_keys) ||
                                    $index === false ||
                                    $action_keys[$index] != $field->tag
                                ) {
                                    continue;
                                }

                                $value = db()->createCommand()
                                    ->select('value_id, value')
                                    ->from('{{list_field_value}}')
                                    ->where('subscriber_id = :sid AND field_id = :fid', [
                                        ':sid' => (int)$subscriber->subscriber_id,
                                        ':fid' => (int)$field->field_id,
                                    ])
                                    ->queryRow();

                                if (empty($value) && $value['value_id'] == '') {

                                    continue;
                                }

                                $data = [
                                    'field_id'      => (int)$field->field_id,
                                    'subscriber_id' => (int)$subscriber->subscriber_id,
                                    'value'         => $action_values[$index],
                                    'last_updated'  => new CDbExpression('NOW()'),
                                ];

                                $command = db()->createCommand();
                                $command->update('{{list_field_value}}', $data, 'value_id = :vid', [':vid' => $value['value_id']]);
                            }
                        }
                    }
                }

                $bounceHandler->closeConnection(); //close connection to active server.
            }
        } catch (Exception $e) {
            if ($e->getCode() == 0) {
                Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
            }
        }
        // mark the server as active once again
        $this->saveStatus(self::STATUS_ACTIVE);
        Yii::app()->mutex->release($mutexKey);

        return true;
    }

    /**
     * Determines whether the given message is from an auto-responder.
     *
     * This method checks whether the header contains any auto response headers as
     * outlined in RFC 3834 and beyond that through personal reseach
     * and also checks to see if the subject line contains
     * certain strings set by different email providers to indicate an automatic
     * response.
     *
     * @param $header (string)
     *   Message header as returned by imap_fetchheader().
     *
     * @return (bool)
     *   TRUE if this message comes from an autoresponder.
     */
    private function detectAutoresponder($header): bool
    {
        $autoresponders = array(
            'X-Autoresponse:' => '', // Other email servers.
            'X-Autorespond:' => '', // LogSat server.
            'Subject: Auto Response' => '', // Yahoo mail.
            'Out of office' => '', // Generic.
            'Out of the office' => '', // Generic.
            'autoreply' => '', // Generic.
            'X-Mail-Autoreply' => '',
            'X-AutoReply-From' => '',
            'Autoresponder' => '',
            'Auto-Submitted' => ['auto-replied', 'auto-generated'], //gitlab,github & other automated pipelines
            'Delivered-To' => ['Autoresponder'],
            'X-Autoreply' => ['yes'],
            'X-POST-MessageClass' => ['9'],
            'X-Autogenerated' => ['Forward', 'Group', 'Letter', 'Mirror', 'Redirect', 'Reply'],
            'Precedence' => ['bulk'],
            'X-Precedence' => ['auto_reply'],
            'Preference' => ['auto_reply'],
            'X-FC-MachineGenerated' => ['true'],
        );

        // Check for presence of different autoresponder strings.
        foreach ($autoresponders as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $svalue) {
                    $string = $key . ': ' . $svalue;
                    if (strpos($header, $string) !== false) {
                        return true;
                    }
                }
            } else {
                if (strpos($header, $key) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Strips quotes (older messages) from a message body.
     *
     * This function removes any lines that begin with a quote character (>).
     * Note that quotes in reply bodies will also be removed by this function,
     *
     * @param $message (string)
     *   The message to be cleaned.
     * @param $plain_text_output (bool)
     *   Set to TRUE to also run the text through strip_tags() (helpful for
     *   cleaning up HTML emails).
     *
     * @return (string)
     *   Same as message passed in, but with all quoted text removed.
     *
     */
    public function cleanMessage($message, $plain_text_output = false): string
    {
        $original_message = $message;
        // Strip markup if $plain_text_output is set.
        if ($plain_text_output) {
            $message = strip_tags($message);
        } else {
            //remove anythin inside blockquote
            $message = preg_replace("/<blockquote\b[^>]*>([\s\S]*)<\/blockquote>/", '', $message);
        }
        // Remove quoted lines (lines that begin with '>').
        $message = preg_replace("/(^\w.+:\n)?(^>.*(\n|$))+/mi", '', $message);

        // Remove lines beginning with 'On' and ending with 'wrote:' (matches
        // Mac OS X Mail, Gmail).
        $message = preg_replace("/^(On).*(wrote:).*$/sm", '', $message);

        //any first occurence of On and wrote:
        $message = preg_replace('/On.*[0-9].*?[\s\S]wrote:/', '', $message);

        // Remove lines like '----- Original Message -----' (some other clients).
        // Also remove lines like '--- On ... wrote:' (some other clients).
        $message = preg_replace("/^---.*$/mi", '', $message);

        // Remove lines like '____________' (some other clients).
        $message = preg_replace("/^____________.*$/mi", '', $message);

        // Remove blocks of text with formats like:
        //   - 'From: Sent: To: Subject:'
        //   - 'From: To: Sent: Subject:'
        //   - 'From: Date: To: Reply-to: Subject:'
        $message = preg_replace("/From:.*^(To:).*^(Subject:).*/sm", '', $message);

        // Remove any remaining whitespace.
        $message = trim($message);

        //alternative method to stripe old message.
        if (empty($message)) {
            $message = trim(preg_replace('/(On.*.wrote:)/', '$2', $original_message));
            $message = preg_replace('#(^\w.+:\n)?(^>.*(\n|$)){2}#mi', "", $message);
        }
        return trim($message);
    }

    /**
     * Get time value from message header
     *
     * This method extract date of message from header of the message
     * @param $header(object)
     *
     * @return (string)
     **/
    public function getTime($header): string
    {
        if (isset($header->udate)) {
            $time = $header->udate;
        } elseif (isset($header->date)) {
            $time = strtotime($header->date);
        } elseif (isset($header->MailDate)) {
            $time = strtotime($header->MailDate);
        } else {
            $time = null;
        }
        return $time;
    }

    /**
     * Extract email from a string
     *
     * This method is used for extracting email from 'from' string of mail
     * For example: get abc@mail.com from "My Name <abc@mail.com>".
     *
     * @return (string)
     */
    public function extractEmail($str)
    {
        preg_match("/(?<email>[-0-9a-zA-Z\.+_]+@[-0-9a-zA-Z\.+_]+\.[a-zA-Z]+)/", $str, $matched);
        if (array_key_exists('email', $matched)) {
            return $matched['email'];
        } else {
            return;
        }
    }

    /**
     * Extract name from a string
     * For example: get abc@mail.com from "My Name <abc@mail.com>".
     *
     * @return (string) version
     */
    public function extractName($str): string
    {
        $parts = explode('<', $str);
        if (count($parts) > 1) {
            return trim($parts[0]);
        }
        $parts = explode('@', $this->extractEmail($str));

        return $parts[0];
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

        if (!($server = DeliveryServer::pickServer(0, $campaign, $dsParams))) {
            if (!($server = DeliveryServer::pickServer(0, $list))) {
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
            if ($sent = $server->setDeliveryFor(DeliveryServer::DELIVERY_FOR_LIST)->setDeliveryObject($list)->sendEmail($params)) {
                break;
            }
            if (!($server = DeliveryServer::pickServer($server->server_id, $list))) {
                break;
            }
        }

        return $sent;
    }

    /**
     * This function return tag list available tags for send-email action.
     *
     * The unsubscribe_tag is required.
     *
     * @param null
     *
     * @return (array)
     */
    public function getAvailableTags()
    {
        return ['[SUBSCRIBE_URL]', '[UPDATE_PROFILE_URL]', '[LIST_NAME]', '[UNSUBSCRIBE_URL]', '[COMPANY_FULL_ADDRESS]', '[COMPANY_NAME]', '[CURRENT_YEAR]'];
    }

    /**
     * Use this method to clean user reply off malacious scritps.
     * The reply are cleaned already before saving but re-escape before displaying
     *
     * @param $data string
     *
     * @return (string)
     **/
    public function purify($data): string
    {
        if ($this->purifier) {
            return $this->purifier->purify($data);
        }
        $purifier = new CHtmlPurifier();
        $purifier->options = array('HTML.Allowed' => 'p,div,b,i,a[href],u,ul,ol,li,h1,h2,h3,h4,h5,h6,br');
        $this->purifier = $purifier;
        return $purifier->purify($data);
    }
}