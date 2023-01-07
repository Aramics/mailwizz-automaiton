<?php
defined('MW_PATH') || exit('No direct script access allowed');


/**
 * This class describes an automation canvas block group action for running all action blocks.
 */
class AutomationExtCanvasBlockGroupAction
{

    /**
     * The canvas running the block
     *
     * @var AutomationExtCanvas
     */
    public $canvas;

    /**
     * The debugger callback
     *
     * @var callable
     */
    public $logger;


    /**
     * Create new instance of AutomationExtCanvasBlockGroupAction
     * We require the canvas to give easy access to some methods and properties.
     *
     * @param AutomationExtCanvas $canvas
     */
    public function __construct(AutomationExtCanvas $canvas)
    {
        // require automation model
        if (!$canvas || !$canvas->automation || !$canvas->customer)
            throw new Exception("Automation model is required to block actions", 1);
    }

    /**
     * Debugger
     *
     * @param string $message
     * @return void
     */
    public function debug(string $message)
    {
        $this->runner->debug($message);
    }

    /**
     * Method to validate objects model before running.
     * This provide convince way of validating the object (campaign or list) belongs to automation user.
     * Using this reduce our repetition and passing around the automation object for CDbCriteria() filtering.
     *
     * @param Campaign|Lists $model
     * @return void
     * 
     * @throws Exception if model is not empty and with wrong ownership
     */
    public function validateResourceOwnership($model)
    {
        if (!empty($model) && (!$model->customer_id || $model->customer_id !== $this->canvas->automation->customer_id))
            throw new Exception(sprintf(
                "The %s must belong to the owner of this automation: %d",
                class_basename($model),
                $this->canvas->automation->automation_id
            ), 1);
    }


    /**
     * Run a canvas action block.
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber|null $subscriber
     * @return bool True if successful
     * 
     * @throws Exception Invalid block group,Internal errors or wrong ownerships.
     */
    public function run(AutomationExtCanvasBlock $block, ListSubscriber $subscriber = null)
    {

        $blockGroup = $block->getGroup();
        $blockType = $block->getType();
        if ($blockGroup !== AutomationExtCanvasBlockGroups::LOGIC) {

            throw new Exception(sprintf(t("automation", "Invalid block group %s passed as action"), $blockGroup), 1);
        }


        switch ($blockType) {

            case AutomationExtCanvasBlockTypes::STOP:
                return $this->stopAutomation();
                break;

            case AutomationExtCanvasBlockTypes::WAIT:
                return $this->wait($block, $this->canvas->getBlockParent($block));
                break;

            case AutomationExtCanvasBlockTypes::SEND_EMAIL:
                return $this->sendEmail($block, $subscriber);
                break;

            case AutomationExtCanvasBlockTypes::SEND_CAMPAIGN:
                return $this->addSubscriberToCampaign($block, $subscriber);
                break;

            case AutomationExtCanvasBlockTypes::RUN_CAMPAIGN:
                return $this->runCampaign($block);
                break;

            case AutomationExtCanvasBlockTypes::OTHER_CAMPAIGN_ACTION:
                return $this->campaignOtherActions($block);
                break;

            case AutomationExtCanvasBlockTypes::MOVE_SUBSCRIBER:
            case AutomationExtCanvasBlockTypes::COPY_SUBSCRIBER:
                return $this->moveCopySubscriber($block, $subscriber, $this->canvas->automation);
                break;

            case AutomationExtCanvasBlockTypes::UPDATE_SUBSCRIBER:
                return $this->updateSubscriber($block, $subscriber);
                break;


            case AutomationExtCanvasBlockTypes::OTHER_SUBSCRIBER_ACTION:
                return $this->subscriberOtherActions($block, $subscriber);
                break;


            case AutomationExtCanvasBlockTypes::WEBHOOK_ACTION:
                return $this->webhookAction($block, $subscriber);
                break;
        }

        throw new \Exception("Unkown action passed for running", 1);
    }

    /**
     * Stop the automation.
     * Will prevent all other blocks from running.
     *
     * @return void
     * 
     * @throws Exception    To break out of all tree/branches and prevent execution of further blocks..
     */
    public function stopAutomation()
    {
        $this->canvas->automation->stop();
        $this->debug("Stopping automation");
        throw new Exception("Automation is now stopped", 0);
    }

