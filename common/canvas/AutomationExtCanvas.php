<?php
defined('MW_PATH') || exit('No direct script access allowed');

/**
 * This class describes an automation canvas.
 */
class AutomationExtCanvas
{
    
    /**
     * Automation model for the Canvas
     * 
     * @var AutomationExtModel
     */
    public $automation;

    /**
     * DOM cavas object.
     * DOM canvas is expected to look like this:
     * {
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
     *            ...
     *        }
     *    ]
     *    ...
     *  }
     **/
    private $canvas;


    /**
     * Block list
     *
     * @var AutomationExtCanvasBlock[]
     */
    private $blocks = [];

    /**
     * Map for block id against canvas index location
     *
     * @var array
     */
    private $blockIdIndexMap = [];


    /**
     * Control debug message logging.
     *
     * @var        bool
     */
    public $verbose = true;


    /**
     * Constructs a new canvas instance.
     *
     * @param      AutomationExtModel  $automation      The automation
     *
     * @throws     Exception           Invalid canvas structure
     *
     * @return     self                
     */
    public function __construct(AutomationExtModel $automation)
    {

        $this->automation = $automation;
        $this->canvas = (object)json_decode($automation->canvas_data);

        
            //check and find canvas
            $valid = $this->init() == true;
            if ($valid !== true) {

                throw new Exception($valid, 1);
            }
        
        return $this;
    }


    /**
     * Debug message logging
     *
     * @param      string  $message  The message
     */
    public function debug($message)
    {
        if ($this->verbose)
            echo $message;
    }

