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
    $itemsCount = ReplyTrackerExtLogModel::model()->count(); ?>
<div class="box box-primary borderless">
    <div class="box-header">
        <div class="pull-left">
            <?php BoxHeaderContent::make(BoxHeaderContent::LEFT)
                    ->add('<h3 class="box-title">' . IconHelper::make('glyphicon-inbox') . $pageHeading . '</h3>')
                    ->render(); ?>
        </div>
        <div class="pull-right">
            <?php BoxHeaderContent::make(BoxHeaderContent::RIGHT)
                    ->addIf($this->widget('common.components.web.widgets.GridViewToggleColumns', array('model' => $log, 'columns' => array('campaign_id', 'subscriber_id', 'from_name', 'from_email', 'message', 'reply_date')), true), $itemsCount)
                    ->addIf(CHtml::link(IconHelper::make('export') . Yii::t('app', 'Export'), array('reply_tracker_log/export'), array('target' => '_blank', 'class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Export'))), $itemsCount)
                    ->add(HtmlHelper::accessLink(IconHelper::make('refresh') . Yii::t('app', 'Refresh'), array('reply_tracker_log/index'), array('class' => 'btn btn-primary btn-flat', 'title' => Yii::t('app', 'Refresh'))))
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
        if (AccessHelper::hasRouteAccess('reply_tracker_log/bulk_action')) {
            $this->widget('common.components.web.widgets.GridViewBulkAction', array(
                        'model'      => $log,
                        'formAction' => $this->createUrl('reply_tracker_log/bulk_action'),
                    ));
        }
        $this->widget('zii.widgets.grid.CGridView', $hooks->applyFilters('grid_view_properties', array(
                    'ajaxUrl'           => $this->createUrl($this->route),
                    'id'                => $log->modelName.'-grid',
                    'dataProvider'      => $log->search(),
                    'filter'            => $log,
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
                            'name'                => 'log_id',
                            'selectableRows'      => 100,
                            'checkBoxHtmlOptions' => array('name' => 'bulk_item[]'),
                            'visible'             => AccessHelper::hasRouteAccess('reply_tracker_log/bulk_action'),
                        ),
                        array(
                            'name'  => 'campaign_id',
                            'value' => 'CHtml::link($data->campaign->name, Yii::app()->createUrl("campaigns/overview", array("campaign_uid" => $data->campaign->campaign_uid)))',
                            'type' => 'raw',
                            'filter' => $log->getCustomerCampaigns()
                        ),
                        array(
                            'name'  => 'subscriber_id',
                            'value' => '!empty($data->subscriber) ? CHtml::link($data->subscriber->getFullName(), Yii::app()->createUrl("list_subscribers/update", array("list_uid" => $data->campaign->list->list_uid, "subscriber_uid" => $data->subscriber->subscriber_uid))) : $data->from_name',
                            'type' => 'raw',
                            'filter'=> CHtml::activeTextField($log, 'subscriber_id'),
                        ),
                        array(
                            'name'  => 'from_name',
                            'value' => '$data->purify($data->from_name)'
                        ),
                        array(
                            'name'  => 'from_email',
                            'value' => 'customer()->getModel()->getGroupOption("common.mask_email_addresses", "no") == "yes" ? StringHelper::maskEmailAddress(CHtml::encode($data->purify($data->from_email))) : CHtml::encode($data->purify($data->from_email))',
                        ),
                        array(
                            'name'  => 'message',
                            'value' => '$data->purify($data->message)', //purified before saved to database
                            'type' => 'raw',
                        ),
                         array(
                            'name'  => 'reply_date',
                            'value' => '$data->reply_date',
                        ),
                        
                        array(
                            'class'     => 'CButtonColumn',
                            'header'    => Yii::t('app', 'Options'),
                            'footer'    => $log->paginationOptions->getGridFooterPagination(),
                            'buttons'   => array(
                                'delete' => array(
                                    'label'     => IconHelper::make('delete'),
                                    'url'       => 'Yii::app()->createUrl("reply_tracker_log/delete", array("id" => $data->log_id))',
                                    'imageUrl'  => null,
                                    'options'   => array('title' => Yii::t('app', 'Delete'), 'class' => 'btn btn-danger btn-flat delete'),
                                    'visible'   => 'AccessHelper::hasRouteAccess("reply_tracker_log/delete")',
                                ),
                            ),
                            'headerHtmlOptions' => array('style' => 'text-align: right'),
                            'footerHtmlOptions' => array('align' => 'right'),
                            'htmlOptions'       => array('align' => 'right', 'class' => 'options'),
                            'template'          => '{delete}'
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
                'renderedGrid'=> $collection->renderGrid,
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
                    $text = 'Here you see the responses to your campaigns. Use campaign filter to select for a particular campaign<br />
                    The message is the subscriber responds.<br/>
                    <br/>If you cant see any reply, make sure your register inbound server is used as "Reply to" in at least one campaign.<br /><br />
                    ';
    echo Yii::t('servers', StringHelper::normalizeTranslationString($text)); ?>
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