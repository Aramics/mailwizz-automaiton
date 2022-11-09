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
if ($viewCollection->renderContent) { ?>

    <div class="box box-primary borderless">
        <div class="box-header">
            <div class="pull-left">
                <h3 class="box-title"><?php echo IconHelper::make('info') .  $pageHeading;?></h3>
            </div>
            <div class="pull-right"></div>
            <div class="clearfix"><!-- --></div>
        </div>
        <div class="box-body">

            <?php if (empty($renderItems)) {
                /**
                 * This widget renders default getting started page for this particular section.
                 * @since 1.3.9.3 MailWizz EMA
                 */
                $this->widget('common.components.web.widgets.StartPagesWidget', array(
                    'collection' => $collection = new CAttributeCollection(array(
                        'controller' => $this,
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
                                <h3><?php echo CHtml::link($stat['total_inbounds']['count'], $stat['total_inbounds']['url']);?></h3>
                                <p><?php echo Yii::t('app','Total Inbound Servers');?></p>
                            </div>
                        </div>
                        <div class="icon">
                            <?php echo IconHelper::make('glyphicon-inbox') ;?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-xs-6">
                    <div class="small-box">
                        <div class="inner">
                            <div class="middle">
                                <h3><?php echo CHtml::link($stat['total_reply']['count'], $stat['total_reply']['url']);?></h3>
                                <p><?php echo Yii::t('app','Total Reply');?></p>
                            </div>
                        </div>
                        <div class="icon">
                            <?php echo IconHelper::make('glyphicon-inbox');?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-xs-12">
                    <div class="small-box">
                        <div class="inner">
                            <div class="middle">
                                <h3><?php echo CHtml::link($stat['total_unique_reply']['count'], $stat['total_unique_reply']['url']);?></h3>
                                <p><?php echo Yii::t('app','Total Unique Reply');?></p>
                            </div>
                        </div>
                        <div class="icon">
                            <?php echo IconHelper::make('glyphicon-inbox');?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    <div class="box box-primary borderless">
        <div class="box-header">
            <div class="pull-left">
                <h3 class="box-title"><?php echo IconHelper::make('info') .  Yii::t('app','Top Campaigns with most response');?></h3>
            </div>
            <div class="clearfix"><!-- --></div>
        </div>
        <div class="box-body">
            <div class="col-lg-12">
                <div class="table-responsive">
                    <table class="table table-border">
                        <thead>
                            <tr>
                                <td> <?php echo Yii::t('app','Campaign');?></td>
                                <td> <?php echo Yii::t('app','Unique Subscribers');?></td>
                                <td> <?php echo Yii::t('app', 'Total Reply');?></td>
                                <td> <?php echo Yii::t('app', 'Total Unique Reply');?></td>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tops['most_replied_campaign'] as $log) { ?>
                                <tr>
                                    <td><?php echo CHtml::link($log['name'],Yii::app()->createUrl("campaigns/overview", array("campaign_uid" => $log['campaign_uid'])));?></td>
                                    <td><?php echo $log['total_subscribers'];?></td>
                                    <td><?php echo $log['total'];?></td>
                                    <td><?php echo $log['total_unique'];?></td>
                                    
                                </tr>
                            <?php }?>
                        </tbody>
                    </table>
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