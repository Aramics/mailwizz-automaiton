<?php defined('MW_PATH') || exit('No direct script access allowed');

/** 
 * Controller file for managing inbound servers.
 * 
 */
 
class ReplyTrackerExtCustomerInboundController extends Controller
{
    // the extension instance
    public $extension;
    
    // the init
    public function init()
    {   
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('campaigns.js')));
        $this->onBeforeAction = array($this, '_registerJuiBs');
        parent::init();
    }
    
    /**
     * @inheritDoc
     */
    public function getViewPath()
    {
        return Yii::getPathOfAlias('ext-reply-tracker.customer.views');
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        $filters = array(
            'postOnly + delete',
        );

        return CMap::mergeArray($filters, parent::filters());
    }

    /**
     * show stat of reply tracker
     * This render the dashboard page.
     */
    public function actionIndex()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $server  = new ReplyTrackerExtInboundModel('search');
        $server->unsetAttributes();

        $server->attributes = (array)$request->getQuery($server->modelName, array());
        $server->customer_id = (int)$customer->customer_id;
        $stat = [];
        $tops = [];
        //total inbound reply tracker monitors
        $stat['total_inbounds'] = [
            'count' =>ReplyTrackerExtInboundModel::model()->CountByAttributes(array(
                    'customer_id' => (int)$customer->customer_id,
                )),
            'url' => $this->createUrl('reply_tracker/inbounds')
        ];
        //get total reply
        $stat['total_reply'] = [
            'count' => ReplyTrackerExtLogModel::model()->countByAttributes(array(
                    'customer_id' => (int)$customer->customer_id,
                )),
            'url' => $this->createUrl('reply_tracker_log/index')
        ];
        
        //ge unique response from log
        $stat['total_unique_reply'] = [
            'count' => ReplyTrackerExtLogModel::model()->countByAttributes(array(
                    'customer_id' => (int)$customer->customer_id,
                ),['select'=>'t.campaign_id,t.message','group'=>'t.campaign_id,t.message','distinct' => true]),
            'url' => $this->createUrl('reply_tracker_log/index')
        ];
        
        //top replied campaign belonging to user.
        $tops['most_replied_campaign'] = Yii::app()->db->createCommand('SELECT `name`,`campaign_uid`,r.`campaign_id`,count(r.`campaign_id`) AS total, count(DISTINCT r.`campaign_id`) AS total_unique, count(DISTINCT r.`subscriber_id`) AS total_subscribers FROM `{{reply_tracker_log}}` as r JOIN {{campaign}} c on c.`campaign_id` =r.campaign_id WHERE r.`customer_id`=:customer_id GROUP BY `campaign_id` ORDER BY total DESC LIMIT 5')->bindValue('customer_id',$customer->customer_id)->queryAll();
       
        $this->setData([
            'pageMetaTitle'   => $this->getData('pageMetaTitle') . ' | '. $this->extension->t('Reply Tracker Dashboard'),
            'pageHeading'     => $this->extension->t('Reply Tracker'),
            'pageBreadcrumbs' => [
                $this->extension->t('Extensions') => $this->createUrl('extensions/index'),
                $this->extension->t('RT Dashboard') => $this->createUrl('reply_tracker/index'),
            ]
        ]);

        $this->render('index', compact('server','stat','tops'));
    }

    /**
     * List available incoming reply/inbound servers
     */
    public function actionInbounds()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $server  = new ReplyTrackerExtInboundModel('search');
        $server->unsetAttributes();

        $server->attributes = (array)$request->getQuery($server->modelName, array());
        $server->customer_id = (int)$customer->customer_id;
       
        $this->setData([
            'pageMetaTitle'   => $this->getData('pageMetaTitle') . ' | '. $this->extension->t('Reply Tracker'),
            'pageHeading'     => $this->extension->t('Reply Tracker'),
            'pageBreadcrumbs' => [
                $this->extension->t('Extensions') => $this->createUrl('extensions/index'),
                $this->extension->t('Reply Tracker') => $this->createUrl('reply_tracker/inbounds'),
            ]
        ]);

        $this->render('inbounds/list', compact('server'));
    }

    /**
     * Create a new incoming reply/inbound server
     */
    public function actionCreate()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $server  = new ReplyTrackerExtInboundModel();
        $server->customer_id    = (int)$customer->customer_id;

        $emailBoxMonitors = EmailBoxMonitor::model()->findAllByAttributes(array(
            'customer_id'   => (int)Yii::app()->customer->getId(),
        ));
        if ($emailBoxMonitors === null)
            $emailBoxMonitors = [];

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
            
            $server->attributes = $attributes;
            
            if(isset($attributes['email_box_monitor_id']) && !empty($attributes['email_box_monitor_id'])){
                //creating from existing email box monitor, we simply copy the email box attribute to reply sever
                $ebm_id = $attributes['email_box_monitor_id'];
                $server_tmp = EmailBoxMonitor::model()->find($ebm_id);
                if($server_tmp !== null){
                    $data = $server_tmp->attributes;
                    $server->setAttributes($data);
                    $server->email_box_monitor_id = $ebm_id;
                }
            }
            if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->conditions = Yii::app()->params['POST'][$server->modelName]['conditions'];
            if (!$server->testConnection() || !$server->save()) {
                $notify->addError(CHtml::errorSummary($server));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }

            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller'=> $this,
                'success'   => $notify->hasSuccess,
                'server'    => $server,
            )));

            if ($collection->success) {
                $this->redirect(array('reply_tracker/update', 'id' => $server->server_id));
            }
        }

        $this->setData(array(
            'pageMetaTitle'     => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Create new email box monitor'),
            'pageHeading'       => Yii::t('servers', 'Create new email box monitor'),
            'pageBreadcrumbs'   => array(
                Yii::t('servers', 'Email box monitors') => $this->createUrl('reply_tracker/index'),
                Yii::t('app', 'Create new'),
            )
        ));

        $this->render('inbounds/form', compact('server','emailBoxMonitors'));
    }

    /**
     * Update existing reply/inbound server monitor
     */
    public function actionUpdate($id)
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $server = ReplyTrackerExtInboundModel::model()->findByAttributes(array(
            'server_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }
        
        if (!$server->getCanBeUpdated()) {
            $this->redirect(array('reply_tracker/inbounds'));
        }


        if ($server->getIsLocked()) {
            $notify->addWarning(Yii::t('servers', 'This server is locked, you cannot change or delete it!'));
            $this->redirect(array('reply_tracker/inbounds'));
        }

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($server->modelName, array()))) {
           if (!$server->isNewRecord && empty($attributes['password']) && isset($attributes['password'])) {
                unset($attributes['password']);
            }
            $server->attributes = $attributes;
            $server->conditions = Yii::app()->params['POST'][$server->modelName]['conditions'];
            $server->customer_id = $customer->customer_id;
            if (!$server->testConnection() || !$server->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }

            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller' => $this,
                'success'    => $notify->hasSuccess,
                'server'     => $server,
            )));

            if ($collection->success) {

            }
        }
        

        $this->setData(array(
            'pageMetaTitle'   => $this->data->pageMetaTitle . ' | '. Yii::t('servers', 'Update reply tracker'),
            'pageHeading'     => Yii::t('servers', 'Update reply tracker'),
            'pageBreadcrumbs' => array(
                Yii::t('servers', 'Reply Trackers') => $this->createUrl('reply_tracker/index'),
                Yii::t('app', 'Update'),
            )
        ));

        $this->render('inbounds/form', compact('server'));
    }

    /**
     * Delete existing reply/inbound server
     */
    public function actionDelete($id)
    {
        $customer = Yii::app()->customer->getModel();
        $server   = ReplyTrackerExtInboundModel::model()->findByAttributes(array(
            'server_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;


        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        if ($server->getIsLocked()) {
            $notify->addWarning(Yii::t('servers', 'This server is locked, you cannot update, enable, disable, copy or delete it!'));
            if (!$request->isAjaxRequest) {
                $this->redirect($request->getPost('returnUrl', array('reply_tracker/inbounds')));
            }
            Yii::app()->end();
        }

        if ($server->getCanBeDeleted()) {
            $server->delete();
        }

        
        $redirect = null;
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $redirect = $request->getPost('returnUrl', array('reply_tracker/index'));
        }

        // since 1.3.5.9 MailWizz EMA
        Yii::app()->hooks->doAction('controller_action_delete_data', $collection = new CAttributeCollection(array(
            'controller' => $this,
            'model'      => $server,
            'redirect'   => $redirect,
        )));

        if ($collection->redirect) {
            $this->redirect($collection->redirect);
        }
    }

    /**
     * Run a bulk action against the servers
     */
    public function actionBulk_action()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        $action = $request->getPost('bulk_action');
        $items  = array_unique(array_map('intval', (array)$request->getPost('bulk_item', array())));

        if ($action == ReplyTrackerExtInboundModel::BULK_ACTION_DELETE && count($items)) {
            $affected = 0;
            foreach ($items as $item) {
                $server = ReplyTrackerExtInboundModel::model()->findByAttributes(array(
                    'server_id'   => (int)$item,
                    'customer_id' => (int)$customer->customer_id,
                ));

            if (empty($server)) {
                    continue;
                }

                if (!$server->getCanBeDeleted()) {
                    continue;
                }

                $server->delete();
                $affected++;
            }
            if ($affected) {
                $notify->addSuccess(Yii::t('app', 'The action has been successfully completed!'));
            }
        }

        $defaultReturn = $request->getServer('HTTP_REFERER', array('reply_tracker/index'));
        $this->redirect($request->getPost('returnUrl', $defaultReturn));
    }

    /**
     * Create a copy of an existing server
     */
    public function actionCopy($id)
    {
        $customer = Yii::app()->customer->getModel();
        $server = ReplyTrackerExtInboundModel::model()->findByAttributes(array(
            'server_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($server->copy()) {
            $notify->addSuccess(Yii::t('servers', 'Your server has been successfully copied!'));
        } else {
            $notify->addError(Yii::t('servers', 'Unable to copy the server!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('reply_tracker/inbounds')));
        }
    }

    /**
     * Enable a server that has been previously disabled
     */
    public function actionEnable($id)
    {
        $customer = Yii::app()->customer->getModel();
        $server = ReplyTrackerExtInboundModel::model()->findByAttributes(array(
            'server_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($server->getIsLocked()) {
            $notify->addWarning(Yii::t('servers', 'This server is locked, you cannot update, enable, disable, copy or delete it!'));
            if (!$request->isAjaxRequest) {
                $this->redirect($request->getPost('returnUrl', array('reply_tracker/inbounds')));
            }
            Yii::app()->end();
        }

        if ($server->getIsDisabled()) {
            $server->enable();
            $notify->addSuccess(Yii::t('servers', 'Your server has been successfully enabled!'));
        } else {
            $notify->addError(Yii::t('servers', 'The server must be disabled in order to enable it!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('reply_tracker/inbounds')));
        }
    }

    /**
     * Disable a server that has been previously verified
     */
    public function actionDisable($id)
    {
        $customer = Yii::app()->customer->getModel();
        $server = ReplyTrackerExtInboundModel::model()->findByAttributes(array(
            'server_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        if (empty($server)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        if ($server->getIsLocked()) {
            $notify->addWarning(Yii::t('servers', 'This server is locked, you cannot update, enable, disable, copy or delete it!'));
            if (!$request->isAjaxRequest) {
                $this->redirect($request->getPost('returnUrl', array('reply_tracker/inbounds')));
            }
            Yii::app()->end();
        }

        if ($server->getIsActive()) {
            $server->disable();
            $notify->addSuccess(Yii::t('servers', 'Your server has been successfully disabled!'));
        } else {
            $notify->addError(Yii::t('servers', 'The server must be active in order to disable it!'));
        }

        if (!$request->isAjaxRequest) {
            $this->redirect($request->getPost('returnUrl', array('reply_tracker/inbounds')));
        }
    }

    /**
     * Export list of inbound/reply tracker servers
     */
    public function actionExport()
    {
        $notify = Yii::app()->notify;

        $models = ReplyTrackerExtInboundModel::model()->findAllByAttributes(array(
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
        HeaderHelper::setDownloadHeaders('reply_trackers.csv');

        $attributes = AttributeHelper::removeSpecialAttributes($models[0]->attributes, array('password'));
        fputcsv($fp, array_map(array($models[0], 'getAttributeLabel'), array_keys($attributes)), ',', '"');

        foreach ($models as $model) {
            $attributes = AttributeHelper::removeSpecialAttributes($model->attributes, array('password'));
            fputcsv($fp, array_values($attributes), ',', '"');
        }

        fclose($fp);
        Yii::app()->end();
    }

    /**
     * Callback to register Jquery ui bootstrap only for certain actions
     */
    public function _registerJuiBs($event)
    {
        if (in_array($event->params['action']->id, array('create', 'update'))) {
            $this->getData('pageStyles')->mergeWith(array(
                array('src' => Yii::app()->apps->getBaseUrl('assets/css/jui-bs/jquery-ui-1.10.3.custom.css'), 'priority' => -1001),
            ));
        }
    }
}