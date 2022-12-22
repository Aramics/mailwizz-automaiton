<?php

defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the helper class for automation model canvas data management.
 */

class AutomationExtGroupTriggers
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

        //list subscribe
        //list unsubscribe
        if ($ext->isAppName('frontend')) {
            $hooks->addAction('frontend_controller_lists_before_action', [$this, '_insertListCallbacks']);
        }

        //dd($this);

        //webhook api

        //specific date

        //subscriber date added

        //recurring interval

        //open email

        //click url in email

        //reply email
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
            $trigger = AutomationExtBlockTypes::LIST_UNSUBSCRIPTION;

        if ($pageType->slug == "subscribe-confirm")
            $trigger = AutomationExtBlockTypes::LIST_SUBSCRIPTION;

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
}