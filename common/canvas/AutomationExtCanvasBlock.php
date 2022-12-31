<?php

defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This class describe a Canvas block.
 */
class AutomationExtCanvasBlock
{
    public $id;

    public function __construct(object $block)
    {
        foreach ($block as $key => $value) $this->{$key} = $value;
    }

    /**
     * Get block group from the block object
     *
     * @return string
     */
    public function getGroup()
    {
        $group = $this->getDataByName("blockelemgroup"); //should return triggers, action or logic
        return in_array($group, AutomationExtCanvasBlockGroups::getConstants()) ? trim($group) : '';
    }

    /**
     * Get block type from the block object
     *
     * @return string
     */
    public function getType()
    {

        $blockType = $this->getDataByName("blockelemtype");
        return in_array($blockType, AutomationExtCanvasBlockTypes::getConstants()) ? trim($blockType) : '';
    }

    /**
     * Get block id from the block object
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get block parent (id) from the block object
     *
     * @return int
     */
    public function getParentId()
    {
        return $this->parent;
    }

    public function getData()
    {
        $data = [];
        foreach ($this->data as $row) {
            $data[$row->name] = $row->value;
        }
        return $data;
    }

    /**
     * Get block data value from the block object
     *
     * @param string $name
     * @param object|null $block
     * @return string
     */
    public function getDataByName(string $name)
    {

        foreach ($this->data as $row) {
            if ($row->name == $name) {
                return $row->value;
            }
        }

        return '';
    }

    /**
     * Return block data named "trigger_value"
     * Empty if the block is not a trigger block.
     * 
     * @return string
     */
    public function getTriggerValue()
    {
        return $this->getDataByName("trigger_value");
    }
}