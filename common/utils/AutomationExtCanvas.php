<?php

defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the helper class for automation model canvas data management.
 */

/**
 
 */
class AutomationExtCanvas
{

    //a canvas is expected to look like this:
    /**
     * {
     *    "blockarr": [],
     *    "blocks": [
     *        {
     *            "id": 1,
     *            "parent": 0,
     *            "data": [
     *                {
     *                "name": "blockid",
     *                "value": "1"
     *                }
     *            ],
     *            "attr": [
     *                {
     *                "id": "block-id",
     *                "class": "block-class"
     *                }
     *            ]
     *        }
     *    ]
     * ...
     * }
     **/
    private $canvas;

    /**
     * Map for block id against canvas index location
     *
     * @var array
     */
    private $blockIdIndexMap = [];


    public function __construct(string $canvas_data, bool $validate = true)
    {

        $this->canvas = (object)json_decode($canvas_data);

        if ($validate) {
            //check and find canvas
            $valid = $this->init() == true;
            if ($valid !== true) {
                throw new Exception($valid, 1);
            }
        }

        return $this;
    }

    /**
     * Check if ccanvas and its blocks if valid and initation of member is fine.
     *
     * @return string|true
     */
    public function init()
    {

        if (!$this->canvas || !$this->canvas->blocks) {

            return Yii::t('ext_automation', 'Canvas is empty!');
        }

        if (count($this->canvas->blocks) < 2) {

            return Yii::t('ext_automation', 'Canvas required minimum of 2 blocks');
        }

        if ($this->getBlockGroup($this->getTriggerBlock()) != "triggers") {

            return Yii::t('ext_automation', 'Trigger is required!');
        }

        //validate blocks and it arrangments
        foreach ($this->canvas->blocks as $index => $block) {

            $parent_block = $this->getBlockParent($block);

            $valid_block = $this->validateBlock($block, $parent_block);

            //validate block
            if ($valid_block !== true) {

                return "Block $index: $valid_block";
            }

            //block id from UI, while beign integer, it is not order arrange rather follows 
            //order of block insertion. Maintaining index map makes lookup easier using id.
            $block_id = $this->getBlockId($block);
            $this->blockIdIndexMap[$block_id] = $index;
        }

        return true;
    }

    public function tree()
    {
        $trigger = $this->getTriggerBlock();
        $node = $this->buildNode($trigger, [], false);

        $this->runNode($node);
        return $node;
    }

    private function runNode($node)
    {
        $node_key = array_keys($node);

        //Two case: either yesNo siblings or non yesNo siblings.
        foreach ($node_key as $key) {
            //has siblings
            if ($this->runBlock($key, count($node_key) > 1) == false)
                return;

            //run children if true reutrn above.
            $node_key = $this->runNode($node[$key]);
        }
    }

    private function runBlock($blockId, $hasSiblings = false)
    {


        $block = $this->getBlockById($blockId);
        $blockType = $this->getBlockType($block);
        $blockGroup = $this->getBlockGroup($block);

        //skip for triggers blocks
        if ($blockGroup == AutomationExtBlockGroups::TRIGGER) {
            echo "Skipping trigger";
            return true;
        }

        echo "Processing- " . ($hasSiblings ? "(with siblings)" : '') . ": $blockId <br/>";

        return true;


        //logic block and its evaluation
        $yesNo = [AutomationExtBlockTypes::YES, AutomationExtBlockTypes::NO];
        $blockIsYesNo = $blockGroup == AutomationExtBlockGroups::LOGIC && in_array($blockType, $yesNo);
        if ($blockIsYesNo) {
            //evaluate parent
            //YES OR NO....Determine branch to follow.i.e evaluate
            $parent = $this->getBlockParent($block);
            $evaluation = (new AutomationExtEvaluate($parent));
            $blockValue = $blockType == AutomationExtBlockTypes::YES ? true : false;
            if ($evaluation != $blockValue) {
                //evaluation wrong, cant continue; skip this subtree/node move to another branch
                return false;
            }
            //base on the out come of the evaluation, determine if we continue or exit the tree.
        }


        //wait block and its evaluation
        if ($blockType == AutomationExtBlockTypes::WAIT) {
        }


        return true;
    }

    private function buildNode($block, $prevNode, $namedIndex = false)
    {
        $children = $this->getBlockChildren($block);
        $blockId = $namedIndex ? $this->getBlockType($block) : $block->id;
        $prevNode[$blockId] = [];

        foreach ($children as $childId) {

            $childBlock = $this->getBlockById($childId);

            if ($namedIndex) //subtitle
                $childId = $this->getBlockType($childBlock);

            $prevNode[$blockId][$childId] = [];

            $hasChildren = count($this->getBlockChildren($childBlock)) > 0;
            if ($hasChildren) {

                $prevNode[$blockId] = $this->buildNode($childBlock, $prevNode[$blockId], $namedIndex);
            }
        }
        return $prevNode;
    }


    function getBlockChildren($block)
    {
        $c = [];
        foreach ($this->getBlocks() as $b) {
            if ($b->parent == $block->id)
                $c[] = $b->id;
        }
        return $c;
    }

    public function getTriggerBlock()
    {
        return $this->canvas->blocks[0]; //first block must be trigger
    }

    public function getTriggerBlockValue()
    {
        return $this->getBlockDataValueByName("trigger_value", $this->getTriggerBlock());
    }