    /**
     * Excute wait action
     *
     * @param AutomationExtCanvasBlock $block
     * @param AutomationExtCanvasBlock $parentBlock
     * @return bool
     */
    public function wait(AutomationExtCanvasBlock $block, AutomationExtCanvasBlock $parentBlock)
    {
        // wait block and its evaluation
        $data = $block->getData();
        $waitPeriodInSeconds = $data['interval_value'] * (int)$data['interval_unit_in_secs'];
        if ($waitPeriodInSeconds <= 0)
            return true;

        $timeDiffInSecs = strtotime(time()) - strtotime($parentBlock->last_run);
        if ($waitPeriodInSeconds > $timeDiffInSecs)
            return false;

        return true;
    }

    /**
     * Method to run the send email block.
     * Will send email to the subscriber using content/template of the provided campaign (regular or automation)
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber $subscriber
     * @return bool
     */
    public function sendEmail(AutomationExtCanvasBlock $block, ListSubscriber $subscriber)
    {
        $blockData = $block->getData();
        $email = trim($blockData['email']);
        $campaignId = (int)$blockData['campaign'];

        $validator = new CEmailValidator();
        $validator->allowEmpty  = false;
        $validator->validateIDN = true;
        $validEmail = $validator->validateValue($email);

        if (!empty($email) && !$validEmail) {

            $this->debug(sprintf(t("automations", "Valid email address is required for send transaction email %s"), $email));
            return false;
        }

        if (!$validEmail && !$subscriber) {

            $this->debug(t("automations", "Subscriber or Valid email address is required to send transactional email: non is found"));
            return false;
        }

        $email = $validEmail ? $email : $subscriber->email;

        // send an email
        $criteria = new CDbCriteria();
        $criteria->compare('campaign_id', $campaignId);
        $criteria->addNotInCondition('status', [Campaign::STATUS_PENDING_DELETE]);

        /** @var Campaign|null $model */
        $campaign = Campaign::model()->find($criteria);
        $this->validateResourceOwnership($campaign);

        $dsParams = array('useFor' => DeliveryServer::USE_FOR_CAMPAIGNS);
        $list = $subscriber->list ?? $campaign->list;

        if (!($server = DeliveryServer::pickServer(0, $campaign, $dsParams))) {
            if (!($server = DeliveryServer::pickServer(0, $list))) {

                $this->debug(t('automations', "No delivery server found"));
                return false;
            }
        }

        $content = $campaign->template->content;
        $subject = $campaign->getCurrentSubject();

        $searchReplace = CampaignHelper::getCommonTagsSearchReplace($content, $campaign);

        $content = str_replace(array_keys($searchReplace), array_values($searchReplace), $content);
        $subject = str_replace(array_keys($searchReplace), array_values($searchReplace), $subject);

        // 1.5.3
        if (CampaignHelper::isTemplateEngineEnabled()) {
            $content = CampaignHelper::parseByTemplateEngine($content, $searchReplace);
            $subject = CampaignHelper::parseByTemplateEngine($subject, $searchReplace);
        }

        // because we don't have what to parse here!
        $hasDefaultFromName = strpos($this->from_name, '[') !== false && !empty($this->list->default->from_name);
        $fromName = $hasDefaultFromName ? $list->default->from_name : $campaign->from_name;

        $params = array(
            'to'        => $email,
            'fromName'  => $fromName,
            'subject'   => $subject,
            'body'      => $content,
            'replyTo' => [$campaign->reply_to => $fromName]
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

        if (!$sent) {
            $this->debug(t('automations', "Error sending campaign"));
            return false;
        }

        return true;
    }

    /**
     * Method add the subscriber to a campaign
     * Will add the subscriber to the campaign list and mark the campaign as sending.
     *
     * 
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber $subscriber
     * @return bool
     */
    public function addSubscriberToCampaign(AutomationExtCanvasBlock $block, ListSubscriber $subscriber)
    {
        if (!$subscriber) {
            $this->debug("Subscriber not found");
            return false;
        }

        // send an email
        $campaignId = (int)$block->getDataByName('campaign');
        $criteria = new CDbCriteria();
        $criteria->compare('campaign_id', $campaignId);
        $criteria->addNotInCondition('status', [Campaign::STATUS_PENDING_DELETE]);
        /** @var Campaign|null $model */
        $campaign = Campaign::model()->find($criteria);

        $this->validateResourceOwnership($campaign);

        //copy the campaign list
        if ($subscriber->list->list_id == $campaign->list->List_id) {

            //just log about this
            $this->debug(t('automations', "The subscriber is already in the list "));
        } else
            $subscriber->copyToList($campaign->list->list_id);

        //mark as sending for cron job to pickup new list members
        $campaign->saveStatus(Campaign::STATUS_SENDING);
        return true;
    }


    /**
     * Start a campaign attached to the block
     * Marks campaigns as sending.
     * 
     * @param AutomationExtCanvasBlock $block
     * @return bool
     */
    public function runCampaign(AutomationExtCanvasBlock $block)
    {
        $campaignId = (int)$block->getDataByName('campaign');
        $criteria = new CDbCriteria();
        $criteria->compare('campaign_id', $campaignId);
        $criteria->addNotInCondition('status', [Campaign::STATUS_PENDING_DELETE]);
        /** @var Campaign|null $model */
        $campaign = Campaign::model()->find($criteria);

        $this->validateResourceOwnership($campaign);

        if (!$campaign->getEditable()) {
            $this->debug("The campaign is not editable $campaignId");
            return false;
        }

        /** @var Customer $customer */
        $customer = $campaign->list->customer;
        $campaign->setScenario('step-confirm');

        if ($campaign->getIsAutoresponder()) {
            $campaign->option->setScenario('step-confirm-ar');
        }

        if (empty($campaign->template->content)) {
            $this->debug('Missing campaign template: ' . $campaignId);
            return false;
        }

        $errors = [];

        // since 1.3.4.7 - must validate sending domain - start
        if (!SendingDomain::model()->getRequirementsErrors() && $customer->getGroupOption('campaigns.must_verify_sending_domain', 'no') == 'yes') {
            $sendingDomain = SendingDomain::model()->findVerifiedByEmail($campaign->from_email, (int)$campaign->customer_id);
            if (empty($sendingDomain)) {
                $emailParts = explode('@', $campaign->from_email);
                $domain = $emailParts[1];
                $errors[] = t('campaigns', 'You are required to verify your sending domain({domain}) in order to be able to send this campaign!', [
                    '{domain}' => CHtml::tag('strong', [], $domain),
                ]);

                $errors[] = t('campaigns', 'Please click {link} to add and verify {domain} domain name. After verification, you can send your campaign.', [
                    '{link}'   => CHtml::link(t('app', 'here'), ['sending_domains/create']),
                    '{domain}' => CHtml::tag('strong', [], $domain),
                ]);
            }
        }
        // must validate sending domain - end

        // since 2.0.30
        if (($maxActiveCampaigns = (int)$customer->getGroupOption('campaigns.max_active_campaigns', -1)) > -1) {
            $criteria = new CDbCriteria();
            $criteria->compare('customer_id', (int)$customer->customer_id);
            $criteria->addInCondition('status', [
                Campaign::STATUS_PENDING_SENDING,
                Campaign::STATUS_PENDING_APPROVE,
                Campaign::STATUS_PAUSED,
                Campaign::STATUS_SENDING,
                Campaign::STATUS_PROCESSING,
            ]);
            $campaignsCount = Campaign::model()->count($criteria);
            if ($campaignsCount >= $maxActiveCampaigns) {
                $errors[] = t('campaigns', 'You have reached the maximum number of allowed active campaigns.');
            }
        }

        $requireApproval = $customer->getGroupOption('campaigns.require_approval', 'no') == 'yes';
        if ($requireApproval) {
            $campaign->markPendingApprove();
            $campaign->save();
            $errors[] =  t('automations', "Campaign requires approval");
        }

        if ($campaign->getIsPaused()) {
            $errors[] = t('automations', "Campaign is paused");
        }

        if (count($errors)) {
            $this->debug(implode("\n", $errors));
            return false;
        }

        $campaign->status = Campaign::STATUS_PENDING_SENDING;
        if (!$campaign->save()) {
            $this->debug(t('automations', "Error saving campaign"));
            return false;
        }
        return true;
    }

    /**
     * Run certain action on the campaign.
     * Copy campaign or update status to "action" data provided in the block.
     *
     * @param AutomationExtCanvasBlock $block
     * @return bool
     */
    public function campaignOtherActions(AutomationExtCanvasBlock $block)
    {
        $blockData = (array)$block->getData();
        $action = $blockData['action'];
        $campaignId = (int)$blockData['campaign'];

        $criteria = new CDbCriteria();
        $criteria->compare('campaign_id', $campaignId);
        $criteria->addNotInCondition('status', [Campaign::STATUS_PENDING_DELETE]);
        /** @var Campaign|null $model */
        $campaign = Campaign::model()->find($criteria);

        $this->validateResourceOwnership($campaign);

        if (!$campaign->getEditable()) {
            $this->debug("The campaign is not editable $campaignId");
            return false;
        }

        $actions = AutomationExtModel::campaignBlockActionsList();
        if (!in_array($action, $actions)) {
            $this->debug("The block: %s (campaignOtherActions) is supplied with unkown action for campaign other actions", $block->getId());
            return false;
        }

        if ($action == "copy") { //copy action
            return !empty($campaign->copy());
        } else { //others are status update
            return $campaign->saveStatus($action);
        }
    }


    /**
     * Move or Copy subscriber to another list provided in block data
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber $subscriber
     * @return bool
     */
    public function moveCopySubscriber(AutomationExtCanvasBlock $block, ListSubscriber $subscriber)
    {
        if (!$subscriber) {
            $this->debug("Subscriber not found");
            return false;
        }

        $listId = (int)$block->getDataByName('mail-list');
        /** @var Lists|null $model */
        $list = Lists::model()->findByPk($listId);

        $this->validateResourceOwnership($list);

        if (empty($list)) {
            $this->debug("Moving to list failed, Unkown list $listId");
            return false;
        }

        if ($block->getType() == AutomationExtCanvasBlockTypes::MOVE_SUBSCRIBER)
            $subscriber->moveToList($list->list_id);
        else
            $subscriber->copyToList($list->list_id);

        return true;
    }


    /**
     * Update subscriber fields provided in the block
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber $subscriber
     * @return bool
     */
    public function updateSubscriber(AutomationExtCanvasBlock $block, ListSubscriber $subscriber)
    {
        if (!$subscriber) {
            $this->debug("Subscriber not found");
            return false;
        }

        $data = $block->getData();
        /** @var Lists $list */
        $list = $subscriber->list;

        $this->validateResourceOwnership($list);

        // update subscriber custom field
        $fields = $data["fields[]"];
        $values = $data["values[]"];

        $list_fields = ListField::getAllByListId((int)$list->list_id);

        foreach ($list_fields as $list_field) {

            $list_field = (object)$list_field;

            $index = array_search($list_field->tag, $fields);

            if (
                !in_array($list_field->tag, $fields) ||
                $index === false ||
                $fields[$index] != $list_field->tag
            ) {
                continue;
            }

            $value = db()->createCommand()
                ->select('value_id, value')
                ->from('{{list_field_value}}')
                ->where('subscriber_id = :sid AND field_id = :fid', [
                    ':sid' => (int)$subscriber->subscriber_id,
                    ':fid' => (int)$list_field->field_id,
                ])->queryRow();

            if (empty($value) && $value['value_id'] == '') {

                continue;
            }

            $data = [
                'field_id'      => (int)$list_field->field_id,
                'subscriber_id' => (int)$subscriber->subscriber_id,
                'value'         => $values[$index],
                'last_updated'  => new CDbExpression('NOW()'),
            ];

            $command = db()->createCommand();
            $command->update('{{list_field_value}}', $data, 'value_id = :vid', [':vid' => $value['value_id']]);
        }

        return false;
    }


    /**
     * The method run bulk actions on the active subscriber only.
     * Similar to buld action on the subscribers list
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber $subscriber
     * @return bool
     */
    public function subscriberOtherActions(AutomationExtCanvasBlock $block, ListSubscriber $subscriber)
    {
        if (!$subscriber) {
            $this->debug("Subscriber not found");
            return false;
        }

        $selectedSubscribers = [$subscriber->id];

        /** @var Lists $list */
        $list = $subscriber->list;

        $this->validateResourceOwnership($list);

        $action     = $block->getDataByName("action");

        if (!in_array($action, array_keys($subscriber->getBulkActionsList()))) {
            $this->debug("Unkown subscriber action $action");
            return false;
        }

        /** @var Customer $customer */
        $customer = $list->customer;

        hooks()->doAction('controller_action_bulk_action', $collection = new CAttributeCollection([
            'controller' => $this,
            'redirect'   => null,
            'list'       => $list,
            'action'     => $action,
            'data'       => [$subscriber->id]
        ]));


        $criteria = new CDbCriteria();
        $criteria->compare('list_id', (int)$list->list_id);
        $criteria->addInCondition('subscriber_id', $selectedSubscribers);

        if ($action == ListSubscriber::BULK_SUBSCRIBE) {

            $statusNotIn          = [ListSubscriber::STATUS_CONFIRMED];
            $canMarkBlAsConfirmed = $customer->getGroupOption('lists.can_mark_blacklisted_as_confirmed', 'no') === 'yes';

            $criteria->addNotInCondition('status', $statusNotIn);
            $subscribers = ListSubscriber::model()->findAll($criteria);

            foreach ($subscribers as $subscriber) {
                // save the flag here
                $approve    = $subscriber->getIsUnapproved();
                $initStatus = $subscriber->status;

                // confirm the subscriber
                $subscriber->saveStatus(ListSubscriber::STATUS_CONFIRMED);

                // and if the above flag is bool, proceed with approval stuff
                if ($approve) {
                    $subscriber->handleApprove(true)->handleWelcome(true);
                }

                // finally remove from blacklist
                if ($initStatus == ListSubscriber::STATUS_BLACKLISTED) {
                    if ($canMarkBlAsConfirmed) {
                        // global blacklist and customer blacklist
                        $subscriber->removeFromBlacklistByEmail();
                    } else {
                        // only customer blacklist
                        CustomerEmailBlacklist::model()->deleteAllByAttributes([
                            'customer_id' => $subscriber->list->customer_id,
                            'email'       => $subscriber->email,
                        ]);
                    }
                }

                // 1.3.8.8 - remove from moved table
                ListSubscriberListMove::model()->deleteAllByAttributes([
                    'source_subscriber_id' => $subscriber->subscriber_id,
                ]);
            }
        } elseif ($action == ListSubscriber::BULK_UNSUBSCRIBE) {

            $criteria->addNotInCondition('status', [ListSubscriber::STATUS_BLACKLISTED, ListSubscriber::STATUS_MOVED]);

            ListSubscriber::model()->updateAll([
                'status'        => ListSubscriber::STATUS_UNSUBSCRIBED,
                'last_updated'  => MW_DATETIME_NOW,
            ], $criteria);
        } elseif ($action == ListSubscriber::BULK_DISABLE) {

            $criteria->addInCondition('status', [ListSubscriber::STATUS_CONFIRMED]);

            ListSubscriber::model()->updateAll([
                'status'        => ListSubscriber::STATUS_DISABLED,
                'last_updated'  => MW_DATETIME_NOW,
            ], $criteria);
        } elseif ($action == ListSubscriber::BULK_UNCONFIRM) {

            $criteria->addInCondition('status', [ListSubscriber::STATUS_CONFIRMED]);

            ListSubscriber::model()->updateAll([
                'status'        => ListSubscriber::STATUS_UNCONFIRMED,
                'last_updated'  => MW_DATETIME_NOW,
            ], $criteria);
        } elseif ($action == ListSubscriber::BULK_RESEND_CONFIRMATION_EMAIL) {
            $criteria->addInCondition('status', [ListSubscriber::STATUS_UNCONFIRMED]);
            $subscribers = ListSubscriber::model()->findAll($criteria);

            foreach ($subscribers as $subscriber) {
                $pageType = ListPageType::model()->findBySlug('subscribe-confirm-email');
                if (empty($pageType)) {
                    continue;
                }

                $page = ListPage::model()->findByAttributes([
                    'list_id' => $subscriber->list_id,
                    'type_id' => $pageType->type_id,
                ]);

                $content = !empty($page->content) ? $page->content : $pageType->content;
                $subject = !empty($page->email_subject) ? $page->email_subject : $pageType->email_subject;
                $list    = $subscriber->list;

                /** @var OptionUrl $optionUrl */
                $optionUrl = container()->get(OptionUrl::class);

                $subscribeUrl = $optionUrl->getFrontendUrl('lists/' . $list->list_uid . '/confirm-subscribe/' . $subscriber->subscriber_uid);

                // 1.5.3
                $updateProfileUrl = $optionUrl->getFrontendUrl('lists/' . $list->list_uid . '/update-profile/' . $subscriber->subscriber_uid);
                $unsubscribeUrl   = $optionUrl->getFrontendUrl('lists/' . $list->list_uid . '/unsubscribe/' . $subscriber->subscriber_uid);

                $searchReplace = [
                    '[LIST_NAME]'           => $list->display_name,
                    '[LIST_DISPLAY_NAME]'   => $list->display_name,
                    '[LIST_INTERNAL_NAME]'  => $list->name,
                    '[LIST_UID]'            => $list->list_uid,
                    '[COMPANY_NAME]'        => !empty($list->company) ? $list->company->name : null,
                    '[SUBSCRIBE_URL]'       => $subscribeUrl,
                    '[CURRENT_YEAR]'        => date('Y'),

                    // 1.5.3
                    '[UPDATE_PROFILE_URL]'  => $updateProfileUrl,
                    '[UNSUBSCRIBE_URL]'     => $unsubscribeUrl,
                    '[COMPANY_FULL_ADDRESS]' => !empty($list->company) ? nl2br($list->company->getFormattedAddress()) : null,
                ];

                // since 1.5.2
                $subscriberCustomFields = $subscriber->getAllCustomFieldsWithValues();
                foreach ($subscriberCustomFields as $field => $value) {
                    $searchReplace[$field] = $value;
                }
                // 

                $content = (string)str_replace(array_keys($searchReplace), array_values($searchReplace), $content);
                $subject = (string)str_replace(array_keys($searchReplace), array_values($searchReplace), $subject);

                // 1.5.3
                if (CampaignHelper::isTemplateEngineEnabled()) {
                    $content = CampaignHelper::parseByTemplateEngine($content, $searchReplace);
                    $subject = CampaignHelper::parseByTemplateEngine($subject, $searchReplace);
                }

                $email = new TransactionalEmail();
                $email->customer_id = (int)$customer->customer_id;
                $email->to_name     = $subscriber->email;
                $email->to_email    = $subscriber->email;
                $email->from_name   = $list->default->from_name;
                $email->subject     = $subject;
                $email->body        = $content;
                $email->save();
            }
        } elseif ($action == ListSubscriber::BULK_DELETE) {

            if (!$subscriber->getCanBeDeleted()) return false;

            ListSubscriber::model()->deleteAll($criteria);
        } elseif ($action == ListSubscriber::BULK_BLACKLIST_IP) {

            $criteria->addCondition('ip_address IS NOT NULL AND ip_address != ""');
            $subscribers = ListSubscriber::model()->findAll($criteria);

            foreach ($subscribers as $subscriber) {
                $subscriber->blacklistIp();
            }
        }

        // since 1.6.4
        $list->flushSubscribersCountCache();

        return true;
    }


    /**
     * Call a webhook action block runner.
     * Send provided data to the webhook endpoint.
     * It detect list tags and replace if $subscriber is not empty
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber|null $subscriber
     * @return bool
     */
    public function webhookAction(AutomationExtCanvasBlock $block, ListSubscriber $subscriber = null)
    {
        $data = $block->getData();
        $webhookUrl = $data["webhook_endpoint"];
        $webhookMethod = $data["webhook_method"];
        $webhookData = [];
        $fields = $data["fields[]"];
        $values = $data["values[]"];
        $headers = [];

        $tagsValue = null;
        if ($subscriber) {
            // replace field tags with values for the subscriber.
            $tagsValue = db()->createCommand()
                ->select('DISTINCT tag, value, subscriber_id')
                ->from('{{list_field_value}} v')
                ->join('{{list_field}} f', 'v.field_id = f.field_id')
                ->where('subscriber_id = :sid', [
                    ':sid' => (int)$subscriber->subscriber_id
                ])
                ->queryAll();
        }

        foreach ($fields as $index => $field) {
            $field = trim($field);
            $value = trim($values[$index]);

            if (stripos($value, '[') >= 0 && stripos($value, ']') > 0 && !empty($tagsValue)) {
                foreach ($tagsValue as $row) {
                    $tag = $row['tag'];
                    $value = str_ireplace("[$tag]", $row['value'], $value);
                }
            }

            // add fields starting with X- as header
            if (stripos($field, 'X-') == 0) {
                $headers[$field] = $value;
                continue;
            }

            $webhookData[$field] = $values[$index];
        }


        // call the webhook with the data
        $client = new GuzzleHttp\Client();
        $options = [
            'timeout' => 15,
            'headers' => $headers,
        ];

        // send data as json or query
        $options[$webhookMethod == "GET" ? 'query' : 'json']    = $webhookData;

        $req = $client->request($webhookMethod, $webhookUrl, $options);
        $statusCode = $req->getStatusCode();

        $this->debug("Sending $webhookMethod request to $webhookUrl is: $statusCode ");

        return $statusCode === 200;
    }
}