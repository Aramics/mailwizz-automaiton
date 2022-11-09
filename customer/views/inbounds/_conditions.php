<?php defined('MW_PATH') || exit('No direct script access allowed'); ?>
<div class="row">
    <div class="conditions-container">
        <div class="col-lg-12">
            <h5>
                <div class="pull-left">
                    <?php echo Yii::t('servers', 'What do you want us do for any subscriber that reply to this address ?'); ?>
                </div>
                <div class="pull-right">
                    <a href="#page-info-conditions" data-toggle="modal"
                        class="btn btn-primary btn-flat"><?php echo IconHelper::make('info'); ?></a>
                </div>
                <div class="clearfix">
                    <!-- -->
                </div>
            </h5>

            <div class="row">
                <div class="col-lg-12">
                    <?php echo $form->error($server, 'conditions'); ?>
                </div>
            </div>

            <hr />
        </div>

        <?php foreach ($server->actions() as $act => $actDetails) : ?>
        <?php echo CHtml::hiddenField($server->modelName . '[conditions][' . $act . '][type]', $actDetails['type']); ?>
        <?php echo CHtml::hiddenField($server->modelName . '[conditions][' . $act . '][description]', $actDetails['description']); ?>


        <div class="item">
            <hr />
            <div class="col-lg-1">
                <?php echo CHtml::checkBox($server->modelName . '[conditions][' . $act . '][status]', $actDetails['status']); ?>
            </div>
            <div class="col-lg-3">
                <?php echo CHtml::label($actDetails['description'], 'conditions'); ?>
            </div>

            <?php if ($actDetails['type'] == ReplyTrackerExtInboundModel::ACTION_TYPE_ACTION) : ?>
            <div class="col-lg-8">
                <?php echo CHtml::dropDownList($server->modelName . '[conditions][' . $act . '][list_id]', $actDetails['list_id'], Lists::getCustomerListsForDropdown((int)Yii::app()->customer->getId()), ['empty' => '']); ?>
            </div>
            <?php endif ?>

            <?php if ($actDetails['type'] == ReplyTrackerExtInboundModel::ACTION_TYPE_KEY_VALUE) : ?>
            <div class="col-lg-8 key-pairs">

                <?php foreach ((array)$actDetails['keys'] as $index => $key) : ?>
                <div class="row duplicate-row form-group">
                    <div class="col-md-5">
                        <?php echo CHtml::textField($server->modelName . '[conditions][' . $act . '][keys][]', $key, ['placeholder' => 'Field Tag (case-sensitive)', 'id' => false]); ?>
                    </div>
                    <div class="col-md-5">
                        <?php echo CHtml::textField($server->modelName . '[conditions][' . $act . '][values][]', $actDetails['values'][$index] ?? '', ['placeholder' => 'Value', 'id' => false]); ?>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-success" onclick="addFormElements(this)"><i
                                class="fa fa-plus"></i></button>
                        <button type="button" class="btn btn-danger" onclick="removeFormElements(this)"><i
                                class="fa fa-minus"></i></button>
                    </div>
                </div>
                <?php endforeach ?>

            </div>
            <?php endif ?>

            <?php if ($actDetails['type'] == ReplyTrackerExtInboundModel::ACTION_TYPE_TRANSACTION) : ?>

            <div class="col-lg-8">
                <div class="">
                    <div class="form-group">
                        <?php echo CHtml::label(Yii::t('server', 'Subject'), 'subject'); ?> [<a data-toggle="modal"
                            href="#available-tags-modal"><?php echo Yii::t('campaigns', 'Available tags'); ?></a>] [<a
                            href="javascript:;"
                            id="toggle-emoji-list"><?php echo Yii::t('campaigns', 'Toggle emoji list'); ?></a>]
                        <?php echo CHtml::textField($server->modelName . '[conditions][' . $act . '][subject]', $actDetails['subject'], array('id' => 'Campaign_subject')); ?>
                    </div>
                    <div id="emoji-list">
                        <div id="emoji-list-wrapper">
                            <?php foreach (EmojiHelper::getList() as $emoji => $description) { ?>
                            <span title="<?php echo ucwords(strtolower($description)); ?>"><?php echo $emoji; ?></span>
                            <?php } ?>
                        </div>
                        <div class="callout callout-info" style="margin-top:5px; margin-bottom: 0px;">
                            <?php echo Yii::t('campaigns', 'You can click on any emoji to enter it in the subject or scroll for more.'); ?>
                        </div>
                    </div>

                    <div class="html-version">
                        <div class="form-group">
                            <div class="pull-left">
                                <?php echo CHtml::label(Yii::t('server', 'Content'), 'content'); ?> [<a
                                    data-toggle="modal"
                                    href="#available-tags-modal"><?php echo Yii::t('lists', 'Available tags'); ?></a>]

                            </div>
                            <div class="clearfix">
                                <!-- -->
                            </div>

                            <?php echo CHtml::activeTextarea($server, 'conditions[' . $act . '][content]', $server->getHtmlOptions('content', array('rows' => 30, 'id' => 'email_content', 'wysiwyg_editor_options' => ['id' => 'email_content']))); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif ?>

            <div class="clearfix">
                <!-- -->
            </div>
        </div>

        <?php endforeach ?>
    </div>
