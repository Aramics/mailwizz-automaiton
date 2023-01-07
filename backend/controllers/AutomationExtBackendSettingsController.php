<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Controller file for automation ext admin settings.
 */
class AutomationExtBackendSettingsController extends Controller
{
    // the extension instance
    public $extension;

    // return the view path
    public function getViewPath()
    {
        return Yii::getPathOfAlias('ext-automation.backend.views.settings');
    }

    /**
     * Common settings
     * Extension page for configuring options used thorugh the extension.
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        $model = new AutomationExtCommon();
        $model->populate();

        if ($request->isPostRequest) {
            $model->attributes = (array)$request->getPost($model->modelName, array());
            if ($model->validate() && $model->save()) {
                $notify->addSuccess(Yii::t('app', 'Your form has been successfully saved!'));
            } else {
                $notify->addError(Yii::t('app', 'Your form has a few errors, please fix them and try again!'));
            }
        }

        $this->setData([
            'pageMetaTitle'   => $this->getData('pageMetaTitle') . ' | ' . $this->extension->t('Automation Settings'),
            'pageHeading'     => $this->extension->t('Automation Settings'),
            'pageBreadcrumbs' => [
                $this->extension->t('Extensions') => $this->createUrl('extensions/index'),
                $this->extension->t('Automation Settings') => $this->createUrl('automation/settings'),
            ]
        ]);

        $this->render('index', compact('model'));
    }
}