<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * Controller file for settings.
 */

class ReplyTrackerExtApiController extends Controller
{
    /**
     * @return array
     */
    public function accessRules()
    {
        return [
            // allow all authenticated users on all actions
            ['allow', 'users' => ['@']],
            // deny all rule.
            ['deny'],
        ];
    }

    /**
     * This method ensure json is return even in debug mode. It disable yii log getting returned with json content.
     * 
     * @return
     */
    public function renderJson($data = [], $statusCode = 200, array $headers = [], $callback = null)
    {
        foreach (Yii::app()->log->routes as $route) {

            if ($route instanceof CWebLogRoute) {

                $route->enabled = false;
            }
        }

        parent::renderJson($data, $statusCode, $headers, $callback);
    }

    // the extension instance
    public $extension;

    /**
     * Common settings
     * Extension page for configuring options used thorugh the extension.
     */
    public function actionIndex()
    {

        /** @var Customer $customer */
        $customer = user()->getModel();

        $stat = [];
        //total inbound reply tracker monitors
        $stat['total_inbounds'] = [
            'count' => ReplyTrackerExtInboundModel::model()->CountByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            )),
            'url' => $this->createUrl('reply_tracker/inbounds')
        ];
        //get total reply
        $stat['total_reply'] = [
            'count' => ReplyTrackerExtLogModel::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            )),
            'url' => $this->createUrl('reply_tracker_log/index')
        ];

        //ge unique response from log
        $stat['total_unique_reply'] = [
            'count' => ReplyTrackerExtLogModel::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            ), ['select' => 't.campaign_id,t.message', 'group' => 't.campaign_id,t.message', 'distinct' => true]),
            'url' => $this->createUrl('reply_tracker_log/index')
        ];

        //top replied campaign belonging to user.
        $stat['most_replied_campaign'] = Yii::app()->db->createCommand('SELECT `name`,`campaign_uid`,r.`campaign_id`,count(r.`campaign_id`) AS total, count(DISTINCT r.`campaign_id`) AS total_unique, count(DISTINCT r.`subscriber_id`) AS total_subscribers FROM `{{reply_tracker_log}}` as r JOIN {{campaign}} c on c.`campaign_id` =r.campaign_id WHERE r.`customer_id`=:customer_id GROUP BY `campaign_id` ORDER BY total DESC LIMIT 5')->bindValue('customer_id', $customer->customer_id)->queryAll();

        $this->renderJson(['status' => 'success', 'data' => $stat], 200);
        return;
    }
}