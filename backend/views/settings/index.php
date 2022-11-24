<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This hook gives a chance to prepend content or to replace the default view content with a custom content.
 * Please note that from inside the action callback you can access all the controller view
 * variables via {@CAttributeCollection $collection->controller->data}
 * In case the content is replaced, make sure to set {@CAttributeCollection $collection->renderContent} to false
 * in order to stop rendering the default content.
 * @since 1.3.3.1 MailWizz EMA
 */
$hooks->doAction('before_view_file_content', $viewCollection = new CAttributeCollection(array(
    'controller'    => $this,
    'renderContent' => true,
)));

// and render if allowed
if ($viewCollection->renderContent) {
    /**
     * This hook gives a chance to prepend content before the active form or to replace the default active form entirely.
     * Please note that from inside the action callback you can access all the controller view variables
     * via {@CAttributeCollection $collection->controller->data}
     * In case the form is replaced, make sure to set {@CAttributeCollection $collection->renderForm} to false
     * in order to stop rendering the default content.
     * @since 1.3.3.1 MailWizz EMA
     */
    $hooks->doAction('before_active_form', $collection = new CAttributeCollection(array(
        'controller'    => $this,
        'renderForm'    => true,
    )));

    // and render if allowed
    if ($collection->renderForm) {
        $form = $this->beginWidget('CActiveForm'); ?>
<div class="box box-primary">
    <div class="box-header">
        <h3 class="box-title">
            <?php echo IconHelper::make('fa-cog') . Yii::t('settings', 'Settings for processing automation') ?></h3>
    </div>
    <div class="box-body">

        <?php
                /**
                 * This hook gives a chance to prepend content before the active form fields.
                 * Please note that from inside the action callback you can access all the controller view variables
                 * via {@CAttributeCollection $collection->controller->data}
                 * @since 1.3.3.1 MailWizz EMA
                 */
                $hooks->doAction('before_active_form_fields', new CAttributeCollection(array(
                    'controller' => $this,
                    'form'       => $form
                ))); ?>
        <div class="row">
            <div class="form-group col-lg-12">
                <?php echo $form->labelEx($model, 'enabled'); ?>
                <?php echo $form->dropDownList($model, 'enabled', $model->getYesNoOptions(), $model->getHtmlOptions('enabled')); ?>
                <?php echo $form->error($model, 'enabled'); ?>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <?php echo $form->labelEx($model, 'customer_groups'); ?>
                    <?php echo $form->dropDownList($model, 'customer_groups', CMap::mergeArray(['' => ''], $model->getCustomerGroupsList()), $model->fieldDecorator->getHtmlOptions('customer_groups', ['multiple' => true])); ?>
                    <?php echo $form->error($model, 'customer_groups'); ?>
                </div>
            </div>
        </div>
        <hr />
        <div class="row">
            <div class="col-lg-6">
                <div class="form-group">
                    <?php echo CHtml::link(IconHelper::make('info'), '#page-info-pcntl-monitor', array('class' => 'btn btn-primary btn-xs btn-flat', 'title' => Yii::t('app', 'Info'), 'data-toggle' => 'modal')); ?>
                    <?php echo $form->labelEx($model, 'use_pcntl'); ?>
                    <?php echo $form->dropDownList($model, 'use_pcntl', $model->getYesNoOptions(), $model->getHtmlOptions('use_pcntl')); ?>
                    <?php echo $form->error($model, 'use_pcntl'); ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="form-group">
                    <?php echo $form->labelEx($model, 'pcntl_processes'); ?>
                    <?php echo $form->numberField($model, 'pcntl_processes', $model->getHtmlOptions('pcntl_processes')); ?>
                    <?php echo $form->error($model, 'pcntl_processes'); ?>
                </div>
            </div>
        </div>
        <!-- modals -->
        <div class="modal modal-info fade" id="page-info-pcntl-monitor" tabindex="-1" role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title"><?php echo IconHelper::make('info') . Yii::t('app', 'Info'); ?></h4>
                    </div>
                    <div class="modal-body">
                        <?php echo Yii::t('settings', 'You can use below settings to increase processing speed. Please be aware that wrong changes might have undesired results.'); ?>
                        <br />
                        <strong><?php echo Yii::t('settings', 'Also note that below will apply only if you have installed and enabled PHP\'s PCNTL extension on your server. If you are not sure if your server has the extension, ask your hosting.'); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php
                /**
                 * This hook gives a chance to append content after the active form fields.
                 * Please note that from inside the action callback you can access all the controller view variables
                 * via {@CAttributeCollection $collection->controller->data}
                 * @since 1.3.3.1
                 */
                $hooks->doAction('after_active_form_fields', new CAttributeCollection(array(
                    'controller'        => $this,
                    'form'              => $form
                ))); ?>
        <div class="clearfix">
            <!-- -->
        </div>
    </div>

    <div class="box-footer">
        <div class="pull-right">
            <button type="submit" class="btn btn-primary btn-submit"
                data-loading-text="<?php echo Yii::t('app', 'Please wait, processing...'); ?>"><?php echo Yii::t('app', 'Save changes'); ?></button>
        </div>
        <div class="clearfix">
            <!-- -->
        </div>
        <hr />
        <div class="alert alert-info">
            <?php
                    $text = 'Please note that you have to add the following cron job in order for this feature to work:<br />Every minute - adjust to suite your needs i.e every minute or every hour. <br/>{cron}';
                    echo Yii::t('servers', StringHelper::normalizeTranslationString($text), array(
                        '{cron}' => '<b>* * * * * ' . CommonHelper::findPhpCliPath() . ' -q ' . MW_ROOT_PATH . '/apps/console/console.php run-automation >/dev/null 2>&1</b>',
                    )); ?>
        </div>
    </div>
</div>
<?php
        $this->endWidget();
    }
    /**
     * This hook gives a chance to append content after the active form.
     * Please note that from inside the action callback you can access all the controller view variables
     * via {@CAttributeCollection $collection->controller->data}
     * @since 1.3.3.1 MailWizz EMA
     */
    $hooks->doAction('after_active_form', new CAttributeCollection(array(
        'controller'      => $this,
        'renderedForm'    => $collection->renderForm,
    )));
}
/**
 * This hook gives a chance to append content after the view file default content.
 * Please note that from inside the action callback you can access all the controller view
 * variables via {@CAttributeCollection $collection->controller->data}
 * @since 1.3.3.1 MailWizz EMA
 */
$hooks->doAction('after_view_file_content', new CAttributeCollection(array(
    'controller'        => $this,
    'renderedContent'   => $viewCollection->renderContent,
)));