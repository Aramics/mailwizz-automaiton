const commonActions = [
    {
        key: "open-email",
        title: "Open email",
        description: "Open a campaign or an email",
        icon: ASSETS_PATH + "/images/open-email.svg",
        shape: "diamond",
        summary: "$campaign opened",
        components: ["campaign-list"],
    },
    {
        key: "click-url",
        title: "Click URL",
        description: "Click on certain or any URL in a campaign or an email",
        shape: "diamond",
        icon: ASSETS_PATH + "/images/click-url.svg",
        summary: "$url in $campaign-with-url Clicked",
        components: [
            {
                "campaign-list": {
                    name: "campaign-with-url",
                    label: "Select an email",
                },
            },
            { "url-list": {} },
        ],
    },
    {
        key: "reply-email",
        title: "Reply email",
        description: "Respond to a certain email or campaign",
        shape: "diamond",
        icon: ASSETS_PATH + "/images/reply-email.svg",
        summary: "$campaign replied to",
        components: ["campaign-list"],
    },
];

const blockList = Object.freeze( [
    {
        key: "triggers",
        title: "Triggers",
        blocks: [
            {
                key: "list-subscription",
                title: "List subscription",
                description: "Welcome automation when a subscriber join.",
                icon: ASSETS_PATH + "/images/subscribe.svg",
                summary: "On subscription to $mail-list",
                components: [{ "mail-list": { label: "" } }],
            },
            {
                key: "list-unsubscription",
                title: "List unsubscribe",
                description: "Say goodbye to a customer.",
                icon: ASSETS_PATH + "/images/unsubscribe.svg",
                summary: "When unsubscribe from $mail-list",
                components: [{ "mail-list": { label: "" } }],
            },
            {
                key: "webhook",
                title: "Webhook (API)",
                description:
                    "Trigger from http call to automation webhook address",
                icon: ASSETS_PATH + "/images/webhook.svg",
                summary: "$method to $webhook_endpoint",
                components: [
                    {
                        input: {
                            label: "Endpoint (readonly)",
                            type: "url",
                            name: "webhook_endpoint",
                            value: AUTOMATION_WEBHOOK_URL,
                            readonly: true,
                        },
                    },
                    {
                        list: {
                            label: "Method",
                            name: "method",
                            items: [
                                { label: "GET", key: "get" },
                                { label: "POST", key: "post" },
                            ],
                        },
                    },
                ],
            },
            {
                key: "specific-date",
                title: "Specific date time",
                description:
                    "Start an automation based on an individual date, like an appointment.",
                icon: ASSETS_PATH + "/images/date-time.svg",
                summary: "$date",
                components: [{ input: { type: "datetime-local", name: "date" } }],
            },

            {
                key: "subscriber-added-date",
                title: "Subscriber added date",
                description:
                    "Run action for subscribers base on the date they joined the list.",
                icon: ASSETS_PATH + "/images/date.svg",
                summary: "$mail-list",
                components: ["mail-list"],
            },

            {
                key: "interval",
                title: "Recurring Interval",
                description:
                    "Schedule series of action base on certain intervals (hourly, daily, weekly, monthly e.t.c)",
                icon: ASSETS_PATH + "/images/interval.svg",
                summary: "Every $count $period",
                components: [
                    {
                        interval: {
                            label: "Every: ",
                            interval_name: "count",
                            unit_name: "period",
                        },
                    },
                ],
            },

            ...commonActions,
        ],
    },

    {
        key: "actions",
        title: "Actions",
        blocks: [
            {
                key: "wait",
                title: "Wait",
                description: "Wait for a certain period",
                icon: ASSETS_PATH + "/images/wait.svg",
                shape: "parallelogram",
                summary: "$count $period",
                components: [
                    {
                        interval: {
                            label: "",
                            interval_name: "count",
                            unit_name: "period",
                        },
                    },
                ],
            },
            {
                key: "send-email",
                title: "Send an email",
                description:
                    "Send a campaign/autoresponder email content to only the current subscriber.",
                summary: "to the subscriber",
                icon: ASSETS_PATH + "/images/send-email.svg",
                components: ["campaign-list"],
            },
            {
                key: "run-campaign",
                title: "Run a campaign",
                description:
                    "Run a campaign to all the campaign list subscribers.",
                summary: "Start/Run $campaign-list",
                icon: ASSETS_PATH + "/images/send-campaign.svg",
                components: ["campaign-list"],
            },
            {
                key: "move-subscriber",
                title: "Move subscriber",
                description: "Move the subscriber to another list",
                summary: "to $mail-list",
                icon: ASSETS_PATH + "/images/move.svg",
                components: [{ "mail-list": { label: "To which mail list ?" } }],
            },
            {
                key: "copy-subscriber",
                title: "Copy subscriber",
                description: "Copy the subscriber to another list",
                summary: "to $mail-list",
                icon: ASSETS_PATH + "/images/copy.svg",
                components: [{ "mail-list": { label: "To which mail list ?" } }],
            },
            {
                key: "update-subscriber",
                title: "Update subscriber field",
                description: "Update certain field/tag for the subscriber.",
                summary: "Update for the matched fields",
                icon: ASSETS_PATH + "/images/update.svg",
                components: [
                    {
                        "input-pair-repeat": {
                            label: "Fields TAG",
                            help: "You can use list field variable as values i.e [FNAME].",
                        },
                    },
                ],
            },
            {
                key: "remove-subscriber",
                title: "Remove subscriber",
                description: "Remove the subscriber from the list",
                icon: ASSETS_PATH + "/images/remove.svg",
                summary: "from the current list",
            },
            {
                key: "webhook-action",
                title: "Call webhook",
                description:
                    "Send data to an endpoint (can be use to start another automation with webhook trigger)",
                icon: ASSETS_PATH + "/images/webhook.svg",
                summary: "$method to $webhook_endpoint",
                components: [
                    {
                        input: {
                            label: "Endpoint",
                            name: "webhook_endpoint",
                            type: "url",
                            value: "",
                        },
                    },
                    {
                        list: {
                            label: "Method",
                            name: "method",
                            items: [
                                { label: "GET", key: "get" },
                                { label: "POST", key: "post" },
                            ],
                        },
                    },
                    {
                        "input-pair-repeat": {
                            label: "Fields",
                            help: "Field name starting with X-HEADER will be passed in the request header only.<br/>You can also use list field variable as values i.e [FNAME]",
                        },
                    },
                ],
            },
            {
                key: "stop",
                title: "Stop this automation",
                description: "Stop and disable the automation",
                icon: ASSETS_PATH + "/images/stop.svg",
                summary: "end",
            },
        ],
    },

    {
        key: "logic",
        title: "Logics",
        blocks: [
            {
                key: "yes",
                title: "Yes",
                description: "Evaluate to yes or true or 1",
                icon: ASSETS_PATH + "/images/yes.svg",
                shape: "circle",
            },
            {
                key: "no",
                title: "No",
                description: "Evaluate to no or false or 0 or null",
                icon: ASSETS_PATH + "/images/no.svg",
                shape: "circle",
            },
            {
                key: "subscriber-count",
                title: "Subscribers",
                description:
                    "Evaluate to arithmetic on an audience. i.e subscriberCount equals 100",
                icon: ASSETS_PATH + "/images/subscribers.svg",
                shape: "diamond",
                summary: "$operator $count for $mail-list",
                components: [
                    { "mail-list": { label: "When subscribers in ?" } },
                    { operators: { name: "operator" } },
                    { input: { type: "number", name: "count" } },
                ],
            },
            {
                key: "now",
                title: "Date",
                description: "Date arithmetic",
                icon: ASSETS_PATH + "/images/calender.svg",
                summary: "$operator $date",
                shape: "diamond",
                components: [
                    { operators: { name: "operator" } },
                    { input: { type: "date", name: "date" } },
                ],
            },
            ...commonActions,
        ],
    },
] );

