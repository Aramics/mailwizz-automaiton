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

        //get total automations
        $stat['total_automation'] = [
            'count' => AutomationExtModel::model()->countByAttributes(array(
                'customer_id' => (int)$customer->customer_id,
            )),
            'url' => $this->createUrl('automation/index')
        ];

        $this->renderJson(['status' => 'success', 'data' => $stat], 200);
        return;
    }
}