</div>


<div class="modal fade" id="available-tags-modal" tabindex="-1" role="dialog"
    aria-labelledby="available-tags-modal-label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><?php echo Yii::t('lists', 'Available tags'); ?></h4>
            </div>
            <div class="modal-body" style="max-height: 300px; overflow-y:scroll;">
                <table class="table table-hover">
                    <tr>
                        <td><?php echo Yii::t('lists', 'Tag'); ?></td>
                        <td><?php echo Yii::t('lists', 'Required'); ?></td>
                    </tr>
                    <?php foreach ((new CampaignTemplate())->getAvailableTags() as $tag) { ?>
                    <tr>
                        <td><?php echo html_encode($tag['tag']); ?></td>
                        <td><?php echo $tag['required'] ? strtoupper(t('app', CampaignTemplate::TEXT_YES)) : strtoupper(t('app', CampaignTemplate::TEXT_NO)); ?>
                        </td>
                    </tr>
                    <?php } ?>
                </table>

                <div class="alert alert-info">
                    <?php
                    $text = 'You can use all list subscriber custom field tags i.e [TAGNAME] e.g [EMAIL]';
                    echo Yii::t('servers', StringHelper::normalizeTranslationString($text));
                    ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-flat"
                    data-dismiss="modal"><?php echo Yii::t('app', 'Close'); ?></button>
            </div>
        </div>
    </div>
</div>
<!-- modals -->
<div class="modal modal-info fade" id="page-info-conditions" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title"><?php echo IconHelper::make('info') . Yii::t('app',  'Info'); ?></h4>
            </div>
            <div class="modal-body">
                <?php
                $text = 'We you use this inbound address as reply_to address for any campaign, We track responses to your campaign and act accordingly. This can be helpful in extracting active list subscriber  or interesting ones. The above actions will be applied to the subscriber found in replied email, the given action will be taken against the subscriber.<br />Conditions are applied in the order they appear and we execute all action. i.e copy subscriber (that replied) to MyActiveMailList';
                echo Yii::t('servers', StringHelper::normalizeTranslationString($text));
                ?>
            </div>
        </div>
    </div>
</div>


<script>
function addFormElements(current) {
    let clone = $(current).parents('.duplicate-row').clone();
    $(current).parents('.key-pairs').append(clone)
    let inputs = $('.duplicate-row:last-child input');
    inputs.val('');
    $(inputs[0]).focus();
}

function removeFormElements(current) {

    if ($('.duplicate-row').length < 2) {
        $('.duplicate-row input').val('');
        return;
    }
    $(current).parents('.duplicate-row').remove();
}
</script>