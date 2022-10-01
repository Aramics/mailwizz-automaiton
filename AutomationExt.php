<?php defined('MW_PATH') || exit('No direct script access allowed');

class CampaignScheduleExt extends ExtensionInit
{
    // name of the extension as shown in the backend panel
    public $name = 'Automation';

    // description of the extension as shown in backend panel
    public $description = 'Automatically or conditionally perform actions like send email campaigns to your list or individuals in response to several types of events.';

    // current version of this extension
    public $version = '0.0.1';

    // the author name
    public $author = 'TurnSaas';

    // author website
    public $website = 'http://www.turnsaas.com/';

    // contact email address
    public $email = 'mailwizz@turnsaas.com';

    // in which apps this extension is allowed to run
    public $allowedApps = array('customer', 'console', 'api');

    // cli enabled
    // since cli is a special case, we need to explicitly enable it
    // do it only if you need to hook inside console hooks
    public $cliEnabled = true;

    // can this extension be deleted? this only applies to core extensions.
    protected $_canBeDeleted = true;

    // can this extension be disabled? this only applies to core extensions.
    protected $_canBeDisabled = true;

    protected $debug = false;

    // run the extension, ths is mandatory
    public function run()
    {
        Yii::import('ext-automation.common.models.*');

        /**
         * Now we can continue only if the extension is enabled from its settings:
         */
        if ($this->getOption('enabled', 'no') != 'yes') {
            return;
        }

        //load common route for both api and customer
        if ($this->isAppName('api') || $this->isAppName('customer')) {
            $this->appRoutes();
        }

        if ($this->isAppName('customer')) {

            // handle all customer related tasks
            $this->customerApp();
        }

        if ($this->isAppName('console')) {

            // handle all command related tasks i.e php apps/console/console.php track-reply >/dev/null 2 >&1
            $this->consoleApp();
        }
    }

    /**
     * @inheritDoc
     */
    public function getViewPath($appName, $view)
    {
        return $this->getPathAlias("$appName.views.$view");
    }


    /**
     * Handle route for api access
     * @TODO: extend in future for more api endpoints
     */
    protected function appRoutes()
    {

        /**
         * Add the url rules.
         * Best is to follow the pattern below for your extension to avoid name clashes.
         * ext_example_settings is actually the controller file defined in controllers folder.
         */
        Yii::app()->urlManager->addRules(array(
            array('campaign_schedule/index', 'pattern'    => 'extensions/campaign-schedule/index'),
            array('campaign_schedule/', 'pattern' => 'extensions/campaign-schedule/*'),
        ));

        /**
         * And now we register the controller for the above rules.
         *
         */
        Yii::app()->controllerMap['campaign_schedule'] = array(
            'class'     => 'ext-campaign-schedule.common.controllers.CampaignScheduleController',

            // pass the extension instance as a variable to the controller
            'extension' => $this,
        );
    }


    // handle all customer related tasks
    protected function customerApp()
    {

        // let's add settings ui:
        Yii::app()->hooks->addAction('campaign_form_setup_step_after_campaign_options', function ($params) {
            $this->renderSettingsUI($params);
        });

        Yii::app()->hooks->addAction('controller_action_save_data', function ($collection) {
            CampaignScheduleHelper::saveCampaignSchedules($collection);
        });

        Yii::app()->hooks->addAction('customer_campaigns_overview_after_tracking_stats', function ($collection) {
            $controller = $collection->itemAt("controller");
            $this->renderStatisticUI($controller);
        });
    }


    // handle all command related tasks
    public function consoleApp()
    {

        //enable below lines for debug mode.
        if ($this->debug) {
            Yii::app()->hooks->addFilter('console_command_send_campaigns_stdout_message', function ($message) {
                var_dump($message);
                return $message;
            });
        }


        Yii::app()->hooks->addFilter('console_send_campaigns_command_find_campaigns_criteria', function ($criteria) {
            return CampaignScheduleHelper::filterCampaignsToSendCriteria($criteria);
        });


        Yii:
        app()->hooks->addAction('console_command_send_campaigns_send_campaign_step1_start', function ($campaign) {
            return CampaignScheduleHelper::canSendCampaign($campaign);
        });


        Yii::app()->hooks->addAction('console_send_campaigns_command_process_subscribers_loop_in_loop_start', function ($collection) {
            return CampaignScheduleHelper::canSendCampaignSubscriber($collection);
        });


        Yii::app()->hooks->addAction('console_command_send_campaigns_after_send_to_subscriber', function ($campaign, $subscriber, $customer, $server, $sent) {
            return CampaignScheduleHelper::afterSendCampaign($campaign, $subscriber, $customer, $server, $sent);
        });
    }


    /**
     * Create databaste structure
     *
     * @return     mixed
     */
    public function beforeEnable()
    {
        //campaign schedule schema
        Yii::app()->getDb()->createCommand("
        CREATE TABLE IF NOT EXISTS `{{campaign_schedule}}` (
         `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
         `customer_id` int(11) DEFAULT NULL,
         `campaign_id` int(11) DEFAULT NULL,
         `total_emails_to_send` int(11) NOT NULL DEFAULT '0',
         `between_email_delay` int(11) NOT NULL DEFAULT '0',
         `day_of_week` enum('0','1','2','3','4','5','6') NOT NULL DEFAULT '1',
         `start_at` varchar(255) NOT NULL,
         `stop_at` varchar(255) NOT NULL,
         `meta_data` longtext default NULL,
         `date_added` datetime NOT NULL,
         `last_updated` datetime NOT NULL,
         PRIMARY KEY (`schedule_id`),
         KEY `fk_{{campaign_schedule}}_customer1_idx` (`customer_id`),
         KEY `fk_{{campaign_schedule}}_campaign1_idx` (`campaign_id`),
         CONSTRAINT `fk_{{campaign_schedule}}_customer1` FOREIGN KEY (`customer_id`) REFERENCES `{{customer}}` (`customer_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
         CONSTRAINT `fk_{{campaign_schedule}}_campaign1` FOREIGN KEY (`campaign_id`) REFERENCES `{{campaign}}` (`campaign_id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ")->execute();

        // call parent
        return parent::afterEnable();
    }


    /**
     * Drop the table when extension is removed.
     *
     * @return
     */
    public function beforeDelete()
    {
        Yii::app()->getDb()->createCommand('DROP TABLE IF EXISTS `{{campaign_schedule}}`')->execute();

        // call parent
        return parent::beforeDelete();
    }

    /**
     * Render the schedule adding ui in campaign setup
     */
    public function renderSettingsUI($params)
    {
        $controller = $params['controller'];
        $controller->renderPartial($this->getViewPath('customer', 'schedule'), compact('params'));
    }

    /**
     * Render the schedule static ui in campaign overview
     */
    public function renderStatisticUI($controller)
    {
        $campaign = $controller->getData('campaign');
        $controller->renderPartial($this->getViewPath('customer', 'statistic'), compact('campaign'));
    }
}