<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Controller file for settings.
 */

class ReplyTrackerExtBackendSettingsController extends Controller
{
    // the extension instance
    public $extension;

    // move the view path
    public function getViewPath()
    {
        return Yii::getPathOfAlias('ext-reply-tracker.backend.views.settings');
    }

    /**
     * Common settings
     * Extension page for configuring options used thorugh the extension.
     */
    public function actionIndex()
    {
        $request = Yii::app()->request;
        $notify  = Yii::app()->notify;

        $model = new ReplyTrackerExtCommon();
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
            'pageMetaTitle'   => $this->getData('pageMetaTitle') . ' | '. $this->extension->t('Reply Tracker Settings'),
            'pageHeading'     => $this->extension->t('Reply Tracker Settings'),
            'pageBreadcrumbs' => [
                $this->extension->t('Extensions') => $this->createUrl('extensions/index'),
                $this->extension->t('Reply Tracker Settings') => $this->createUrl('reply_tracker/settings'),
            ]
        ]);

        $this->render('index', compact('model'));
    }
}
