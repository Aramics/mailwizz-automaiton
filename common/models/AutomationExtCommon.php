<?php defined('MW_PATH') || exit('No direct script access allowed');

/**
 * ReplyTrackerExtCommon
 * Model for Extension page settings
 */

class AutomationExtCommon extends OptionBase
{
    //asset public path
    const CUSTOMER_ASSETS_PATH = MW_ROOT_PATH . '/customer/assets/ext-automation';

    //enabling the front end functionalities.
    public $enabled = 'no';

    /**
     * @var array
     * Customer groups allowed to use automations
     */
    public $customer_groups = [];

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
            array('customer_groups', 'safe'),
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
            'customer_groups'        => Yii::t('settings', 'Customer groups'),
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
            'exclude_autoresponse'       => Yii::t('settings', 'Decide which customer groups can make use of this extension. If no group is selected, then all customers can use it.'),
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
        return Yii::app()->extensionsManager->getExtensionInstance('automation');
    }

    /**
     * @return array
     */
    public function getCustomerGroupsList(): array
    {
        $groups = CustomerGroup::model()->findAll();
        $list   = [];

        foreach ($groups as $group) {
            $list[$group->group_id] = $group->name;
        }

        return $list;
    }
}