<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Controller file for managing tracked replies.
 * More like log/Index controller
 *
 */
 
class ReplyTrackerExtCustomerLogController extends Controller
{
    // the extension instance
    public $extension;
    
    // the init
    public function init()
    {
        $this->getData('pageScripts')->add(array('src' => AssetsUrl::js('email-box-monitors.js')));
        $this->onBeforeAction = array($this, '_registerJuiBs');
        parent::init();
    }
    
    /**
     * @inheritDoc
     */
    public function getViewPath()
    {
        return Yii::getPathOfAlias('ext-reply-tracker.customer.views.log');
    }
    
    /**
     * Define the filters for various controller actions
     * Merge the filters with the ones from parent implementation
     */
    public function filters()
    {
        $filters = array(
            'postOnly + delete, copy, enable, disable',
        );

        return CMap::mergeArray($filters, parent::filters());
    }

    /**
     * List reply tracker logs
     */
    public function actionIndex()
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $log  = new ReplyTrackerExtLogModel('search');
        $log->unsetAttributes();

        $log->attributes = (array)$request->getQuery($log->modelName, array());
        $log->customer_id = (int)$customer->customer_id;
       
        $this->setData([
            'pageMetaTitle'   => $this->getData('pageMetaTitle') . ' | '. $this->extension->t('Reply Tracker'),
            'pageHeading'     => $this->extension->t('Reply Tracker'),
            'pageBreadcrumbs' => [
                $this->extension->t('Extensions') => $this->createUrl('extensions/index'),
                $this->extension->t('Reply Tracker Log') => $this->createUrl('reply_tracker_log'),
            ]
        ]);

        $this->render('list', compact('log'));
    }

    /**
     * Delete a tracked response.
     * This method can be used if an autoresponse is still able to pass through our filters.
     */
    public function actionDelete($id)
    {
        $customer = Yii::app()->customer->getModel();
        $log = ReplyTrackerExtLogModel::model()->findByAttributes(array(
            'log_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));
        if (empty($log)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        $log->delete();

        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        $redirect = null;
        if (!$request->getQuery('ajax')) {
            $notify->addSuccess(Yii::t('app', 'The item has been successfully deleted!'));
            $redirect = $request->getPost('returnUrl', array('reply_tracker/index'));
        }

        // since 1.3.5.9
        Yii::app()->hooks->doAction('controller_action_delete_data', $collection = new CAttributeCollection(array(
            'controller' => $this,
            'model'      => $log,
            'redirect'   => $redirect,
        )));

        if ($collection->redirect) {
            $this->redirect($collection->redirect);
        }
    }

    /**
     * Run a bulk action against the logs
     */
    public function actionBulk_action()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $customer = Yii::app()->customer->getModel();

        $action = $request->getPost('bulk_action');
        $items  = array_unique(array_map('intval', (array)$request->getPost('bulk_item', array())));

        if ($action == ReplyTrackerExtLogModel::BULK_ACTION_DELETE && count($items)) { //delete bulk action
            $affected = 0;
            foreach ($items as $item) {
                $log = ReplyTrackerExtLogModel::model()->findByAttributes(array(
                    'log_id'   => (int)$item,
                    'customer_id' => (int)$customer->customer_id,
                ));
                if (empty($log)) {
                    continue;
                }

                $log->delete();
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
     * Export log to csv
     */
    public function actionExport()
    {
        $notify = Yii::app()->notify;
        $customer = Yii::app()->customer->getModel();

        $models = ReplyTrackerExtLogModel::model()->findAllByAttributes(array(
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

        $attributes = AttributeHelper::removeSpecialAttributes($models[0]->attributes, ['campaign_id','subscriber_id']);
        fputcsv($fp, array_map(array($models[0], 'getAttributeLabel'), array_keys($attributes)), ',', '"');

        foreach ($models as $model) {
            $attributes = AttributeHelper::removeSpecialAttributes($model->attributes, ['campaign_id','subscriber_id']);
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