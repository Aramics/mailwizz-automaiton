<?php

defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the helper class for automation model canvas data management.
 */

class AutomationExtCanvasBlockGroupTrigger
{

    /**
     * @var ExtensionInit
     */
    public ExtensionInit $ext;

    /**
     * @var array
     */
    public $listActionToPageType = [
        'subscribe_confirm'   => 'subscribe-confirm',
        'unsubscribe_confirm' => 'unsubscribe-confirm',
    ];

    public function init(ExtensionInit $ext)
    {
        $this->ext = $ext;

        $hooks = Yii::app()->hooks;


        if ($ext->isAppName('frontend')) {

            //subscribe and unsubscriber trigger - handle for api and backend by loop
            $hooks->addAction('frontend_controller_lists_before_action', [$this, '_insertListCallbacks']);

            //open email         
            $hooks->addAction('frontend_campaigns_after_track_opening', [$this, '_openTrigger']);

            //click url
            $hooks->addAction('frontend_campaigns_after_track_url_before_redirect', [$this, '_urlClickTrigger']);


            //reply to email
            $hooks->addAction('campaigns_after_reply', [$this, '_replyTrigger']);
        }

        //dd($this);

        //webhook api - make frontend controller for processing webhook

        //specific date - cron match date

        //subscriber date added - cron (get list of subscribers having today as date of added to list)

        //recurring interval - cron


        //reply email - add hook for when a new reply is tracked
    }

    /**
     * @param Controller $controller
     * @param CampaignTrackOpen $track
     * @param Campaign $campaign
     *
     * @return void
     * @since 1.6.8
     */
    public function _openTrigger(Controller $controller, CampaignTrackOpen $track, Campaign $campaign,  ListSubscriber $subscriber)
    {
        $models = CampaignTrackOpenWebhook::model()->findAllByAttributes([
            'campaign_id' => $campaign->campaign_id,
        ]);

        if (empty($models)) {
            return;
        }

        foreach ($models as $model) {
            $request = new CampaignTrackOpenWebhookQueue();
            $request->webhook_id    = (int)$model->webhook_id;
            $request->track_open_id = $track->id;
            $request->save(false);
        }
    }

    /**
     * @param Controller $controller
     * @param Campaign $campaign
     * @param ListSubscriber $subscriber
     * @param CampaignUrl $url
     *
     * @return void
     * @throws CException
     * @throws MaxMind\Db\Reader\InvalidDatabaseException
     */
    public function _urlClickTrigger(Controller $controller, Campaign $campaign, ListSubscriber $subscriber, CampaignUrl $url)
    {
        $models = CampaignTemplateUrlActionListField::model()->findAllByAttributes([
            'campaign_id' => $campaign->campaign_id,
            'url'         => $url->destination,
        ]);

        if (empty($models)) {
            return;
        }

        foreach ($models as $model) {
            $valueModel = ListFieldValue::model()->findByAttributes([
                'field_id'      => (int)$model->field_id,
                'subscriber_id' => (int)$subscriber->subscriber_id,
            ]);
            if (empty($valueModel)) {
                $valueModel = new ListFieldValue();
                $valueModel->field_id       = (int)$model->field_id;
                $valueModel->subscriber_id  = (int)$subscriber->subscriber_id;
            }

            $valueModel->value = $model->getParsedFieldValueByListFieldValue(new CAttributeCollection([
                'valueModel' => $valueModel,
                'campaign'   => $campaign,
                'subscriber' => $subscriber,
                'url'        => $url,
                'event'      => 'campaign:subscriber:track:click',
            ]));
            $valueModel->save();
        }
    }


    /**
     * @param Campaign $campaign
     * @param ListSubscriber $subscriber
     * @param ReplyTrackerExtLog $replyLog
     *
     * @return void
     * @throws CException
     */
    public function _replyTrigger(Campaign $campaign, ListSubscriber $subscriber, $replyLog)
    {
    }



