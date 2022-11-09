<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ReplyTrackerExtCommon
 * Model for Extension page settings
 */

class ReplyTrackerExtCommon extends OptionBase
{
    //enabling the front end functionalities.
    public $enabled = 'no';

    // how many servers to process at once
    public $servers_at_once = 10;

    // how many emails should we load at once for each loaded server
    public $emails_at_once = 500;

    // how many seconds should we pause between the batches
    public $pause = 5;

    // select emails that are newer than x days . Use zero for only new date for fast perfomance
    public $days_back = 0;

    // level of reply matching pattern
    public $strictness = 'low';

    // exclude autoresponses as reply.
    public $exclude_autoresponse = 'yes';

    // whether to use pcntl
    public $use_pcntl = 'yes';

    // how many pcntl processes
    public $pcntl_processes = 10;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = array(
            array('enabled', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('servers_at_once, emails_at_once, pause, days_back', 'required'),
            array('servers_at_once, emails_at_once, pause', 'numerical', 'integerOnly' => true),
            array('servers_at_once', 'numerical', 'min' => 1, 'max' => 100),
            array('emails_at_once', 'numerical', 'min' => 100, 'max' => 1000),
            array('pause, days_back', 'numerical', 'min' => 0, 'max' => 60),
            array('strictness', 'in', 'range' => array_keys($this->getDifficultyOptions())),
            array('exclude_autoresponse', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('use_pcntl', 'in', 'range' => array_keys($this->getYesNoOptions())),
            array('pcntl_processes', 'numerical', 'min' => 1, 'max' => 100),
        );

        return CMap::mergeArray($rules, parent::rules());
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        $labels = array(
            'enabled'       => Yii::t('app', 'Enabled'),
            'servers_at_once'  => Yii::t('settings', 'Servers at once'),
            'emails_at_once'   => Yii::t('settings', 'Emails at once'),
            'pause'            => Yii::t('settings', 'Pause'),
            'days_back'        => Yii::t('settings', 'Days back'),
            'strictness'        => Yii::t('settings', 'Strictness'),
            'exclude_autoresponse'        => Yii::t('settings', 'Exclude autoresponses'),
            'use_pcntl'        => Yii::t('settings', 'Parallel processing via PCNTL'),
            'pcntl_processes'  => Yii::t('settings', 'Parallel processes count')
        );
        return CMap::mergeArray($labels, parent::attributeLabels());
    }

    /**
     * @return array default attribute labels (name=>label)
     */
    public function attributePlaceholders()
    {
        $placeholders = array(
            'servers_at_once'  => null,
            'emails_at_once'   => null,
            'pause'            => null,
            'days_back'        => 0,
            'strictness'       => 'low',
            'exclude_autoresponse'       => 'yes',
            'pcntl_processes'  => 10,
        );
        return CMap::mergeArray($placeholders, parent::attributePlaceholders());
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeHelpTexts()
    {
        $texts = array(
            'enabled'       => Yii::t('settings', 'Whether the feature is enabled'),
            'servers_at_once'  => Yii::t('settings', 'How many servers to process at once.'),
            'emails_at_once'   => Yii::t('settings', 'How many emails for each server to process at once.'),
            'pause'            => Yii::t('settings', 'How many seconds to sleep after processing the emails from a server.'),
            'days_back'        => Yii::t('settings', 'Process emails that are newer than this amount of days. Increasing the number of days increases the amount of emails to be processed. Basically leave this at 0 to process for reply for current day only, this will also boost performance so far cron is run at least daily.'),
            'strictness'       => Yii::t('settings', 'Level of reply matching. Low for general use'),
            'exclude_autoresponse'       => Yii::t('settings', 'Determine if autoreply/out of office mail should be excluded as reply or not.'),
            'use_pcntl'        => Yii::t('settings', 'Whether to process using PCNTL, that is multiple processes in parallel.'),
            'pcntl_processes'  => Yii::t('settings', 'The number of processes to run in parallel.'),
        );
        return CMap::mergeArray($texts, parent::attributeHelpTexts());
    }

    /**
     * This method is run when setting is saved
     *
     **/
    public function save()
    {
        $extension  = $this->getExtensionInstance();
        if (!$this->validate()) {
            return false;
        }

        foreach ($this->getAttributes() as $attributeName => $attributeValue) {
            $extension->setOption($attributeName, $attributeValue);
        }

        return $this;
    }

    /**
     * Populate the model instance with saved or default settings
     *
     **/
    public function populate()
    {
        $extension  = $this->getExtensionInstance();
        $attributes = $this->getAttributes();
        foreach ($this->getAttributes() as $attributeName => $attributeValue) {
            $this->$attributeName = $extension->getOption($attributeName, $attributeValue);
        }
        return $this;
    }

    /**
     * We use this method to access extension setting from extension page.
     **/
    public function getExtensionInstance()
    {
        return Yii::app()->extensionsManager->getExtensionInstance('reply-tracker');
    }

    /**
     * @return array
     */
    public function getDifficultyOptions()
    {
        return array('low' => Yii::t('app', 'Low'), 'high' => Yii::t('app', 'High'));
    }
}