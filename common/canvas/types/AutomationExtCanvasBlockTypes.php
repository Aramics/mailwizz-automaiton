<?php

defined('MW_PATH') || exit('No direct script access allowed');

class AutomationExtCanvasBlockTypes extends AutomationExtReflection
{
    const LIST_SUBSCRIPTION =  "list-subscription"; //trigger
    const LIST_UNSUBSCRIPTION =  "list-unsubscription"; //trigger
    const WEBHOOK =  "webhook"; //trigger
    const SPECIFIC_DATE =  "specific-date"; //trigger/logic
    const SUBSCRIBER_ADDED_DATE =  "subscriber-added-date"; //trigger
    const INTERVAL =  "interval"; //trigger
    const OPEN_EMAIL =  "open-email"; //trigger/logic
    const CLICK_URL =  "click-url"; //trigger/logic
    const REPLY_EMAIL =  "reply-email"; //trigger/logic
    const WAIT =  "wait"; //action
    const SEND_EMAIL =  "send-email"; //action
    const SEND_CAMPAIGN =  "send-campaign"; //action
    const RUN_CAMPAIGN =  "run-campaign"; //action
    const OTHER_CAMPAIGN_ACTION = "other-campaign-action"; //action
    const MOVE_SUBSCRIBER =  "move-subscriber"; //action
    const COPY_SUBSCRIBER =  "copy-subscriber"; //action 
    const UPDATE_SUBSCRIBER =  "update-subscriber"; //action
    const OTHER_SUBSCRIBER_ACTION =  "other-subscriber-action"; //action
    const WEBHOOK_ACTION =  "webhook-action"; //action
    const STOP =  "stop"; //action
    const YES =  "yes"; //logic
    const NO =  "no"; //logic
    const SUBSCRIBER_COUNT =  "subscriber-count"; //logic
}