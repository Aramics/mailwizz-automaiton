<?php defined('MW_PATH') || exit('No direct script access allowed');

class AutomationExt extends ExtensionInit
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
    public $allowedApps = array('backend', 'customer', 'console', 'api', 'frontend');

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

        $this->loadModels();

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

        //bind bindable triggers
        (new AutomationExtGroupTriggers())->init($this);
    }


    private function loadModels()
    {
        Yii::import('ext-automation.common.utils.*');

        Yii::import('ext-automation.common.models.*');
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
            array('automations/<action>', 'pattern'    => 'automations/*'),
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
         */
        Yii::app()->cache->flush();
        Yii::app()->urlManager->addRules(array(
            array('automation', 'pattern'    => 'automations/<action>'),
        ));

        /**
         * And now we register the controller for the above rules.
         */
        Yii::app()->controllerMap['automation'] = array(
            // remember the ext-example path alias?
            'class'     => 'ext-automation.backend.controllers.AutomationExtBackendSettingsController',

            // pass the extension instance as a variable to the controller
            'extension' => $this,
        );
    }


    // handle all customer related tasks
    protected function customerApp()
    {

        /** add the controllers */
        Yii::app()->controllerMap['automations'] = array(
            'class'     => 'ext-automation.customer.controllers.AutomationExtCustomerController',
            'extension' => $this,
        );

        // add the menu item
        Yii::app()->hooks->addFilter('customer_left_navigation_menu_items', array($this, '_registerCustomerMenuItem'));
    }

    // handle all command related tasks
    public function consoleApp()
    {
        Yii::app()->getCommandRunner()->commands['run-automation'] = array(
            'class' => 'ext-automation.console.commands.AutomationExtCommand'
        );
    }

    // menu callback for customer area
    public function _registerCustomerMenuItem(array $items = array())
    {
        $route = Yii::app()->getController()->getRoute();
        $items['automation'] = array(
            'name'      => Yii::t('ext_automation', 'Automations'),
            'icon'      => 'glyphicon-plane',
            'active'    => 'automation',
            'route'     => array('automations/index'),
            'items'     => null
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
        return Yii::app()->createUrl('automations/index');
    }


    // create the database table
    public function beforeEnable()
    {
        //reply tracker schema for storing inbound servers
        Yii::app()->getDb()->createCommand("
        CREATE TABLE IF NOT EXISTS `{{automations}}` (
         `automation_id` int(11) NOT NULL AUTO_INCREMENT,
         `customer_id` int(11) NOT NULL,
         `title` varchar(150) NOT NULL,
         `trigger` varchar(150) DEFAULT NULL,
         `trigger_value` varchar(250) DEFAULT NULL,
         `locked` enum('yes','no') NOT NULL DEFAULT 'no',
         `canvas_data` MEDIUMTEXT DEFAULT NULL,
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
         `parent_log_id` bigint(20) DEFAULT NULL,
         `automation_id` int(11) NOT NULL,
         `subject_id` int(11) DEFAULT NULL,
         `subject_type` varchar(250) DEFAULT NULL,
         `canvas_block_id` int(11) NOT NULL,
         `metadata`  MEDIUMTEXT DEFAULT NULL,
         `status` char(15) NOT NULL DEFAULT 'draft',
         `date_added` datetime NOT NULL,
         `last_updated` datetime NOT NULL,
         PRIMARY KEY (`log_id`),
         KEY `fk_{{automations}}_automation1_idx` (`automation_id`),
        CONSTRAINT `fk_{{automations}}_automation1` FOREIGN KEY (`automation_id`) REFERENCES `{{automations}}` (`automation_id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ")->execute();

        $this->loadModels();
        //CFileHelper::copyDirectory($src = __DIR__ . '/assets', $dst = AutomationExtCommon::CUSTOMER_ASSETS_PATH, array('newDirMode' => 0777));

        // call parent
        return parent::beforeEnable();
    }

    // drop the table when extension is removed.
    public function beforeDelete()
    {
        Yii::app()->getDb()->createCommand('DROP TABLE IF EXISTS `{{automation_logs}}`')->execute();
        Yii::app()->getDb()->createCommand('DROP TABLE IF EXISTS `{{automations}}`')->execute();

        $this->loadModels();
        //CFileHelper::removeDirectory(AutomationExtCommon::CUSTOMER_ASSETS_PATH);

        return false;
        // call parent
        return parent::beforeDelete();
    }
}