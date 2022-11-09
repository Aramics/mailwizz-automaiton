<?php defined('MW_PATH') || exit('No direct script access allowed');

class CampaignScheduleExt extends ExtensionInit
{
    // name of the extension as shown in the backend panel
    public $name = 'Automation';

    // description of the extension as shown in backend panel
    public $description = 'Mailwizz Email and Marketing Automation with flow builder. Automatically or conditionally perform actions like send email campaigns to your list or individuals in response to several types of events.';

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
        Yii::import('ext-reply-tracker.common.models.*');

        $this->commonHooks();

        if ($this->isAppName('backend')) {

            // handle all backend related tasks
            $this->backendApp();
        }

        /**
         * Now we can continue only if the extension is enabled from its settings:
         */
        if ($this->getOption('enabled', 'no') != 'yes') {
            return;
        }

        if ($this->isAppName('api')) {

            // handle all api related tasks
            $this->apiApp();
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

    //common hooks 
    protected function commonHooks()
    {
        $hooks = Yii::app()->hooks;
    }

    // handle all api related tasks
    protected function apiApp()
    {
        /**
         * Add the url rules.
         * Best is to follow the pattern below for your extension to avoid name clashes.
         * ext_example_settings is actually the controller file defined in controllers folder.
         */
        Yii::app()->urlManager->addRules(array(
            array('automation/<action>', 'pattern'    => 'automation/*'),
        ));

        /**
         * And now we register the controller for the above rules.
         *
         * Please note that you can register controllers and urls rules
         * in any of the apps.
         *
         * Remember how we said that ext_example_settings is actually the controller file:
         */
        Yii::app()->controllerMap['automation'] = array(
            // remember the ext-example path alias?
            'class'     => 'ext-automation.api.controllers.AutomationExtApiController',

            // pass the extension instance as a variable to the controller
            'extension' => $this,
        );
    }


    // handle all backend related tasks
    protected function backendApp()
    {
        /**
         * Add the url rules.
         * Best is to follow the pattern below for your extension to avoid name clashes.
         * ext_example_settings is actually the controller file defined in controllers folder.
         */
        Yii::app()->urlManager->addRules(array(
            array('automation_settings/index', 'pattern'    => 'automation/settings'),
            array('automation_settings/<action>', 'pattern' => 'automation/settings/*'),
        ));

        /**
         * And now we register the controller for the above rules.
         *
         * Please note that you can register controllers and urls rules
         * in any of the apps.
         *
         * Remember how we said that ext_example_settings is actually the controller file:
         */
        Yii::app()->controllerMap['automation_settings'] = array(
            // remember the ext-example path alias?
            'class'     => 'ext-automation.backend.controllers.AutomationExtBackendSettingsController',

            // pass the extension instance as a variable to the controller
            'extension' => $this,
        );
    }


    // handle all customer related tasks
    protected function customerApp()
    {
        /** register the url rule to resolve the pages */
        Yii::app()->urlManager->addRules([
            ['automation/index', 'pattern' => 'automation/index'],
            ['automation_logs/', 'pattern' => 'automation/logs/<action>'],
        ]);

        /** add the controllers */
        Yii::app()->controllerMap['automation'] = array(
            'class'     => 'ext-automation.customer.controllers.AutomationExtCustomerController',
            'extension' => $this,
        );
        /** add the controllers */
        Yii::app()->controllerMap['automation_log'] = array(
            'class'     => 'ext-automation.customer.controllers.AutomationExtCustomerLogController',
            'extension' => $this,
        );

        // add the menu item
        Yii::app()->hooks->addFilter('customer_left_navigation_menu_items', array($this, '_registerCustomerMenuItem'));
    }

    // handle all command related tasks
    public function consoleApp()
    {
        Yii::app()->getCommandRunner()->commands['run-automations'] = array(
            'class' => 'ext-automation.console.commands.RunAutomationCommand'
        );
    }

    // menu callback for backend
    public function _registerBackendMenuItem(array $items = array())
    {
    }

    // menu callback for customer area
    public function _registerCustomerMenuItem(array $items = array())
    {
        $route = Yii::app()->getController()->getRoute();
        $items['reply-tracker'] = array(
            'name'      => Yii::t('ext_automation', 'Automations'),
            'icon'      => 'glyphicon-inbox',
            'active'    => 'automation',
            'route'     => null,
            'items'     => array(
                array('url' => array('automation/index'), 'label' => Yii::t('app', 'Dashboard'), 'active' => strpos($route, 'reply_tracker') !== false),
                array('url' => array('automation/logs'), 'label' => Yii::t('app', 'Logs'), 'active' => strpos($route, 'logs') !== false),
            ),
        );
        return $items;
    }

    /**
     * This is an inherit method where we define the url to our settings page in backed.
     * Remember that we can click on an extension title to view the extension settings.
     * This method generates that link.
     */
    public function getPageUrl()
    {
        return Yii::app()->createUrl('automation_settings/index');
    }


    // create the database table
    public function beforeEnable()
    {
        //reply tracker schema for storing inbound servers
        Yii::app()->getDb()->createCommand("
        CREATE TABLE IF NOT EXISTS `{{automations}}` (
         `automation_id` int(11) NOT NULL AUTO_INCREMENT,
         `customer_id` int(11) DEFAULT NULL,
         `name` varchar(150) NOT NULL,
         `trigger` varchar(150) NOT NULL,
         `canvas` varchar(255) NOT NULL,
         `canvas_data` varchar(150) NOT NULL,
         `locked` enum('yes','no') NOT NULL DEFAULT 'no',
         `canvas` longblob,
         `status` char(15) NOT NULL DEFAULT 'draft',
         `date_added` datetime NOT NULL,
         `last_updated` datetime NOT NULL,
         PRIMARY KEY (`automation_id`),
         KEY `fk_{{automations}}_customer1_idx` (`customer_id`),
         CONSTRAINT `fk_{{automations}}_customer1` FOREIGN KEY (`customer_id`) REFERENCES `{{customer}}` (`customer_id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ")->execute();

        //automation log schema for tracking automation step
        Yii::app()->getDb()->createCommand("
        CREATE TABLE IF NOT EXISTS `{{automation_logs}}` (
         `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
         `customer_id` int(11) NOT NULL,
         `server_id` int(11) NOT NULL,
         `campaign_id` int(11) NOT NULL,
         `subscriber_id` int(11) NOT NULL,
         `message_id` int(11) NOT NULL,
         `message`  longtext DEFAULT NULL,
         `to` varchar(50) DEFAULT NULL,
         `from_email` varchar(50) DEFAULT NULL,
         `from_name` varchar(50) DEFAULT NULL,
         `reply_date` datetime DEFAULT NULL,
         `date_added` datetime NOT NULL,
         `last_updated` datetime NOT NULL,
         PRIMARY KEY (`log_id`),
         KEY `fk_{{reply_tracker_log}}_campaign1_idx` (`campaign_id`),
         KEY `fk_{{reply_tracker_log}}_list_subscriber1_idx` (`subscriber_id`),
        CONSTRAINT `fk_{{reply_tracker_log}}_campaign1` FOREIGN KEY (`campaign_id`) REFERENCES `{{campaign}}` (`campaign_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
         CONSTRAINT `fk_{{reply_tracker_log}}_list_subscriber1` FOREIGN KEY (`subscriber_id`) REFERENCES `{{list_subscriber}}` (`subscriber_id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ")->execute();
        // call parent
        return parent::afterEnable();
    }

    // drop the table when extension is removed.
    public function beforeDelete()
    {
        Yii::app()->getDb()->createCommand('DROP TABLE IF EXISTS `{{reply_trackers}}`')->execute();
        Yii::app()->getDb()->createCommand('DROP TABLE IF EXISTS `{{reply_tracker_log}}`')->execute();
        // call parent
        return parent::beforeDelete();
    }
}