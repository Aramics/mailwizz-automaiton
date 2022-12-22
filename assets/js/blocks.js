//Interval period
const INTERVALS = [
	{
		key: "s",
		label: "Second(s)",
	},
	{
		key: "i",
		label: "Minute(s)",
	},
	{
		key: "h",
		label: "Hour(s)",
	},
	{
		key: "d",
		label: "Day(s)",
	},
	{
		key: "w",
		label: "Week(s)",
	},
	{
		key: "m",
		label: "Month(s)",
	},
	{
		key: "y",
		label: "Year(s)",
	},
];

const OPERATORS = [
	{
		key: ">",
		label: "Greater than",
	},
	{
		key: "<",
		label: "Less than",
	},
	{
		key: "=",
		label: "Equals",
	},
];

//Block groups
/*const BLOCK_GROUPS = {
	TRIGGER: "triggers",
	LOGIC: "logic",
	ACTION: "action",
};

//Know block types . This should sync with backend, or perhaps come from backend.
//Leaving or copying it here for clarity.
const BLOCK_TYPES = {
	LIST_SUBSCRIPTION: "list-subscription",
	LIST_UNSUBSCRIPTION: "list-unsubscription",
	WEBHOOK: "webhook",
	SPECIFIC_DATE: "specific-date",
	SUBSCRIBER_ADDED_DATE: "subscriber-added-date",
	INTERVAL: "interval",
	OPEN_EMAIL: "open-email",
	CLICK_URL: "click-url",
	REPLY_EMAIL: "reply-email",
	WAIT: "wait",
	SEND_EMAIL: "send-email",
	RUN_CAMPAIGN: "run-campaign",
	MOVE_SUBSCRIBER: "move-subscriber",
	COPY_SUBSCRIBER: "copy-subscriber",
	UPDATE_SUBSCRIBER: "update-subscriber",
	REMOVE_SUBSCRIBER: "remove-subscriber",
	WEBHOOK_ACTION: "webhook-action",
	STOP: "stop",
	YES: "yes",
	NO: "no",
	SUBSCRIBER_COUNT: "subscriber-count",
};*/

///////////// Block structure defination /////////////

/**
 * @componentVariable : The block structure support use of component field name in the summary text of the block.
 * i.e for block "open-email", we have "campaign-list" as a component for the block.
 * "campaign-list" component has an input field named "campaign",
 * thus we can reference this field using $campaign ins the "summary" ppt of the block.
 */

const commonActions = (group = "") => [
	{
		key: BLOCK_TYPES.OPEN_EMAIL,
		title: "Open email",
		description: "Open a campaign or an email",
		icon: ASSETS_PATH + "/images/open-email.svg",
		shape: "diamond",
		summary: "$campaign opened",
		components:
			group == BLOCK_GROUPS.TRIGGER
				? [{"campaign-list": {name: "trigger_value"}}]
				: ["campaign-list"],
	},
	{
		key: BLOCK_TYPES.CLICK_URL,
		title: "Click URL",
		description: "Click on certain or any URL in a campaign or an email",
		shape: "diamond",
		icon: ASSETS_PATH + "/images/click-url.svg",
		summary: "$url in $campaign-with-url Clicked",
		components: [
			{
				"campaign-list": {
					name:
						group == BLOCK_GROUPS.TRIGGER
							? "trigger_value"
							: "campaign-with-url",
					label: "Select an email",
				},
			},
			{
				"url-list":
					group == BLOCK_GROUPS.TRIGGER
						? {name: "trigger_value"}
						: {},
			},
		],
	},
	{
		key: BLOCK_TYPES.REPLY_EMAIL,
		title: "Reply email",
		description: "Respond to a certain email or campaign",
		shape: "diamond",
		icon: ASSETS_PATH + "/images/reply-email.svg",
		summary: "$campaign replied to",
		components:
			group == BLOCK_GROUPS.TRIGGER
				? [{"campaign-list": {name: "trigger_value"}}]
				: ["campaign-list"],
	},
];

