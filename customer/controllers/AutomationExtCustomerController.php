<?php defined('MW_PATH') || exit('No direct script access allowed');

/** 
 * Controller file for managing automations.
 * 
 */

class AutomationExtCustomerController extends Controller
{
    // the extension instance
    public $extension;

    /**
     * @inheritDoc
     */
    public function getViewPath()
    {
        return Yii::getPathOfAlias('ext-automation.customer.views');
    }

    /**
     * @return array
     */
    public function accessRules()
    {
        return [
            // allow all authenticated users on all actions
            ['allow', 'users' => ['@']],
            // deny all rule.
            ['deny'],
        ];
    }

    /**
     * show stat of reply tracker
     * This render the dashboard page.
     */
    public function actionIndex()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $automation  = new AutomationExtModel('search');
        //$automation->unsetAttributes();

        $automation->attributes = (array)$request->getQuery($automation->modelName, array());
        $automation->customer_id = (int)$customer->customer_id;

        $stat = [];
        $tops = [];
        //total inbound reply tracker monitors
        $stat['total_automations'] = [
            'count' => AutomationExtModel::model()->CountByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            )),
            'url' => $this->createUrl('automations/index')
        ];
        //get total reply
        $stat['total_active'] = [
            'count' => AutomationExtModel::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
                'status' => 'running'
            )),
            'url' => $this->createUrl('automations/index')
        ];

        //ge unique response from log
        $stat['total_inactive'] = [
            'count' => AutomationExtModel::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
                'status' => 'draft'
            )),
            'url' => $this->createUrl('automations/index')
        ];

        $this->setData([
            'pageMetaTitle'   => $this->getData('pageMetaTitle') . ' | ' . $this->extension->t('Automation Dashboard'),
            'pageHeading'     => $this->extension->t('Automations'),
            'pageBreadcrumbs' => [
                $this->extension->t('Automations') => $this->createUrl('automations/index'),
            ]
        ]);

        $this->render('index', compact('automation', 'stat'));
    }

    /**
     * Create a new automation
     */
    public function actionCreate()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $automation  = new AutomationExtModel();
        $automation->customer_id    = (int)$customer->customer_id;

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($automation->modelName, array()))) {

            $automation->attributes = $attributes;

            $automation_tmp = AutomationExtModel::model()->findByAttributes(array('title' => $attributes['title']));
            if (!empty($automation_tmp)) {

                $notify->addError($this->extension->t("You already have an automation with this title"));
                $this->redirect(array('automations/create'));
                return;
            }

            if (!$automation->save()) {
                $notify->addError(CHtml::errorSummary($automation));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }

            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller' => $this,
                'success'   => $notify->hasSuccess,
                'automation'    => $automation,
            )));

            if ($collection->success) {

                $this->redirect(array('automations/update', 'id' => $automation->automation_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | ' . $this->extension->t('Create new automation'),
            'pageHeading'       => $this->extension->t('Create new automation'),
            'pageBreadcrumbs'   => array(
                $this->extension->t('Automation') => $this->createUrl('automations/index'),
                Yii::t('app', 'Create new'),
            )
        ));

        $this->render('form', compact('automation'));
    }
    /**
     * Update existing automation
     */
    public function actionCanvasTest($id)
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        $message = '';

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            if ($request->getIsAjaxRequest()) {

                $this->renderJson([
                    'success' => false,
                    'message' => $message
                ], 404);
                return;
            }

            throw new CHttpException(404, $message);
        }
        $canvas = new AutomationExtCanvas($automation);
        dd($canvas->run(['automation_id' => $automation->automation_id]));
        $automation->__process([]);
    }

    /**
     * Update existing automation
     */
    public function actionUpdate($id)
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        $message = '';

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            if ($request->getIsAjaxRequest()) {

                $this->renderJson([
                    'success' => false,
                    'message' => $message
                ], 404);
                return;
            }

            throw new CHttpException(404, $message);
        }


        //automation cant be updated cron_running or hidden status
        if (!$automation->getCanBeUpdated()) {

            $message = $this->extension->t('Automation can not be updated at this time');

            if ($request->getIsAjaxRequest()) {
                $this->renderJson([
                    'success' => false,
                    'message' => $message,
                ]);
                return;
            }

            $notify->addWarning($this->extension->t($message));
            $this->redirect(array('automations/index'));
        }


        //Cron locked
        if ($automation->getIsLocked()) {

            $message = 'This automation is locked, you cannot change or delete it!';

            if ($request->getIsAjaxRequest()) {
                $this->renderJson([
                    'success' => false,
                    'message' => $this->extension->t($message)
                ]);
                return;
            }

            $notify->addWarning($this->extension->t($message));
            $this->redirect(array('automations/index'));
        }


        //post request submission handling
        if ($request->isPostRequest) {

            $attributes = $request->getOriginalPost('', []);
            if ($attributes && isset($attributes[$automation->modelName]))
                $attributes = $automation->modelName;

            $attributes = (array)$attributes;

            $message = $this->extension->t('Invalid data structure');
            $success = false;

            if (!empty($attributes)) {

                $title = $attributes['title'];
                $canvas_data = $attributes['canvas_data'];

                try {

                    //create and validate canvas or throw exception.
                    $canvas = new AutomationExtCanvas($automation);
                    $trigger = $canvas->getTriggerBlock();
                    $trigger_value = $trigger->getTriggerValue();

                    if (!$trigger)
                        throw new Exception($this->extension->t("Unkown trigger"), 1);

                    if (!$trigger_value)
                        throw new Exception($this->extension->t("Trigger value is missing"), 1);


                    $automation->canvas_data = $canvas_data;

                    $automation->trigger = $trigger->getType();
                    $automation->trigger_value = trim($trigger_value);

                    if ($title)
                        $automation->title = $title;

                    if (!$automation->save()) {

                        $message = Yii::t('app', 'Your form has a few errors, please fix them and try again!');
                        $notify->addError($message);
                    } else {

                        $message = Yii::t('app', 'Your form has been successfully saved!');
                        $notify->addSuccess($message);
                    }

                    Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                        'controller' => $this,
                        'success'    => $notify->hasSuccess,
                        'automation'     => $automation,
                    )));

                    if ($collection->success) {

                        $success = true;
                    }
                } catch (\Throwable $th) {

                    $message = $th->getMessage();
                    $notify->addError($message);
                }
            }

            return $this->renderJson([
                'success' => $success,
                'message' => $message
            ]);
        }


        $this->setData(array(
            'pageMetaTitle'   => $this->data->pageMetaTitle . ' | ' . $this->extension->t('Update automation'),
            'pageHeading'     => $this->extension->t('Update automation'),
            'pageBreadcrumbs' => array(
                $this->extension->t('Automation') => $this->createUrl('automations/index'),
                Yii::t('app', 'Update'),
            )
        ));


        $cs = clientScript();
        $cs->reset();

        if (request()->enableCsrfValidation) {
            $cs->registerMetaTag(request()->csrfTokenName, 'csrf-token-name');
            $cs->registerMetaTag(request()->getCsrfToken(), 'csrf-token-value');
        }

        $this->renderPartial('canvas', compact('automation'), false, true);
    }

    /**
     * Delete existing automation
     */
    public function actionDelete($id)
    {
        $customer = Yii::app()->customer->getModel();
        $automation   = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;


        if (empty($automation)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        if ($automation->getIsLocked()) {
            $notify->addWarning($this->extension->t('This automation is locked, you cannot update, enable, disable, copy or delete it!'));
            if (!$request->isAjaxRequest) {
                $this->redirect($request->getPost('returnUrl', array('automations/index')));
            }
            Yii::app()->end();
        }

        if ($automation->getCanBeDeleted()) {
            $automation->delete();
        }


        $redirect = null;
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $redirect = $request->getPost('returnUrl', array('automations/index'));
        }

        // since 1.3.5.9 MailWizz EMA
        Yii::app()->hooks->doAction('controller_action_delete_data', $collection = new CAttributeCollection(array(
            'controller' => $this,
            'model'      => $automation,
            'redirect'   => $redirect,
        )));

        if ($collection->redirect) {
            $this->redirect($collection->redirect);
        }
    }

    /**
     * Run a bulk action against the automations
     */
    public function actionBulk_action()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        $action = $request->getPost('bulk_action');
        $items  = array_unique(array_map('intval', (array)$request->getPost('bulk_item', array())));

        if ($action == AutomationExtModel::BULK_ACTION_DELETE && count($items)) {
            $affected = 0;
            foreach ($items as $item) {
                $automation = AutomationExtModel::model()->findByAttributes(array(
                    'automation_id'   => (int)$item,
                    'customer_id' => (int)$customer->customer_id,
                ));

                if (empty($automation)) {
                    continue;
                }

                if (!$automation->getCanBeDeleted()) {
                    continue;
                }

                $automation->delete();
                $affected++;
            }
            if ($affected) {
                $notify->addSuccess(Yii::t('app', 'The action has been successfully completed!'));
            }
        }

        $defaultReturn = $request->getServer('HTTP_REFERER', array('automations/index'));
        $this->redirect($request->getPost('returnUrl', $defaultReturn));
    }

    /**
     * Create a copy of an existing automation
     */
    public function actionCopy($id)
    {
        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        if (empty($automation)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($automation->copy()) {
            $notify->addSuccess($this->extension->t('Your automation has been successfully copied!'));
        } else {
            $notify->addError($this->extension->t('Unable to copy the automation!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('automations/index')));
        }
    }

    /**
     * Enable a automation that has been previously disabled
     */
    public function actionEnable($id)
    {
        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        if (empty($automation)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($automation->getIsLocked()) {
            $notify->addWarning($this->extension->t('This automation is locked, you cannot update, enable, disable, copy or delete it!'));
            if (!$request->isAjaxRequest) {
                $this->redirect($request->getPost('returnUrl', array('automations/inbounds')));
            }
            Yii::app()->end();
        }

        if ($automation->getIsDisabled()) {
            $automation->enable();
            $notify->addSuccess($this->extension->t('Your automation has been successfully enabled!'));
        } else {
            $notify->addError($this->extension->t('The automation must be disabled in order to enable it!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('automations/inbounds')));
        }
    }

    /**
     * Disable a automation that has been previously verified
     */
    public function actionDisable($id)
    {
        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        if (empty($automation)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($automation->getIsLocked()) {
            $notify->addWarning($this->extension->t('This automation is locked, you cannot update, enable, disable, copy or delete it!'));
            if (!$request->isAjaxRequest) {
                $this->redirect($request->getPost('returnUrl', array('automations/inbounds')));
            }
            Yii::app()->end();
        }

        if ($automation->getIsActive()) {
            $automation->disable();
            $notify->addSuccess($this->extension->t('Your automation has been successfully disabled!'));
        } else {
            $notify->addError($this->extension->t('The automation must be active in order to disable it!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('automations/inbounds')));
        }
    }

    /**
     * Export list of automations
     */
    public function actionExport()
    {
        $notify = Yii::app()->notify;

        $models = AutomationExtModel::model()->findAllByAttributes(array(
            'customer_id' => (int)Yii::app()->customer->getId(),
        ));

        if (empty($models)) {
            $notify->addError(Yii::t('app', 'There is no item available for export!'));
            $this->redirect(array('index'));
        }

        if (!($fp = fopen('php://output', 'w'))) {
            $notify->addError(Yii::t('app', 'Unable to access the output for writing the data!'));
            $this->redirect(array('index'));
        }

        /* Set the download headers */
        HeaderHelper::setDownloadHeaders('automations.csv');

        $attributes = AttributeHelper::removeSpecialAttributes($models[0]->attributes, array('password'));
        fputcsv($fp, array_map(array($models[0], 'getAttributeLabel'), array_keys($attributes)), ',', '"');

        foreach ($models as $model) {
            $attributes = AttributeHelper::removeSpecialAttributes($model->attributes, array('password'));
            fputcsv($fp, array_values($attributes), ',', '"');
        }

        fclose($fp);
        Yii::app()->end();
    }


    public function actionCampaigns($id)
    {

        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        $message = '';

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            $this->renderJson([
                'success' => false,
                'message' => $message
            ], 404);

            return;
        }

        $criteria = new CDbCriteria();
        $criteria->compare('customer_id', (int)$automation->customer_id);
        $criteria->addNotInCondition('status', [Campaign::STATUS_PENDING_DELETE]);
        $criteria->order    = 't.campaign_id DESC';
        $criteria->limit    = 1000;


        $data = [
            'campaigns_templates' => [],
            'regular_campaigns' => [],
        ];

        $campaigns = Campaign::model()->findAll($criteria);

        foreach ($campaigns as $campaign) {
            if (!empty($campaign->template->content)) {

                $isAutoresponder = $campaign->getIsAutoresponder();
                $campaign = $campaign->getAttributes(['campaign_id', 'type', 'name', 'status']);
                $campaign['key'] = $campaign['campaign_id'];
                $campaign['label'] = $campaign['name'];

                //list of campaigns which templates can be used for sending transactional email.
                $data['campaigns_templates'][] = $campaign;

                //we dont want to run autoresponders as campaign
                if (!$isAutoresponder)
                    $data['regular_campaigns'][] = $campaign;
            }
        }

        $this->renderJson([
            'success' => true,
            'data'      => $data,
        ]);
    }

    public function actionLists($id)
    {

        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        $message = '';

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            $this->renderJson([
                'success' => false,
                'message' => $message
            ], 404);

            return;
        }

        $criteria = new CDbCriteria();
        $criteria->compare('customer_id', (int)$automation->customer_id);
        $criteria->addNotInCondition('status', [Lists::STATUS_PENDING_DELETE, Lists::STATUS_ARCHIVED]);
        $criteria->order    = 't.list_id DESC';
        $criteria->limit    = 1000;

        $data = [];

        $lists = Lists::model()->findAll($criteria);

        foreach ($lists as $list) {

            $list = $list->getAttributes(['list_id', 'name']);
            $list['label'] = $list['name'];
            $list['key'] = $list['list_id'];
            $data[] = $list;
        }

        $this->renderJson([
            'success' => true,
            'data'      => $data,
        ]);
    }

    public function actionCampaign_urls($id)
    {

        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        $request = Yii::app()->request;

        $message = '';

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            $this->renderJson([
                'success' => false,
                'message' => $message
            ], 404);

            return;
        }

        $campaign_id = $request->getQuery('campaign_id', '');
        $campaign = Campaign::model()->findByPk($campaign_id);

        $data = [];
        foreach ($campaign->urls as $url) {
            $destination = $url->attributes['destination'];
            if (!in_array(pathinfo($destination, PATHINFO_EXTENSION), ['png', 'css', 'js', 'jpg', 'gif', 'jpeg']))
                $data[] = ['key' => $url->attributes['url_id'], 'label' => $destination];
        }

        $this->renderJson([
            'success' => true,
            'data'      => $data,
        ]);
    }

    public function actionSubscriber_actions($id)
    {

        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            $this->renderJson([
                'success' => false,
                'message' => $message
            ], 404);

            return;
        }

        $subscriber = new ListSubscriber();
        $actions = $subscriber->getBulkActionsList();

        $data = [];
        foreach ($actions as $action => $label) {
            $data[] = ['key' => $action, 'label' => $label];
        }

        $this->renderJson([
            'success' => true,
            'data'      => $data,
        ]);
    }

    public function actionCampaign_actions($id)
    {

        $customer = Yii::app()->customer->getModel();
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        //invalid automation id
        if (empty($automation)) {

            $message = Yii::t('app', 'The requested page does not exist.');

            $this->renderJson([
                'success' => false,
                'message' => $message
            ], 404);

            return;
        }

        $actions = $automation->campaignBlockActionsList();
        $data = [];
        foreach ($actions as $action => $label) {
            $data[] = ['key' => $action, 'label' => sprintf($label, '$campaign')];
        }

        $this->renderJson([
            'success' => true,
            'data'      => $data,
        ]);
    }

    /**
     * This method ensure json is return even in debug mode. It disable yii log getting returned with json content.
     * 
     * @return
     */
    public function renderJson($data = [], $statusCode = 200, array $headers = [], $callback = null)
    {
        foreach (Yii::app()->log->routes as $route) {

            if ($route instanceof CWebLogRoute) {

                $route->enabled = false;
            }
        }

        parent::renderJson($data, $statusCode, $headers, $callback);
    }
}