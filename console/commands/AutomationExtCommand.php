<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * AutomationExtCommand
 *
 * This class handle run-automation command which must have
 * set through cron
 */

class AutomationExtCommand extends ConsoleCommand
{
    /**
     * @var int
     */
    public $fast = 1;

    /**
     * Whether this should be verbose and output to console
     *
     * @var int
     */
    public $verbose = 1;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Main function that is run when the command is started
     * @return int
     */
    public function actionIndex()
    {
        $this->stdout('Start processing...');

        // make sure we only allow a single cron job at a time if this flag is disabled
        $fastLockName = sha1(__METHOD__);
        if (!$this->fast && !Yii::app()->mutex->acquire($fastLockName, 5)) {
            $this->stdout('Cannot acquire lock, seems another process is already running!');
            return 0;
        }

        try {

            // since 1.5.0
            AutomationExtModel::model()->updateAll(array(
                'status' => AutomationExtModel::STATUS_ACTIVE,
            ), 'status = :st', array(
                ':st' => AutomationExtModel::STATUS_CRON_RUNNING,
            ));
            //

            Yii::app()->hooks->doAction('console_command_automation_before_process', $this);

            if ($this->getCanUsePcntl()) {
                $this->stdout('Processing with PCNTL!');
                $this->processWithPcntl();
            } else {
                $this->stdout('Processing without PCNTL!');
                $this->processWithoutPcntl();
            }

            Yii::app()->hooks->doAction('console_command_automation_after_process', $this);
        } catch (Exception $e) {
            $this->stdout(__LINE__ . ': ' .  $e->getMessage());
            Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
        }

        if (!$this->fast) {
            Yii::app()->mutex->release($fastLockName);
        }

        return 0;
    }

    /**
     * Sub process of actionIndex used when pcntl is enabled from your extension page settings
     * @return $this
     * @throws CException
     */
    protected function processWithPcntl()
    {
        // get all automations
        $automations = AutomationExtModel::model()->findAll(array(
            'condition' => 't.status = :status',
            'params'    => array(':status' => AutomationExtModel::STATUS_ACTIVE),
        ));

        // close the external connections
        $this->setExternalConnectionsActive(false);

        // split into x automation chuncks
        $chunkSize    = (int)$this->getOption('pcntl_processes', 10);
        $automationChunks = array_chunk($automations, $chunkSize);
        unset($automations);

        foreach ($automationChunks as $automations) {
            $childs = array();

            foreach ($automations as $automation) {
                $pid = pcntl_fork();
                if ($pid == -1) {
                    continue;
                }

                // Parent
                if ($pid) {
                    $childs[] = $pid;
                }

                // child
                if (!$pid) {
                    try {
                        $this->stdout(sprintf('Started processing automation ID %d.', $automation->automation_id));

                        $automation->processCanvasFromCron(array(
                            'logger' => $this->verbose ? array($this, 'stdout') : null,
                        ));

                        $this->stdout(sprintf('Finished processing automation ID %d.', $automation->automation_id));
                    } catch (Exception $e) {
                        $this->stdout(__LINE__ . ': ' .  $e->getMessage());
                        Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
                    }

                    Yii::app()->end();
                }
            }

            while (count($childs) > 0) {
                foreach ($childs as $key => $pid) {
                    $res = pcntl_waitpid($pid, $status, WNOHANG);
                    if ($res == -1 || $res > 0) {
                        unset($childs[$key]);
                    }
                }
                sleep(1);
            }
        }

        return $this;
    }

    /**
     * If use disallow pcntl multiple process, this function is used.
     * @return $this
     */
    protected function processWithoutPcntl()
    {
        // get all automations
        $automations = AutomationExtModel::model()->findAll(array(
            'condition' => 't.status = :status',
            'params'    => array(':status' => AutomationExtModel::STATUS_ACTIVE),
        ));

        foreach ($automations as $automation) {
            try {
                $this->stdout(sprintf('Started processing automation ID %d.', $automation->automation_id));

                $automation->processCanvasFromCron(array(
                    'logger' => $this->verbose ? array($this, 'stdout') : null,
                ));

                $this->stdout(sprintf('Finished processing automation ID %d.', $automation->automation_id));
            } catch (Exception $e) {
                $this->stdout(__LINE__ . ': ' .  $e->getMessage());
                Yii::log($e->getMessage(), CLogger::LEVEL_ERROR);
            }
        }

        return $this;
    }

    /**
     * Determine if pcntl process can be used or not base on setting and envinronment.
     * @return bool
     */
    protected function getCanUsePcntl()
    {
        static $canUsePcntl;
        if ($canUsePcntl !== null) {
            return $canUsePcntl;
        }
        if ($this->getOption('use_pcntl', 'yes') != 'yes') {
            return $canUsePcntl = false;
        }
        if (!CommonHelper::functionExists('pcntl_fork') || !CommonHelper::functionExists('pcntl_waitpid')) {
            return $canUsePcntl = false;
        }
        return $canUsePcntl = true;
    }

    //This method get instance of the extension
    public function getExtensionInstance()
    {
        return Yii::app()->extensionsManager->getExtensionInstance('automation');
    }

    //We use this method to access extension setting from extension page.
    public function getOption($key, $default = null)
    {
        $extension  = $this->getExtensionInstance();
        return $extension->getOption($key, $default);
    }
}