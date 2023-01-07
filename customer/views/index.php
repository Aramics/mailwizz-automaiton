<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This file is part of the MailWizz EMA application.
 *
 * @package MailWizz EMA
 * @author Serban George Cristian <cristian.serban@mailwizz.com>
 * @link https://www.mailwizz.com/
 * @copyright MailWizz EMA (https://www.mailwizz.com)
 * @license https://www.mailwizz.com/license/
 * @since 1.4.5
 */

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
     * @since 1.3.9.2 MailWizz EMA
     */
    $itemsCount = (int)AutomationExtModel::model()->countByAttributes([
        'customer_id' => (int)customer()->getId(),
        'status'      => array_keys($automation->getStatusesList()),
    ]); ?>

<div class="box box-primary borderless">
    <div class="box-header">
        <div class="pull-left">
            <h3 class="box-title"><?php echo IconHelper::make('info') .  Yii::t('app', 'Dashboard'); ?></h3>
        </div>
        <div class="pull-right"></div>
        <div class="clearfix">
            <!-- -->
        </div>
    </div>
    <div class="box-body">

        <?php if (empty($renderItems)) {
                /**
                 * This widget renders default getting started page for this particular section.
                 * @since 1.3.9.3 MailWizz EMA
                 */
                $this->widget('common.components.web.widgets.StartPagesWidget', array(
                    'collection' => $collection = new CAttributeCollection(array(
                        'controller' => $controller,
                        'renderGrid' => true,
                    )),
                    'enabled' => true,
                ));
            } ?>

        <div class="row boxes-mw-wrapper">
            <div class="col-lg-4 col-xs-6">
                <div class="small-box">
                    <div class="inner">
                        <div class="middle">
                            <h3><?php echo CHtml::link($stat['total_automations']['count'], $stat['total_automations']['url']); ?>
                            </h3>
                            <p><?php echo $controller->extension->t('Total Automations'); ?></p>
                        </div>
                    </div>
                    <div class="icon">
                        <?php echo IconHelper::make('glyphicon-inbox'); ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-xs-6">
                <div class="small-box">
                    <div class="inner">
                        <div class="middle">
                            <h3><?php echo CHtml::link($stat['total_active']['count'], $stat['total_active']['url']); ?>
                            </h3>
                            <p><?php echo $controller->extension->t('Total active'); ?></p>
                        </div>
                    </div>
                    <div class="icon">
                        <?php echo IconHelper::make('glyphicon-inbox'); ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-xs-12">
                <div class="small-box">
                    <div class="inner">
                        <div class="middle">
                            <h3><?php echo CHtml::link($stat['total_inactive']['count'], $stat['total_inactive']['url']); ?>
                            </h3>
                            <p><?php echo $controller->extension->t('Total draft'); ?></p>
                        </div>
                    </div>
                    <div class="icon">
                        <?php echo IconHelper::make('glyphicon-inbox'); ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="box box-primary borderless">
    <div class="box-header">
        <div class="pull-left">
            <?php BoxHeaderContent::make(BoxHeaderContent::LEFT)
                    ->add('<h3 class="box-title">' . IconHelper::make('glyphicon-inbox') . $pageHeading . '</h3>')
                    ->render(); ?>
        </div>
        <div class="pull-right">
            <?php BoxHeaderContent::make(BoxHeaderContent::RIGHT)
                    ->addIf($this->widget('common.components.web.widgets.GridViewToggleColumns', array('model' => $automation, 'columns' => array('automation_id', 'title', 'trigger', 'status', 'date_added')), true), $itemsCount)
                    ->add(CHtml::link(IconHelper::make('create') . Yii::t('app', 'Create new'), ['automations/create'], ['class' => 'btn btn-primary btn-flat', 'title' => t('app', 'Create new')]))
                    ->addIf(CHtml::link(IconHelper::make('export') . Yii::t('app', 'Export'), array('automations/export'), array('target' => '_blank', 'class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Export'))), $itemsCount)
                    ->add(HtmlHelper::accessLink(IconHelper::make('refresh') . Yii::t('app', 'Refresh'), array('automations/index'), array('class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Refresh'))))
                    ->add(CHtml::link(IconHelper::make('info'), '#page-info', array('class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Info'), 'data-toggle' => 'modal')))
                    ->render(); ?>
        </div>
        <div class="clearfix">
            <!-- -->
        </div>
    </div>
    <div class="box-body">
        <div class="table-responsive">
            <?php
                /**
                 * This hook gives a chance to prepend content or to replace the default grid view content with a custom content.
                 * Please note that from inside the action callback you can access all the controller view
                 * variables via {@CAttributeCollection $collection->controller->data}
                 * In case the content is replaced, make sure to set {@CAttributeCollection $collection->renderGrid} to false
                 * in order to stop rendering the default content.
                 * @since 1.3.3.1 MailWizz EMA
                 */
                $hooks->doAction('before_grid_view', $collection = new CAttributeCollection(array(
                    'controller'  => $this,
                    'renderGrid'  => true,
                )));

                /**
                 * This widget renders default getting started page for this particular section.
                 * @since 1.3.9.2 MailWizz EMA
                 */
                $this->widget('common.components.web.widgets.StartPagesWidget', array(
                    'collection' => $collection,
                    'enabled'    => !$itemsCount,
                ));

                // and render if allowed
                if ($collection->renderGrid) {
                    // since 1.3.5.4 MailWizz EMA
                    if (AccessHelper::hasRouteAccess('automations/bulk_action')) {
                        $this->widget('common.components.web.widgets.GridViewBulkAction', array(
                            'model'      => $automation,
                            'formAction' => $this->createUrl('automations/bulk_action'),
                        ));
                    }
                    $this->widget('zii.widgets.grid.CGridView', $hooks->applyFilters('grid_view_properties', array(
                        'ajaxUrl'           => $this->createUrl($this->route),
                        'id'                => $automation->modelName . '-grid',
                        'dataProvider'      => $automation->search(),
                        'filter'            => $automation,
                        'filterPosition'    => 'body',
                        'filterCssClass'    => 'grid-filter-cell',
                        'itemsCssClass'     => 'table table-hover',
                        'selectableRows'    => 0,
                        'enableSorting'     => false,
                        'cssFile'           => false,
                        'pagerCssClass'     => 'pagination pull-right',
                        'pager'             => array(
                            'class'         => 'CLinkPager',
                            'cssFile'       => false,
                            'header'        => false,
                            'htmlOptions'   => array('class' => 'pagination')
                        ),
                        'columns' => $hooks->applyFilters('grid_view_columns', array(
                            array(
                                'class'               => 'CCheckBoxColumn',
                                'name'                => 'automation_id',
                                'selectableRows'      => 100,
                                'checkBoxHtmlOptions' => array('name' => 'bulk_item[]'),
                                'visible'             => AccessHelper::hasRouteAccess('automations/bulk_action'),
                            ),
                            array(
                                'name'  => 'title',
                                'value' => '$data->title'
                            ),
                            array(
                                'name'  => 'trigger',
                                'value' => '$data->trigger',
                            ),
                            array(
                                'name'  => 'status',
                                'value' => 'ucfirst(Yii::t("app", $data->status))',
                                'filter' => $automation->getStatusesList(),
                            ),
                            array(
                                'name'  => 'date_added',
                                'value' => '$data->date_added',
                                'filter' => false,
                            ),
                            array(
                                'class'     => 'CButtonColumn',
                                'header'    => Yii::t('app', 'Options'),
                                'footer'    => $automation->paginationOptions->getGridFooterPagination(),
                                'buttons'   => array(
                                    'update' => array(
                                        'label'     => IconHelper::make('update'),
                                        'url'       => 'Yii::app()->createUrl("automations/update", array("id" => $data->automation_id))',
                                        'imageUrl'  => null,
                                        'options'   => array('title' => Yii::t('app', 'Update'), 'class' => 'btn btn-primary btn-flat'),
                                        'visible'   => 'AccessHelper::hasRouteAccess("automations/update") && $data->getCanBeUpdated()',
                                    ),
                                    'copy' => array(
                                        'label'     => IconHelper::make('copy'),
                                        'url'       => 'Yii::app()->createUrl("automations/copy", array("id" => $data->automation_id))',
                                        'imageUrl'  => null,
                                        'options'   => array('title' => Yii::t('app', 'Copy'), 'class' => 'btn btn-primary btn-flat copy-automation'),
                                        'visible'   => 'AccessHelper::hasRouteAccess("automations/copy")',
                                    ),
                                    'enable' => array(
                                        'label'     => IconHelper::make('glyphicon-open'),
                                        'url'       => 'Yii::app()->createUrl("automations/enable", array("id" => $data->automation_id))',
                                        'imageUrl'  => null,
                                        'options'   => array('title' => Yii::t('app', 'Activate'), 'class' => 'btn btn-primary btn-flat enable-automation'),
                                        'visible'   => 'AccessHelper::hasRouteAccess("automations/enable") && ($data->getIsDisabled() || $data->getIsDraft())',
                                    ),
                                    'disable' => array(
                                        'label'     => IconHelper::make('glyphicon-save'),
                                        'url'       => 'Yii::app()->createUrl("automations/disable", array("id" => $data->automation_id))',
                                        'imageUrl'  => null,
                                        'options'   => array('title' => Yii::t('app', 'Disable'), 'class' => 'btn btn-primary btn-flat disable-automation'),
                                        'visible'   => 'AccessHelper::hasRouteAccess("automations/disable") && $data->getIsActive()',
                                    ),
                                    'delete' => array(
                                        'label'     => IconHelper::make('delete'),
                                        'url'       => 'Yii::app()->createUrl("automations/delete", array("id" => $data->automation_id))',
                                        'imageUrl'  => null,
                                        'options'   => array('title' => Yii::t('app', 'Delete'), 'class' => 'btn btn-danger btn-flat delete'),
                                        'visible'   => 'AccessHelper::hasRouteAccess("automations/delete") && $data->getCanBeDeleted()',
                                    ),
                                ),
                                'headerHtmlOptions' => array('style' => 'text-align: right'),
                                'footerHtmlOptions' => array('align' => 'right'),
                                'htmlOptions'       => array('align' => 'right', 'class' => 'options'),
                                'template'          => '{update} {copy} {enable} {disable} {delete}'
                            ),
                        ), $this),
                    ), $this));
                }
                /**
                 * This hook gives a chance to append content after the grid view content.
                 * Please note that from inside the action callback you can access all the controller view
                 * variables via {@CAttributeCollection $collection->controller->data}
                 * @since 1.3.3.1 MailWizz EMA
                 */
                $hooks->doAction('after_grid_view', new CAttributeCollection(array(
                    'controller'  => $this,
                    'renderedGrid' => $collection->renderGrid,
                ))); ?>
            <div class="clearfix">
                <!-- -->
            </div>
        </div>
    </div>
</div>
<!-- modals -->
<div class="modal modal-info fade" id="page-info" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><?php echo IconHelper::make('info') . Yii::t('app', 'Info'); ?></h4>
            </div>
            <div class="modal-body">
                <?php
                    $text = 'Here you see the list of your automations.<br/>
                    <br/>Click on each automation to see statistic.<br /><br />
                    ';
                    echo $controller->extension->t(StringHelper::normalizeTranslationString($text)); ?>
            </div>
        </div>
    </div>
</div>
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