const blockList = Object.freeze([
	{
		key: BLOCK_GROUPS.TRIGGER,
		title: "Triggers",
		blocks: [
			{
				key: BLOCK_TYPES.LIST_SUBSCRIPTION,
				title: "List subscription",
				description: "Welcome automation when a subscriber join.",
				icon: ASSETS_PATH + "/images/subscribe.svg",
				summary: "On subscription to $mail-list",
				components: [{"mail-list": {label: "", name: "trigger_value"}}],
			},
			{
				key: BLOCK_TYPES.LIST_UNSUBSCRIPTION,
				title: "List unsubscribe",
				description: "Say goodbye to a customer.",
				icon: ASSETS_PATH + "/images/unsubscribe.svg",
				summary: "When unsubscribe from $mail-list",
				components: [{"mail-list": {label: "", name: "trigger_value"}}],
			},
			{
				key: BLOCK_TYPES.WEBHOOK,
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
							name: "trigger_value",
							items: [
								{label: "GET", key: "get"},
								{label: "POST", key: "post"},
							],
						},
					},
				],
			},
			{
				key: BLOCK_TYPES.SPECIFIC_DATE,
				title: "Specific date time",
				description:
					"Start an automation based on an individual date, like an appointment.",
				icon: ASSETS_PATH + "/images/date-time.svg",
				summary: "$date",
				components: [
					{input: {type: "datetime-local", name: "trigger_value"}},
				],
			},

			{
				key: BLOCK_TYPES.SUBSCRIBER_ADDED_DATE,
				title: "Subscriber added date",
				description:
					"Run action for subscribers base on the date they joined the list.",
				icon: ASSETS_PATH + "/images/date.svg",
				summary: "$mail-list",
				components: [{"mail-list": {name: "trigger_value"}}],
			},

			{
				key: BLOCK_TYPES.INTERVAL,
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
							name: "trigger_value",
						},
					},
				],
			},

			...commonActions(BLOCK_GROUPS.TRIGGER),
		],
	},

	{
		key: BLOCK_GROUPS.ACTION,
		title: "Actions",
		blocks: [
			{
				key: BLOCK_TYPES.WAIT,
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
				key: BLOCK_TYPES.SEND_EMAIL,
				title: "Send an email",
				description:
					"Send a campaign/autoresponder email content to only the current subscriber.",
				summary: "to the subscriber - $campaign",
				icon: ASSETS_PATH + "/images/send-email.svg",
				components: ["campaign-list"],
			},
			{
				key: BLOCK_TYPES.RUN_CAMPAIGN,
				title: "Run a campaign",
				description:
					"Run a campaign to all the campaign list subscribers.",
				summary: "Start/Run $campaign",
				icon: ASSETS_PATH + "/images/send-campaign.svg",
				components: ["campaign-list"],
			},
			{
				key: BLOCK_TYPES.MOVE_SUBSCRIBER,
				title: "Move subscriber",
				description: "Move the subscriber to another list",
				summary: "to $mail-list",
				icon: ASSETS_PATH + "/images/move.svg",
				components: [{"mail-list": {label: "To which mail list ?"}}],
			},
			{
				key: BLOCK_TYPES.COPY_SUBSCRIBER,
				title: "Copy subscriber",
				description: "Copy the subscriber to another list",
				summary: "to $mail-list",
				icon: ASSETS_PATH + "/images/copy.svg",
				components: [{"mail-list": {label: "To which mail list ?"}}],
			},
			{
				key: BLOCK_TYPES.UPDATE_SUBSCRIBER,
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
				key: BLOCK_TYPES.REMOVE_SUBSCRIBER,
				title: "Remove subscriber",
				description: "Remove the subscriber from the list",
				icon: ASSETS_PATH + "/images/remove.svg",
				summary: "from the current list",
			},
			{
				key: BLOCK_TYPES.WEBHOOK_ACTION,
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
								{label: "GET", key: "get"},
								{label: "POST", key: "post"},
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
				key: BLOCK_TYPES.STOP,
				title: "Stop this automation",
				description: "Stop and disable the automation",
				icon: ASSETS_PATH + "/images/stop.svg",
				summary: "end",
			},
		],
	},

	{
		key: BLOCK_GROUPS.LOGIC,
		title: "Logics",
		blocks: [
			{
				key: BLOCK_TYPES.YES,
				title: "Yes",
				description: "Evaluate to yes or true or 1",
				icon: ASSETS_PATH + "/images/yes.svg",
				shape: "circle",
			},
			{
				key: BLOCK_TYPES.NO,
				title: "No",
				description: "Evaluate to no or false or 0 or null",
				icon: ASSETS_PATH + "/images/no.svg",
				shape: "circle",
			},
			{
				key: BLOCK_TYPES.SUBSCRIBER_COUNT,
				title: "Subscribers",
				description:
					"Evaluate to arithmetic on an audience. i.e subscriberCount equals 100",
				icon: ASSETS_PATH + "/images/subscribers.svg",
				shape: "diamond",
				summary: "$operator $count for $mail-list",
				components: [
					{"mail-list": {label: "When subscribers in ?"}},
					{operators: {name: "operator"}},
					{input: {type: "number", name: "count"}},
				],
			},
			{
				key: BLOCK_TYPES.SPECIFIC_DATE,
				title: "Date",
				description: "Date arithmetic",
				icon: ASSETS_PATH + "/images/calender.svg",
				summary: "$operator $date",
				shape: "diamond",
				components: [
					{operators: {name: "operator"}},
					{input: {type: "date", name: "date"}},
				],
			},
			...commonActions(),
		],
	},
]);

const getBlock = (groupId, blockId) => {
	let data = window.blockData;
	let blockIndex = data.blockIndexes[groupId][blockId];
	let groupIndex = data.groupIndexes[groupId];
	console.log(groupIndex, blockIndex);
	return data.blockList[groupIndex]["blocks"][blockIndex];
};

//generate style for the blocks icons
const getBlocksStyle = () => {
	return window.blockData.blockStyles;
};

//generate all necessary block data and attach to window
const initBlocks = () => {
	let blockStyles = "";
	let blockIndexes = {};
	let groupIndexes = {};
	let blockListFlattened = [];

	for (let index = 0; index < blockList.length; index++) {
		const group = blockList[index];
		groupIndexes[group.key] = index;
		blockIndexes[group.key] = [];

		for (let j = 0; j < group.blocks.length; j++) {
			const block = group.blocks[j];

			blockListFlattened.push(block);
			blockIndexes[group.key][block.key] = j;

			if (block?.icon) {
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
