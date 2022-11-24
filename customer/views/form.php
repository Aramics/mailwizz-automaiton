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
        $form = $this->beginWidget('CActiveForm', ['id' => 'automation-form']);
?>
<div class="box box-primary borderless">
    <div class="box-header">
        <div class="pull-left">
            <?php BoxHeaderContent::make(BoxHeaderContent::LEFT)
                        ->add('<h3 class="box-title">' . IconHelper::make('glyphicon-transfer') . $pageHeading . '</h3>')
                        ->render();
                    ?>
        </div>
        <div class="pull-right">
            <?php BoxHeaderContent::make(BoxHeaderContent::RIGHT)
                        ->addIf(HtmlHelper::accessLink(IconHelper::make('create') . Yii::t('app', 'Create new'), array('automations/create'), array('class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Create new'))), !$automation->isNewRecord)
                        ->add(HtmlHelper::accessLink(IconHelper::make('cancel') . Yii::t('app', 'Cancel'), array('automations/index'), array('class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Cancel'))))
                        ->render();
                    ?>
        </div>
        <div class="clearfix">
            <!-- -->
        </div>
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
                    'controller'    => $this,
                    'form'          => $form
                )));
                ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <?php echo $form->labelEx($automation, 'title'); ?>
                    <?php echo $form->textField($automation, 'title', $automation->getHtmlOptions('title')); ?>
                    <?php echo $form->error($automation, 'title'); ?>
                </div>
            </div>

        </div>
        <?php
                /**
                 * This hook gives a chance to append content after the active form fields.
                 * Please note that from inside the action callback you can access all the controller view variables 
                 * via {@CAttributeCollection $collection->controller->data}
                 * @since 1.3.3.1 MailWizz EMA
                 */
                $hooks->doAction('after_active_form_fields', new CAttributeCollection(array(
                    'controller'    => $this,
                    'form'          => $form
                )));
                ?>
    </div>
    <div class="box-footer">
        <div class="pull-right">
            <button type="submit"
                class="btn btn-primary btn-flat"><?php echo IconHelper::make('save') . Yii::t('app', 'Save changes'); ?></button>
        </div>
        <div class="clearfix">
            <!-- -->
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
    ?>
<?php
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