    public function getTriggerBlockType()
    {
        return $this->getBlockType($this->getTriggerBlock());
    }

    public function getBlocks()
    {
        return $this->canvas->blocks;
    }

    public function getBlockIndexById($blockId)
    {
        return $this->blockIdIndexMap[$blockId];
    }

    public function getBlockById(int $blockId)
    {
        $index = $this->getBlockIndexById($blockId);
        return $this->getBlocks()[$index] ?? NULL;
    }

    public function getBlockValues()
    {
    }

    /**
     * Get parent block of a block
     *
     * @param object $block
     * @return object|null
     */
    public function getBlockParent(object $block)
    {

        $parentId = (int)$block->parent; //index

        if ($parentId == -1) return null;

        return $this->getBlockById($parentId);
    }

    /**
     * Get block group from the block object
     *
     * @param object $block
     * @return string
     */
    public function getBlockGroup(object $block)
    {
        $group = $this->getBlockDataValueByName("blockelemgroup", $block); //should return triggers, action or logic
        return in_array($group, AutomationExtBlockGroups::getConstants()) ? trim($group) : '';
    }

    /**
     * Get block type from the block object
     *
     * @param object $block
     * @return string
     */
    public function getBlockType(object $block)
    {

        $blockType = $this->getBlockDataValueByName("blockelemtype", $block);
        return in_array($blockType, AutomationExtBlockTypes::getConstants()) ? trim($blockType) : '';
    }

    /**
     * Get block id from the block object
     *
     * @param object $block
     * @return int
     */
    public function getBlockId(object $block)
    {
        return $block->id;
    }

    /**
     * Get block parent (id) from the block object
     *
     * @param object $block
     * @return int
     */
    public function getBlockParentId(object $block)
    {
        return $block->parent;
    }

    /**
     * Get block data value from the block object
     *
     * @param string $name
     * @param object|null $block
     * @return string
     */
    public function getBlockDataValueByName(string $name, object $block = null)
    {

        foreach ($block->data as $row) {
            if ($row->name == $name) {
                return $row->value;
            }
        }

        return '';
    }

    /**
     * Validate canvas block.
     *
     * @param object $block
     * @param object $parent . The parent block to the current block. NULL for triggers
     * @return string|true
     */
    public function validateBlock($block, $parent = null)
    {
        //validate structure.
        if (!isset($block->id) || !isset($block->parent) || !isset($block->data)) {

            return Yii::t('ext_automation', 'Invalid block structure on canvas');
        }

        $blockGroup = $this->getBlockGroup($block);
        $blockType = $this->getBlockType($block);


        if (!$blockGroup) {

            return Yii::t('ext_automation', 'Unkown block group');
        }

        if (!$blockType) {

            return Yii::t('ext_automation', 'Unkown block type');
        }

        if (!$parent) {

            //only allow triggers as first blocks
            if ($blockGroup != AutomationExtBlockGroups::TRIGGER) {

                return Yii::t('ext_automation', 'Only triggers can used as the first block.');
            }
        }


        if ($parent) {

            $parentGroup = $this->getBlockGroup($parent);
            $parentType = $this->getBlockType($parent);

            if (!$blockGroup) {

                return Yii::t('ext_automation', 'Block group parent is unkown');
            }

            if (!$blockType) {

                return Yii::t('ext_automation', 'Block parent type is unkown');
            }

            if ($parentType == $blockType) {

                if ($parentType != AutomationExtBlockGroups::ACTION) {

                    return Yii::t('ext_automation', 'Cant have same non action block as a direct child');
                }
            }

            if ($blockGroup == AutomationExtBlockGroups::TRIGGER) { //only on trigger on the canvas

                return Yii::t('ext_automation', 'Canvas already have a trigger');
            }


            //logics
            $yesNo = [AutomationExtBlockTypes::YES, AutomationExtBlockTypes::NO];
            $blockIsYesNo = in_array($blockType, $yesNo);
            $parentIsYesNO = in_array($parentType, $yesNo);

            //only logic should follow logics other than yes or no
            if ($parentGroup == AutomationExtBlockGroups::LOGIC) {

                if (!$parentIsYesNO && $blockGroup != AutomationExtBlockGroups::LOGIC) {

                    return Yii::t('ext_automation',  'Only logic should be folled by a logics block except for "yes" and "no" logics');
                }
            }


            if ($blockGroup == AutomationExtBlockGroups::LOGIC) {

                //Non logic blocks should not be followed by yes or no
                if ($parentGroup != AutomationExtBlockGroups::LOGIC && $blockIsYesNo) {

                    return Yii::t('ext_automation',  'Non logic blocks cant be followed by yes or no');
                }

                //A  "yes" or "no" logic block should not be followed by "yes" or "no" block
                if ($parentIsYesNO && $blockIsYesNo) {

                    return Yii::t('ext_automation',  'A  "yes" or "no" logic block should not be followed by same');
                }

                //A logic block other than "yes" and "no" should be followed by "yes" or "no"
                if ($parentGroup == AutomationExtBlockGroups::LOGIC && !$parentIsYesNO && !$blockIsYesNo) {

                    return Yii::t('ext_automation',  'A logic block other than "yes" and "no" should be followed by "yes" or "no"');
                }
            }
        }

        return true;
    }
}