    /**
     * @param CAction $action
     *
     * @return void
     * @throws CException
     */
    public function _insertListCallbacks(CAction $action)
    {
        if (!in_array($action->getId(), array_keys($this->listActionToPageType))) {
            return;
        }

        $list_uid = (string)request()->getQuery('list_uid', '');
        if (empty($list_uid)) {
            return;
        }

        /** @var Lists|null $list */
        $list = Lists::model()->findByUid($list_uid);
        if (empty($list)) {
            return;
        }

        /** @var ListPageType|null $pageType */
        $pageType = ListPageType::model()->findByAttributes([
            'slug' => $this->listActionToPageType[$action->getId()],
        ]);
        if (empty($pageType)) {
            return;
        }

        //get trigger group
        $trigger = "";
        if ($pageType->slug == "unsubscribe-confirm")
            $trigger = AutomationExtCanvasBlockTypes::LIST_UNSUBSCRIPTION;

        if ($pageType->slug == "subscribe-confirm")
            $trigger = AutomationExtCanvasBlockTypes::LIST_SUBSCRIPTION;

        if (empty($trigger)) {

            return;
        }

        /** @var AutomationExtModel[] $ */
        $automations = AutomationExtModel::model()->findAllByAttributes([
            'trigger'   => $trigger,
            'trigger_value'   => $list->list_uid,
        ]);

        //not related to any automation
        if (empty($automations)) {
            return;
        }

        if (!$action->getController()->asa('callbacks')) {
            return;
        }

        $this->ext->setData('automations', $automations);
        $this->ext->setData('trigger', $trigger);

        $action->getController()->callbacks->onSubscriberSaveSuccess = [$this, '_sendListData'];
    }


    /**
     * @param CEvent $event
     *
     * @return void
     * @throws CException
     */
    public function _sendListData(CEvent $event)
    {
        //subscribe and unsubscribe handler.
        /*["subscriber" => ListSubscriber {#1761 ▶}
        "list" => Lists {#1762 ▶}
        "action" => "subscribe-confirm"
        "do" => null]*/

        dd($event->params);

        /** @var AutomationExtModel[] $automations */
        $automations = $this->ext->getData('automations');
        $trigger = $this->ext->getData('trigger');

        if (empty($automations) || empty($trigger)) {
            return;
        }

        $actions = ['subscribe-confirm', 'unsubscribe-confirm'];
        if (!isset($event->params['action']) || !in_array($event->params['action'], $actions)) {
            return;
        }

        $data = [];

        /** @var ListSubscriber $subscriber */
        $subscriber = $event->params['subscriber'];

        /** @var Lists $list */
        $list = $event->params['list'];

        $data['action']      = $event->params['action'];
        $data['list']        = $list->getAttributes(['list_uid', 'name']);
        $data['subscriber']  = $subscriber->getAttributes(['subscriber_uid', 'email']);
        $data['trigger'] = $trigger;

        foreach ($automations as $automation) {

            $automation->__process($data);
        }
    }


    /* public function _sendListData(CEvent $event)
    {
        
        $data['action']      = $event->params['action'];
        $data['list']        = $list->getAttributes(['list_uid', 'name']);
        $data['subscriber']  = $subscriber->getAttributes(['subscriber_uid', 'email']);
        $data['form_fields'] = (array)request()->getPost('', []);

        if (isset($data['form_fields'][request()->csrfTokenName])) {
            unset($data['form_fields'][request()->csrfTokenName]);
        }

        $data['optin_history'] = [];
        if (!empty($subscriber->optinHistory)) {
            $data['optin_history'] = $subscriber->optinHistory->getAttributes([
                'optin_ip', 'optin_date', 'confirm_ip', 'confirm_date',
            ]);
        }

        $data   = ['data' => $data];
        $client = new GuzzleHttp\Client(['timeout' => 5]);

        foreach ($automations as $automation) {
            
            $campaign = new Campaign();
            $campaign->customer_id = (int)$list->customer_id;
            $campaign->list_id     = (int)$list->list_id;

            [, , $url] = CampaignHelper::parseContent($webhook->request_url, $campaign, $subscriber);
            try {
                if ($webhook->request_type == ListFormCustomWebhook::REQUEST_TYPE_POST) {
                    $client->post($url, ['form_params' => $data]);
                } elseif ($webhook->request_type == ListFormCustomWebhook::REQUEST_TYPE_GET) {
                    $client->get($url, ['query' => $data]);
                }
            } catch (Exception $e) {
            }
        }
    }*/

