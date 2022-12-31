<?php

defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This is the helper class for automation model canvas data management.
 */

/**
 
 */
class AutomationExtCanvas
{
    /**
     * Automation model for the Canvas
     *
     * @var AutomationExtModel
     */
    public $automation;

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
     * Block list
     *
     * @var AutomationExtBlock[]
     */
    private $blocks = [];

    /**
     * Map for block id against canvas index location
     *
     * @var array
     */
    private $blockIdIndexMap = [];

    public $verbose = true;


    public function __construct(AutomationExtModel $automation, bool $validate = true)
    {

        $this->automation = $automation;
        $this->canvas = (object)json_decode($automation->canvas_data);

        if ($validate) {
            //check and find canvas
            $valid = $this->init() == true;
            if ($valid !== true) {
                throw new Exception($valid, 1);
            }
        }

        return $this;
    }

    public function debug($message)
    {
        if ($this->verbose)
            echo $message;
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

        $trigger_block = new AutomationExtBlock($this->canvas->blocks[0]);
        if ($trigger_block->getGroup() != "triggers") {

            return Yii::t('ext_automation', 'Trigger is required!');
        }

        //validate blocks and it arrangments
        foreach ($this->canvas->blocks as $index => $block) {

            //make block instance
            $block = new AutomationExtBlock($block);
            $this->blocks[$index] = $block;

            $parent_block = $this->getBlockParent($block);

            $valid_block = $this->validateBlock($block, $parent_block);

            //validate block
            if ($valid_block !== true) {

                return "Block $index: $valid_block";
            }

            //block id from UI, while beign integer, it is not order arrange rather follows 
            //order of block insertion. Maintaining index map makes lookup easier using id.
            $block_id = $block->getId();
            $this->blockIdIndexMap[$block_id] = $index;
        }

        return true;
    }

    public function run($params = [])
    {
        $tree = $this->tree();
        $this->runNode($tree, $params);
        dd($tree);
    }

    public function tree()
    {
        $trigger = $this->getTriggerBlock();
        //ensure trigger is not a tree
        $node = $this->buildNode($trigger, [], false);
        return $node;
    }

    private function buildNode(AutomationExtBlock $block, array $prevNode, bool $namedIndex = false)
    {
        $children = $this->getBlockChildren($block);
        $blockId = $namedIndex ? $block->getType() : $block->id;
        $prevNode[$blockId] = [];

        foreach ($children as $childId) {

            $childBlock = $this->getBlockById($childId);

            if ($namedIndex) //subtitle
                $childId = $childBlock->getType();

            $prevNode[$blockId][$childId] = [];

            $hasChildren = count($this->getBlockChildren($childBlock)) > 0;
            if ($hasChildren) {

                $prevNode[$blockId] = $this->buildNode($childBlock, $prevNode[$blockId], $namedIndex);
            }
        }
        return $prevNode;
    }


    private function getBlockChildren(object $block)
    {
        $c = [];
        foreach ($this->getBlocks() as $b) {
            if ($b->parent == $block->id)
                $c[] = $b->id;
        }
        return $c;
    }

    private function runNode($node, $params)
    {
        $node_key = array_keys($node);

        //Two case: either yesNo siblings or non yesNo siblings.
        foreach ($node_key as $key) {
            //has siblings
            if ($this->runBlock($key, $params, count($node_key) > 1) == false) {
                $this->debug("Skipping the branch, run next sibling/branch<br/>");
                continue;
            }
            //return;

            //run children if true reutrn above.
            $this->runNode($node[$key], $params);
        }
    }

    private function runBlock($blockId, $params, $hasSiblings = false)
    {

        $block = $this->getBlockById($blockId);
        $blockType = $block->getType();
        $blockGroup = $block->getGroup();

        $subscriber = $params['subscriber'];
        $automationId = $params['automation_id'];
        //check if executed.
        $c_criteria = new CDbCriteria();
        if ($subscriber) {
            $c_criteria->compare('subject_id', $subscriber->subscriber_uid);
            $c_criteria->compare('subject_type', 'subscriber');
        }
        $c_criteria->compare('canvas_block_id', (int)$blockId);
        $c_criteria->compare('automation_id', $automationId);
        $log = AutomationExtLogModel::model()->find($c_criteria);

        if (!$log) {

            $log = new AutomationExtLogModel();
            $log->automation_id = $automationId;
            $log->canvas_block_id = $blockId;
            $log->subject_id = $subscriber ? $subscriber->subscriber_uid : NULL;
            $log->subject_type = $subscriber ? 'subscriber' : NULL;
            $log->status = AutomationExtModel::STATUS_DRAFT;
            //$log->last_run =
            //if (!$log->save()) {
            //    throw new Exception("Error saving automation:$automationId log for block:$blockId", 1);
            //}
        }

        if ($log->status == AutomationExtModel::STATUS_CRON_RUNNING) {
            //move to anothe branch or break;
        }

        $this->debug("Processing- " . ($hasSiblings ? "(with siblings)" : '') . ": $blockId - $blockType - $blockGroup<br/>");

        //skip for triggers blocks
        if ($blockGroup == AutomationExtBlockGroups::TRIGGER) {
            $this->debug("Skipping trigger");
            return true;
        }

        //skip for triggers blocks
        if ($blockGroup == AutomationExtBlockGroups::ACTION) {
            $actionRunner = new AutomationExtBlockGroupAction($this);
            return $actionRunner->run($block, $subscriber);
        }


        //skip for triggers blocks
        if ($blockGroup == AutomationExtBlockGroups::LOGIC) {
            $logicRunner = new AutomationExtBlockGroupLogic($this);
            return $logicRunner->run($block, $subscriber);
        }

        throw new Exception("Unkown block type: $blockType <br/>", 1);
    }


    public function getTriggerBlock()
    {
        return $this->blocks[0]; //first block must be the trigger
    }

    public function getBlocks()
    {
        return $this->blocks;
    }

    public function getBlockIndexById($blockId)
    {
        return $this->blockIdIndexMap[$blockId];
    }

    public function getBlockById(int $blockId)
    {
        $index = $this->getBlockIndexById($blockId);
        $block = $this->getBlocks()[$index];
        return $block ?? NULL;
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
     * Validate canvas block.
     *
     * @param AutomationExtBlock $block
     * @param AutomationExtBlock|null $parent . The parent block to the current block. NULL for triggers
     * @return string|true
     */
    public function validateBlock(AutomationExtBlock $block, AutomationExtBlock $parent = null)
    {
        //validate structure.
        if (!isset($block->id) || !isset($block->parent) || !isset($block->data)) {

            return Yii::t('ext_automation', 'Invalid block structure on canvas');
        }

        $block = new AutomationExtBlock($block);
        $blockGroup = $block->getGroup();
        $blockType = $block->getType();


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

            $parent = new AutomationExtBlock($parent);
            $parentGroup = $parent->getGroup();
            $parentType = $parent->getType();

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