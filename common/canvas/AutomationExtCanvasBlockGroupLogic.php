<?php
defined('MW_PATH') || exit('No direct script access allowed');

/**
 * 
 */
class AutomationExtCanvasBlockGroupLogic extends AutomationExtCanvasBlockGroupAction
{

    /**
     * Create new instance of AutomationExtCanvasBlockGroupAction
     * We require the canvas to give easy access to some methods and properties.
     *
     * @param AutomationExtCanvas $canvas
     */
    public function __construct(AutomationExtCanvas $canvas)
    {
        parent::__construct($canvas);
    }

    /**
     * Run a logic block.
     * In real sensce, it only evaluate the parent of YES or NO block only 
     * and alway return True when non YES or NO block is passed as block.
     *
     * @param AutomationExtCanvasBlock $block
     * @param ListSubscriber|null $subscriber
     * @return bool False when block is YES or NO and parent block evaluation does not matches, otherwise True
     * 
     * @throws Exception Invalid block group
     */
    public function run(AutomationExtCanvasBlock $block, ListSubscriber $subscriber = null)
    {
        $blockType = $block->getType();
        $blockGroup = $block->getGroup();

        if ($blockGroup !== AutomationExtCanvasBlockGroups::LOGIC) {
            throw new Exception(sprintf(t("automation", "Invalid block group %s passed as logic"), $blockGroup), 1);
        }

        $yesNoBlock = $blockType == AutomationExtCanvasBlockTypes::NO || $blockType == AutomationExtCanvasBlockTypes::YES;

        // A non YesNo log block must be followed by either YES or NO block
        // Thus, skip non YesNo logic. The will be evaluated by YesNo block as a parent.
        if (!$yesNoBlock) {

            $this->debug("<br/>Skipping evaluation logic block till child YES or NO.<br/>");
            return true; //allow to continue to child block (i.e YES or NO)
        }


        // if reaches here, block is either YES or NO,
        // we want to evaluate only its parent logic.
        $parentBlock = $this->canvas->getBlockParent($block);
        $data = $parentBlock->getData();
        $parentType = $parentBlock->getType();


        $parentEvaluation = false;

        if (!$subscriber && $parentType != AutomationExtCanvasBlockTypes::SPECIFIC_DATE) {
            $this->debug(t("automations", "Subscriber is required for open email block evaluation"));
            return false;
        }

        // Evalutate the parent block
        switch ($parentType) {
            case AutomationExtCanvasBlockTypes::SPECIFIC_DATE:
                $parentEvaluation = $this->dateOperation($data['datetime'], $data['operator']);
                break;

            case AutomationExtCanvasBlockTypes::OPEN_EMAIL:
                $parentEvaluation = $this->openEmail($subscriber, (int)$data['campaign']);
                break;

            case AutomationExtCanvasBlockTypes::CLICK_URL:
                $parentEvaluation = $this->clickUrl($subscriber, (int)$data['url']);
                break;

            case AutomationExtCanvasBlockTypes::REPLY_EMAIL:
                $parentEvaluation = $this->replyEmail($subscriber, (int)$data['campaign']);
                break;

            default:
                throw new \Exception("Unkown logic passed for evaluation", 1);
                break;
        }


        // Expect value will be true if block is YES else false
        $blockExpectedValue = $blockType == AutomationExtCanvasBlockTypes::YES ? true : false;
        $this->debug(sprintf("Seeking %s - %s<br/><br/>", $parentEvaluation, $blockExpectedValue));

        // base on the out come of the evaluation, determine if we continue or exit the tree. 
        // to continue the current node, parent block evaluation must matches with block expected value.
        // if block expected value is true (i.e YES block) parent evaluation must also be true 
        // else we move to another chain.
        if ($blockExpectedValue != $parentEvaluation) {
            // evaluation wrong, cant continue; skip this subtree/node move to another branch
            return false;
        }

        return true; //continue with flow.
    }

    /**
     * The method carry out operation on the block datetime string.
     *
     * @param string $dateTime
     * @param string $operator Supported operators: ">", "<", and "="
     * @return bool
     */
    public function dateOperation(string $dateTime, string $operator)
    {

        // since the dateTime is exepcted to be saved in block in customer timezone 
        // we need to get the current time for comparison is customer timezone also

        $customerTimeZone         = new DateTimeZone($this->automation->customer->timezone);
        $dateTime = new DateTime((string)$dateTime, $customerTimeZone);
        $now = new DateTime("now", $customerTimeZone); //get current time in app time zone

        //compare $dateTime with the current time.
        switch ($operator) {
            case '>':
                $result = $dateTime > $now;
                break;

            case '<':
                $result = $dateTime < $now;
                break;
            case '=':
                $result = $dateTime == $now;
                break;

            default:
                $result = false;
                break;
        }
        return $result;
    }

    /**
     * Check if the subscriber has open the email or cmapign
     *
     * @param ListSubscriber $subscriber
     * @param integer $campaignId
     * @return bool
     */
    public function openEmail(ListSubscriber $subscriber, int $campaignId)
    {
        $count = (int)CampaignTrackOpen::model()->countByAttributes([
            'campaign_id'   => (int)$campaignId,
            'subscriber_id' => (int)$subscriber->subscriber_id,
        ]);
        return $count > 0;
    }

    /**
     * Check if the url has been clicked by the subscriber
     *
     * @param ListSubscriber $subscriber
     * @param integer $urlId
     * @return bool
     */
    public function clickUrl(ListSubscriber $subscriber, int $urlId)
    {
        $count = (int)CampaignTrackUrl::model()->countByAttributes([
            'url_id'   => (int)$urlId,
            'subscriber_id' => (int)$subscriber->subscriber_id,
        ]);
        return $count > 0;
    }

    /**
     * Method to check if the subscriber has replied to the campaign.
     * Requried Reply Extension plugin to work: https://codecanyon.net/item/reply-tracker-for-mailwizz-ema/
     *
     * @param ListSubscriber $subscriber
     * @param integer $campaignId
     * @return bool
     */
    public function replyEmail(ListSubscriber $subscriber, int $campaignId)
    {
        if (!class_exists('ReplyTrackerExtLogModel')) {
            $this->debug(t("automations", "Reply tracker extension is required to track email response."));
            return false;
        }

        $count = (int)ReplyTrackerExtLogModel::model()->countByAttributes([
            'campaign_id'   => (int)$campaignId,
            'subscriber_id' => (int)$subscriber->subscriber_id,
        ]);

        return $count > 0;
    }
}
