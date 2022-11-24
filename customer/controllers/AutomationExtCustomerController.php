<?php defined('MW_PATH') || exit('No direct script access allowed');

/** 
 * Controller file for managing automations.
 * 
 */

class AutomationExtCustomerController extends Controller
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
        return Yii::getPathOfAlias('ext-automation.customer.views');
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
    public function actionUpdate($id)
    {
        $customer = Yii::app()->customer->getModel();
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;
        $automation = AutomationExtModel::model()->findByAttributes(array(
            'automation_id'   => (int)$id,
            'customer_id' => (int)$customer->customer_id,
        ));

        if (empty($automation)) {
            throw new CHttpException(404, Yii::t('app', 'The requested page does not exist.'));
        }

        if (!$automation->getCanBeUpdated()) {
            $this->redirect(array('automations/index'));
        }


        if ($automation->getIsLocked()) {
            $notify->addWarning($this->extension->t('This automation is locked, you cannot change or delete it!'));
            $this->redirect(array('automations/index'));
        }

        if ($request->isPostRequest && ($attributes = (array)$request->getPost($automation->modelName, array()))) {

            $automation->attributes = $attributes;
            $automation->customer_id = $customer->customer_id;

            if (!$automation->save()) {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            } else {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            }

            Yii::app()->hooks->doAction('controller_action_save_data', $collection = new CAttributeCollection(array(
                'controller' => $this,
                'success'    => $notify->hasSuccess,
                'automation'     => $automation,
            )));

            if ($collection->success) {
            }
        }


        $this->setData(array(
            'pageMetaTitle'   => $this->data->pageMetaTitle . ' | ' . $this->extension->t('Update automation'),
            'pageHeading'     => $this->extension->t('Update automation'),
            'pageBreadcrumbs' => array(
                $this->extension->t('Automation') => $this->createUrl('automations/index'),
                Yii::t('app', 'Update'),
            )
        ));

        $view = $this->getViewFile('canvas');
        $this->renderFile($view, compact('automation'));
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