    public static function getTriggerSubscribers($trigger_type, $last_run, $trigger_value)
    {

        $subscribers = [];

        switch ($trigger_type) {


            case AutomationExtCanvasBlockTypes::LIST_SUBSCRIPTION:
            case AutomationExtCanvasBlockTypes::LIST_UNSUBSCRIPTION:

                $criteria = new CDbCriteria();
                $criteria->select    = 'reference_id as subscriber_id, reference_relation_id as list_id, customer_id, date_added';
                $criteria->addCondition('t.date_added >= :last_run');
                $criteria->addCondition('category = :category');
                $criteria->addCondition('reference_relation_id = :list_id');
                //$criteria->limit     = 1000;

                $category = AutomationExtCanvasBlockTypes::LIST_SUBSCRIPTION ?
                    CustomerActionLog::CATEGORY_LISTS_SUBSCRIBERS_CREATED :
                    CustomerActionLog::CATEGORY_LISTS_SUBSCRIBERS_UNSUBSCRIBED;

                $criteria->params[':last_run'] = $last_run;
                $criteria->params[':category'] = $category;
                $criteria->params[':list_id'] = (int)$trigger_value;

                $subscribers = CustomerActionLog::model()->findAll($criteria);
                break;

            case AutomationExtCanvasBlockTypes::SUBSCRIBER_ADDED_DATE:

                $criteria = new CDbCriteria();
                $criteria->select    = 'subscriber_id, list_id';
                $criteria->addCondition('list_id = :list_id');
                $criteria->addCondition("DATE_FORMAT(date_added, '%m-%d') = DATE_FORMAT(NOW(), '%m-%d')");
                $criteria->params[':list_id']    = (int)$trigger_value;

                $subscribers = ListSubscriber::model()->findAll($criteria);
                break;

            case AutomationExtCanvasBlockTypes::OPEN_EMAIL:

                $criteria = new CDbCriteria();
                $criteria->select    = 'DISTINCT(subscriber_id) as subscriber_id, campaign_id';
                $criteria->addCondition('campaign_id = :campaign_id');
                $criteria->addCondition("date_added >= :last_run");
                $criteria->params[':list_id'] = (int)$trigger_value;
                $criteria->params[':last_run'] = $last_run;

                $subscribers = CampaignTrackOpen::model()->findAll($criteria);
                break;

            case AutomationExtCanvasBlockTypes::CLICK_URL:

                $criteria = new CDbCriteria();
                $criteria->select    = 'DISTINCT(subscriber_id) as subscriber_id, url_id';
                $criteria->addCondition('url_id = :url_id');
                $criteria->addCondition("date_added >= :last_run");
                $criteria->params[':url_id'] = (int)$trigger_value;
                $criteria->params[':last_run'] = $last_run;

                $subscribers = CampaignTrackUrl::model()->findAll($criteria);
                break;

            case AutomationExtCanvasBlockTypes::REPLY_EMAIL:

                if (!class_exists('ReplyTrackerExtLogModel'))
                    throw new Exception("Reply Tracker is required for this trigger", 1);

                $criteria = new CDbCriteria();
                $criteria->select    = 'DISTINCT(subscriber_id) as subscriber_id, campaign_id';
                $criteria->group = 'campaign_id';
                $criteria->addCondition('campaign_id = :campaign_id');
                $criteria->addCondition("date_added >= :last_run");
                $criteria->params[':campaign_id'] = (int)$trigger_value;
                $criteria->params[':last_run'] = $last_run;

                $subscribers = ReplyTrackerExtLogModel::model()->findAll($criteria);

                break;
            case AutomationExtCanvasBlockTypes::WEBHOOK:
                # code...
                break;
            case AutomationExtCanvasBlockTypes::SPECIFIC_DATE:
                # code...
                break;
            case AutomationExtCanvasBlockTypes::INTERVAL:
                # code...
                break;



            default:
                # code...
                break;
        }

        return $subscribers;
    }
}