    /**
     * Initializes the canvas object.
     * Check if canvas and its blocks are valid and also build the blocks property.
     *
     * @return     bool|string  True if fine else error string
     */
    public function init()
    {

        if (!$this->canvas || !$this->canvas->blocks) {

            return Yii::t('ext_automation', 'Canvas is empty!');
        }

        if (count($this->canvas->blocks) < 2) {

            return Yii::t('ext_automation', 'Canvas required minimum of 2 blocks');
        }

        $trigger_block = new AutomationExtCanvasBlock($this->canvas->blocks[0]);
        if ($trigger_block->getGroup() != "triggers") {

            return Yii::t('ext_automation', 'Trigger is required!');
        }

        //validate blocks and it arrangments
        foreach ($this->canvas->blocks as $index => $block) {

            //make block instance
            $block = new AutomationExtCanvasBlock($block);
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


    /**
     * Builds the canvas tree.
     *
     * @return     integer[]  The tree of block chain.
     */
    public function buildTree()
    {
        $trigger = $this->getTriggerBlock();
        //ensure trigger is not a tree
        $node = $this->buildNode($trigger, [], false);
        return $node;
    }


    /**
     * Builds a node.
     *
     * @param      AutomationExtCanvasBlock  $block       The block
     * @param      array                     $prevNode    The previous node (parent node)
     * @param      bool                      $namedIndex  If to use named index or not (named index tree helps with debug)
     *
     * @return     integer[]                     The node.
     */
    private function buildNode(AutomationExtCanvasBlock $block, array $prevNode, bool $namedIndex = false)
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


    /**
     * Gets the block children.
     *
     * @param      AutomationExtCanvasBlock  $block  The block
     *
     * @return     AutomationExtCanvasBlock[]   The block children.
     */
    private function getBlockChildren(AutomationExtCanvasBlock $block)
    {
        $c = [];
        foreach ($this->getBlocks() as $b) {
            if ($b->parent == $block->getId())
                $c[] = $b->id;
        }
        return $c;
    }


    /**
     * Run a canvas node
     *
     * @param      array  $node    The node
     * @param      array  $params  The parameters
     * 
     * @return     void
     */
    private function runNode(array $node, array $params)
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

    /**
     * Run a block.
     *
     * @param      int        $blockId      The block identifier
     * @param      array      $params       The parameters
     * @param      bool       $hasSiblings  Indicates if the block as node siblings
     *
     * @throws     Exception  unkown block error or interna exception
     *
     * @return     bool       True if block run successfully. False stop the block node from running.
     */
    private function runBlock(int $blockId, array $params, bool $hasSiblings = false)
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
        if ($blockGroup == AutomationExtCanvasBlockGroups::TRIGGER) {
            $this->debug("Skipping trigger");
            return true;
        }

        //skip for triggers blocks
        if ($blockGroup == AutomationExtCanvasBlockGroups::ACTION) {
            $actionRunner = new AutomationExtCanvasBlockGroupAction($this);
            return $actionRunner->run($block, $subscriber);
        }


        //skip for triggers blocks
        if ($blockGroup == AutomationExtCanvasBlockGroups::LOGIC) {
            $logicRunner = new AutomationExtCanvasBlockGroupLogic($this);
            return $logicRunner->run($block, $subscriber);
        }

        throw new Exception("Unkown block type: $blockType <br/>", 1);
    }


    /**
     * Gets the trigger block.
     * First block of the canvas must be the trigger
     *
     * @return     AutomationExtCanvasBlock  The trigger block.
     */
    public function getTriggerBlock()
    {
        return $this->blocks[0];
    }

    /**
     * Gets the blocks.
     *
     * @return     AutomationExtCanvasBlock[]  The blocks.
     */
    public function getBlocks()
    {
        return $this->blocks;
    }

    /**
     * Gets the block index by identifier.
     *
     * @param      integer  $blockId  The block identifier
     *
     * @return     AutomationExtCanvasBlock|null  The block object.
     */
    public function getBlockIndexById(int $blockId)
    {
        return $this->blockIdIndexMap[$blockId];
    }

    /**
     * Gets the block by identifier.
     *
     * @param      int     $blockId  The block identifier
     *
     * @return     AutomationExtCanvasBlock|null  The block by identifier.
     */
    public function getBlockById(int $blockId)
    {
        $index = $this->getBlockIndexById($blockId);
        $block = $this->getBlocks()[$index];
        return $block ?? NULL;
    }

    /**
     * Get parent block of a block
     *
     * @param AutomationExtCanvasBlock $block
     * @return AutomationExtCanvasBlock|null
     */
    public function getBlockParent(AutomationExtCanvasBlock $block)
    {

        $parentId = (int)$block->parent; //index

        if ($parentId == -1) return null;

        return $this->getBlockById($parentId);
    }



    /**
     * Validate canvas block.
     *
     * @param AutomationExtCanvasBlock $block
     * @param AutomationExtCanvasBlock|null $parent . The parent block to the current block. NULL for triggers
     * @return string|true
     */
    /**
     * Validate canvas block
     *
     * @param      AutomationExtCanvasBlock  $block   The block
     * @param      AutomationExtCanvasBlock  $parent  The parent block
     *
     * @return     bool|string                      Error string or true
     */
    public function validateBlock(AutomationExtCanvasBlock $block, AutomationExtCanvasBlock $parent = null)
    {
        //validate structure.
        if (!isset($block->id) || !isset($block->parent) || !isset($block->data)) {

            return Yii::t('ext_automation', 'Invalid block structure on canvas');
        }

        $block = new AutomationExtCanvasBlock($block);
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
            if ($blockGroup != AutomationExtCanvasBlockGroups::TRIGGER) {

                return Yii::t('ext_automation', 'Only triggers can used as the first block.');
            }
        }


        if ($parent) {

            $parent = new AutomationExtCanvasBlock($parent);
            $parentGroup = $parent->getGroup();
            $parentType = $parent->getType();

            if (!$blockGroup) {

                return Yii::t('ext_automation', 'Block group parent is unkown');
            }

            if (!$blockType) {

                return Yii::t('ext_automation', 'Block parent type is unkown');
            }

            if ($parentType == $blockType) {

                if ($parentType != AutomationExtCanvasBlockGroups::ACTION) {

                    return Yii::t('ext_automation', 'Cant have same non action block as a direct child');
                }
            }

            if ($blockGroup == AutomationExtCanvasBlockGroups::TRIGGER) { //only on trigger on the canvas

                return Yii::t('ext_automation', 'Canvas already have a trigger');
            }


            //logics
            $yesNo = [AutomationExtCanvasBlockTypes::YES, AutomationExtCanvasBlockTypes::NO];
            $blockIsYesNo = in_array($blockType, $yesNo);
            $parentIsYesNO = in_array($parentType, $yesNo);

            //only logic should follow logics other than yes or no
            if ($parentGroup == AutomationExtCanvasBlockGroups::LOGIC) {

                if (!$parentIsYesNO && $blockGroup != AutomationExtCanvasBlockGroups::LOGIC) {

                    return Yii::t('ext_automation',  'Only logic should be folled by a logics block except for "yes" and "no" logics');
                }
            }


            if ($blockGroup == AutomationExtCanvasBlockGroups::LOGIC) {

                //Non logic blocks should not be followed by yes or no
                if ($parentGroup != AutomationExtCanvasBlockGroups::LOGIC && $blockIsYesNo) {

                    return Yii::t('ext_automation',  'Non logic blocks cant be followed by yes or no');
                }

                //A  "yes" or "no" logic block should not be followed by "yes" or "no" block
                if ($parentIsYesNO && $blockIsYesNo) {

                    return Yii::t('ext_automation',  'A  "yes" or "no" logic block should not be followed by same');
                }

                //A logic block other than "yes" and "no" should be followed by "yes" or "no"
                if ($parentGroup == AutomationExtCanvasBlockGroups::LOGIC && !$parentIsYesNO && !$blockIsYesNo) {

                    return Yii::t('ext_automation',  'A logic block other than "yes" and "no" should be followed by "yes" or "no"');
                }
            }
        }

        return true;
    }
}