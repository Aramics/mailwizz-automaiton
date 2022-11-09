<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ReplyTrackerExtInboundModelHandlerCommand
 *
 * This class handle track-reply command which must have
 * set through cron
 */
 
class TrackReplyCommand extends ConsoleCommand
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

        Yii::import('common.vendors.BounceHandler.*');
    }

    /**
     * Main function that is run when the command is started
     * @return int
     */
    public function actionIndex()
    {
        $this->stdout('Start processing...');
        
        // because some cli are not compiled same way with the web module.
        if (!CommonHelper::functionExists('imap_open')) {
            $message = Yii::t('servers', 'The PHP CLI binary is missing the IMAP extension!');
            Yii::log($message, CLogger::LEVEL_ERROR);
            $this->stdout($message);
            return 1;
        }

        // make sure we only allow a single cron job at a time if this flag is disabled
        $fastLockName = sha1(__METHOD__);
        if (!$this->fast && !Yii::app()->mutex->acquire($fastLockName, 5)) {
            $this->stdout('Cannot acquire lock, seems another process is already running!');
            return 0;
        }
        
        try {

            // since 1.5.0
            ReplyTrackerExtInboundModel::model()->updateAll(array(
                'status' => ReplyTrackerExtInboundModel::STATUS_ACTIVE,
            ), 'status = :st', array(
                ':st' => ReplyTrackerExtInboundModel::STATUS_CRON_RUNNING,
            ));
            //
            
            // added in 1.3.4.7
            Yii::app()->hooks->doAction('console_command_reply_tracker_before_process', $this);

            if ($this->getCanUsePcntl()) {
                $this->stdout('Processing with PCNTL!');
                $this->processWithPcntl();
            } else {
                $this->stdout('Processing without PCNTL!');
                $this->processWithoutPcntl();
            }

            // added in 1.3.4.7
            Yii::app()->hooks->doAction('console_command_reply_tracker_after_process', $this);
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
        // get all servers
        $servers = ReplyTrackerExtInboundModel::model()->findAll(array(
            'condition' => 't.status = :status',
            'params'    => array(':status' => ReplyTrackerExtInboundModel::STATUS_ACTIVE),
        ));

        // close the external connections
        $this->setExternalConnectionsActive(false);

        // split into x server chuncks
        $chunkSize    = (int)$this->getOption('pcntl_processes', 10);
        $serverChunks = array_chunk($servers, $chunkSize);
        unset($servers);

        foreach ($serverChunks as $servers) {
            $childs = array();

            foreach ($servers as $server) {
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
                        $this->stdout(sprintf('Started processing server ID %d.', $server->server_id));
                        
                        $server->processRemoteContents(array(
                            'logger' => $this->verbose ? array($this, 'stdout') : null,
                        ));

                        $this->stdout(sprintf('Finished processing server ID %d.', $server->server_id));
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
        // get all servers
        $servers = ReplyTrackerExtInboundModel::model()->findAll(array(
            'condition' => 't.status = :status',
            'params'    => array(':status' => ReplyTrackerExtInboundModel::STATUS_ACTIVE),
        ));

        foreach ($servers as $server) {
            try {
                $this->stdout(sprintf('Started processing server ID %d.', $server->server_id));
                
                $server->processRemoteContents(array(
                    'logger' => $this->verbose ? array($this, 'stdout') : null,
                ));

                $this->stdout(sprintf('Finished processing server ID %d.', $server->server_id));
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
        return Yii::app()->extensionsManager->getExtensionInstance('reply-tracker');
    }

    //We use this method to access extension setting from extension page.
    public function getOption($key, $default=null)
    {
        $extension  = $this->getExtensionInstance();
        return $extension->getOption($key, $default);
    }
}