const getBlock = ( groupId, blockId ) => {
    let data = window.blockData;
    let blockIndex = data.blockIndexes[groupId][blockId];
    let groupIndex = data.groupIndexes[groupId];
    console.log( groupIndex, blockIndex );
    return data.blockList[groupIndex]["blocks"][blockIndex];
};

//generate style for the blocks icons
const getBlocksStyle = () => {
    return window.blockData.blockStyles;
};

//generate block component

//@TODO: remove
const getBlockHtml = ( blockItem, groupId ) => {
    if ( !blockItem ) return "";
    return `
    <div class="blockelem create-flowy noselect ${groupId} ${blockItem.key} ${blockItem.shape ?? ""
        }">
        <input type="hidden" name='blockelemtype' class="blockelemtype" value="${blockItem.key
        }">
        <input type="hidden" name='blockelemgroup' class="blockelemgroup" value="${groupId}">
        <div class="grabme">
            <img src=ASSETS_PATH+"/images/grabme.svg">
        </div>
        <div class="block-content">
            <div class="block-icon">
                <span></span>
            </div>
            <div class="block-text">
                <p class="block-title">${blockItem.title}</p>
                <p class="block-desc">${blockItem.description}</p>
            </div>
        </div>
        <div class="block-canvas-content">
            <div class="block-canvas-left">
                <div class="block-icon">
                    <span></span>
                </div>
                <p class="block-canvas-name">${blockItem.title}</p>
            </div>
            <div class='block-canvas-right'><img src=ASSETS_PATH+"/images/more.svg"></div>
            <div class='block-canvas-div'></div>
            <div class='block-canvas-info'>loading...</div>
        </div>
    </div>`;
};

//generate all necessary block data and attach to window
const initBlocks = () => {
    let blockStyles = "";
    let blockIndexes = {};
    let groupIndexes = {};
    let blockListFlattened = [];

    for ( let index = 0; index < blockList.length; index++ ) {
        const group = blockList[index];
        groupIndexes[group.key] = index;
        blockIndexes[group.key] = [];

        for ( let j = 0; j < group.blocks.length; j++ ) {
            const block = group.blocks[j];

            blockListFlattened.push( block );
            blockIndexes[group.key][block.key] = j;

            if ( block?.icon ) {
                //generate style for the blocks icons
                blockStyles += `.${block.key} .block-icon span {mask-image: url(${block.icon});}`;
            }
        }
    }

    const payload = {
        blockList,
        blockListFlattened,
        blockIndexes,
        groupIndexes,
        blockStyles,
    };

    window.blockData = payload;

    return payload;
};

initBlocks();
