document.addEventListener("DOMContentLoaded", function () {
	let draggingBlock;
	let canvas = document.getElementById("canvas");
	const canvasStore = Alpine.store("canvas");
	const TYPE_TRIGGER = "triggers";
	const TYPE_LOGIC = "logic";
	///////////////////////Flowy Callbacks///////////////////////
	/**
	 * Function that gets triggered when a block snaps with another one, i.e new block addition
	 *
	 * @param {object} block The dragged/grabbed block - DOM newly added block on the canvas
	 * @param {object} first
	 * @returns
	 */
	function onSnap(block, first, parent) {
		if (!validateBlock(block, first, parent)) return false;

		console.log({block, first, parent});
		if (document.querySelector(".left-card-closed")) {
			console.log("returning false");
			return false;
		}

		//clean off unwanted html contents
		cleanBlockDom(block);

		//open right card if necessary
		openRightCard(block);

		//do validation with block arrangments
		return true;
	}

	/**
	 * Function that gets triggered when a block is dragged
	 *
	 * @param {object} block
	 */
	function onDrag(block) {
		console.log("draging");
		block.classList.add("block-disabled");
		draggingBlock = block;
		closeRightCard();
		scrollToElementBottom();
	}

	/**
	 * Function that gets triggered when a block is released
	 */
	function onRelease() {
		if (draggingBlock) {
			console.log("released");
			draggingBlock.classList.remove("block-disabled");
		}

		setTimeout(() => {
			scrollToElementBottom();
		}, 100);
	}

	function onRearrange(block, parent) {
		console.log("REARRANGE");
		// When a block is rearranged
		//return true;
	}

	//block validation rules
	function validateBlock(block, first, parent) {
		const blockGroup = block.querySelector('[name="blockelemgroup"]').value;
		const blockType = block.querySelector('[name="blockelemtype"]').value;

		if (!parent) {
			//only allow triggers as first blocks
			if (blockGroup != TYPE_TRIGGER) {
				setTimeout(() => {
					flowy.deleteBlocks();
				}, 0);

				notify("Only triggers can used as the first block.");

				return false;
			}
		}

		if (parent) {
			const parentGroup = parent.querySelector(
				'[name="blockelemgroup"]'
			).value;
			const parentType = parent.querySelector(
				'[name="blockelemtype"]'
			).value;

			if (parentType == blockType) {
				notify("Cant have same block as a direct child");
				return false;
			}

			if (blockGroup == TYPE_TRIGGER) {
				//only on trigger on the canvas
				notify("Canvas already have a trigger");
				return false;
			}

			//logics

			const yesNo = ["yes", "no"];

			//only logic should follow logics other than yes or no
			if (parentGroup == TYPE_LOGIC) {
				if (!yesNo.includes(parentType) && blockGroup != TYPE_LOGIC) {
					notify("Only logic should be folled by a logics block");
					return false;
				}
			}

			if (blockGroup == TYPE_LOGIC) {
				//Non logic blocks should not be followed by yes or no
				if (parentGroup != TYPE_LOGIC && yesNo.includes(blockType)) {
					notify("Non logic blocks cant be followed by yes or no");
					return false;
				}

				//A  "yes" or "no" logic block should not be followed by "yes" or "no" block
				if (yesNo.includes(parentType) && yesNo.includes(blockType)) {
					notify(
						'A  "yes" or "no" logic block should not be followed by same'
					);
					return false;
				}

				//A logic block other than "yes" and "no" should be followed by "yes" or "no"
				if (
					parentGroup == TYPE_LOGIC &&
					!yesNo.includes(parentType) &&
					!yesNo.includes(blockType)
				) {
					notify(
						'A logic block other than "yes" and "no" should be followed by "yes" or "no"'
					);
					return false;
				}
			}
		}

		return true;
	}

	/////////////////////////// Utils ///////////////////////
	/**
	 * Bind an event on a selector of multiple value i.e class selector
	 *
	 * @param {string} type Type of event
	 * @param {callback} listener Callback to run on event occurence
	 * @param {bool} capture Capture event or not
	 * @param {string} selector The element selector
	 */
	function addCustomEventListener(selector, type, listener, capture) {
		let nodes = document.querySelectorAll(selector);

		for (let i = 0; i < nodes.length; i++) {
			nodes[i].addEventListener(type, listener, capture);
		}
	}

	//Listen to only click event without drag. A drag element wont trigger callback
	function addClickEventOnly(element, callback, threshold = 10) {
		let drag = 0;
		element.addEventListener("mousedown", () => (drag = 0));
		element.addEventListener("mousemove", () => drag++);
		element.addEventListener("mouseup", (e) => {
			if (drag < threshold) callback(e);
		});
	}

	/** Scroll to bottom of the element */
	function scrollToElementBottom(elementId = "canvas") {
		let element = document.getElementById(elementId);
		let height = element.scrollHeight;
		element.scrollTop = height;
		element.focus();
	}

	////////////////////// Block Components Generation ////////////////////////////
	/**
	 * Generate required components for each block.
	 *
	 * Below structure is arrived for each block having components
	 *
	 * <div id="list-subscription">
	 *    <x-mail-list data-label="Custom label select mail"></x-mail-list>
	 *    ...
	 * </div>
	 */
	const COMPONENT_SELECTOR = "x-component";
	const COMPONENT_NAME_PREFIX = "x-";
	const COMPONENT_DATA_PREFIX = "data-x-";

	function generateBlockComponents() {
		//generate template tag for each blocks

		const blocks = window.blockData.blockListFlattened;

		for (let index = 0; index < blocks.length; index++) {
			const block = blocks[index];

			if (document.getElementById(block.key)) continue;

			//create wrapper div
			const wrapper = document.createElement("div");
			wrapper.setAttribute("class", "component-wrapper");
			wrapper.setAttribute("id", block.key);

			if (!block.components) continue;

			for (let i = 0; i < block.components.length; i++) {
				let cObject = block.components[i];
				let cKey = cObject;
				let cData = {};

				if (typeof cObject == "object") {
					cKey = Object.keys(cObject)[0];
					cData = Object.values(cObject)[0];
				}

				//create the component tag
				let component = document.createElement(`x-${cKey}`);

				//add custom attributes data to the component
				for (const attribute in cData) {
					let value = cData[attribute];
					if (typeof value == "object") value = JSON.stringify(value);

					component.setAttribute(
						`${COMPONENT_DATA_PREFIX}${attribute}`,
						value
					);
				}

				wrapper.append(component);
			}

			document.getElementById("components").append(wrapper);
		}
	}

	/**
	 * Render each custom component with Alpine useable props.
	 *
	 * This render the generated components tags (by generateBlockComponents) using the respective template(<template ..>) content node.
	 * It also provide props interface for binding with alpine . i.e x-data="{ ...ComponentData(), ...$el.parentElement.props }"
	 *
	 * Thus allowing props inheritance from parenet blocks through data-x- attribute html property.
	 *
	 */
	function renderComponents() {
		// Render custom templates
		document
			.querySelectorAll(`template[${COMPONENT_SELECTOR}]`)
			.forEach((component) => {
				//component name i.e x-list
				const componentName = `${COMPONENT_NAME_PREFIX}${component.getAttribute(
					COMPONENT_SELECTOR
				)}`;

				//create the comoponent class
				class Component extends HTMLElement {
					constructor() {
						super();

						//Attach props and make attribute to be available to apline.js using $el.parentElement.props.
						this.props = this.dataProps();
					}

					connectedCallback() {
						//get template tag (<template ...>) content and set as the component content
						let childNode = component.content.cloneNode(true);
						this.append(childNode);
					}

					// Makes attribute to be available to apline.js using $el.parentElement.props
					// Interface all data-x- (i.e COMPONENT_DATA_PREFIX ) attribute as prop object on the custom component.
					// Usable by child node through alpine data x-data={...$el.parentElement.props};
					dataProps() {
						const attributes = this.getAttributeNames();
						const data = {};
						attributes.forEach((attribute) => {
							if (attribute.startsWith(COMPONENT_DATA_PREFIX)) {
								let prop = attribute.replace(
									COMPONENT_DATA_PREFIX,
									""
								);
								let val = this.getAttribute(`${attribute}`);
								if (val.startsWith("[{") && val.endsWith("}]"))
									try {
										val = JSON.parse(val);
									} catch (error) {}
								data[prop] = val;
							}
						});

						//check if has child node
						let innerContent = this.innerHTML.trim();
						if (innerContent.length) {
							data["children"] = innerContent;
							this.innerHTML = "";
						}

						return data;
					}
				}

				//add component to window custom element registry.
				customElements.define(componentName, Component);
			});
	}

	// clean blocks off alpine.js and unused code/node/selector
	function cleanBlockDom(block) {
		let grab = block.querySelector(".grabme");
		grab.parentNode.removeChild(grab);

		let blockContent = block.querySelector(".block-content");
		blockContent.parentNode.removeChild(blockContent);

		let id = Date.now();
		block.setAttribute("data-id", id);

		block.removeAttribute("x-bind:x-data");
		block.removeAttribute("x-bind:class");

		// this prevent alpine.js from running on the block before content clean up below
		block.setAttribute("x-ignore", true);

		// remove all alpine.js dep. This allow light canvas DOM and storage size
		setTimeout(() => {
			const regex =
				/x-([a-zA-Z0-9:;\.\s\(\)\-\,]*)="([a-zA-Z0-9:;\.\s\(\)\-\,]*)(")/gi;
			let b = document.querySelector(`[data-id="${id}"]`);
			b.outerHTML = b.outerHTML.replace(regex, "");
		}, 100);
	}

	////////////////////// Block value helpers ////////////////////////////

	//sync properties value with canvase selected block
	function setBlockPropertiesValue(event) {
		const block = canvasStore.selectedBlock;
		const activeBlockDOM = document.querySelector(".selectedblock");
		const blockPptDOM = document.getElementById(block.key);

		if (!block || !activeBlockDOM || !blockPptDOM) {
			console.warn("Missing value.");
			return;
		}

		const inputs = blockPptDOM.querySelectorAll(
			"[name]:not([data-exclude])"
		);
		for (let index = 0; index < inputs.length; index++) {
			const input = inputs[index];

			if (!input || input.hasAttribute("data-exclude")) continue;

			//the name of the input
			const inputName = input.getAttribute("name");

			//select input from the active block in canvas
			const blockInput = activeBlockDOM.querySelector(
				'[name="' + inputName + '"]'
			);

			//get helper title text for canvas block
			let title = "";
			let valueOption;

			if (input.tagName == "SELECT") {
				valueOption = input.querySelector(
					"option[value='" + input.value + "']"
				);

				if (valueOption && valueOption.hasAttribute("title"))
					title = valueOption.getAttribute("title");
			} else {
				if (input.hasAttribute("title"))
					title = input.getAttribute("title");
			}

			//syc input value and title
			if (blockInput) {
				if (event.type == "change") {
					blockInput.value = input.value;
					blockInput.setAttribute("title", title);
				} else {
					//set ppt value with value existing on the block.
					input.value = blockInput.value;

					//physically selection option with the right value for this non change event case
					if (input.tagName == "SELECT") {
						setTimeout(() => {
							valueOption = input.querySelector(
								'option[value="' + blockInput.value + '"]'
							);
							if (valueOption) valueOption.selected = true;
						}, 100);
					}
				}
			} else {
				//create element, set tag into block and add value
				const element = document.createElement("input");
				element.setAttribute("type", "hidden");
				element.setAttribute("data-type", "ppt");
				element.setAttribute("name", inputName);
				element.setAttribute("value", input.value);
				if (title.length) element.setAttribute("title", title);
				activeBlockDOM.append(element);
			}

			//events
			if (
				event.type != "change" &&
				input.hasAttribute("data-onmanualupdate")
			) {
				let callbackString = input.getAttribute("data-onmanualupdate");
				let callback = window[callbackString];

				if (typeof callback === "function") callback(input);
			}
		}

		setBlockPropertiesValueForPairs(blockPptDOM, activeBlockDOM, event);

		setBlockSummaryTextFromValues();
	}

	//custom value sync for pair input component.
	function setBlockPropertiesValueForPairs(
		blockPptDOM,
		activeBlockDOM,
		event
	) {
		if (!activeBlockDOM || !blockPptDOM) return;

		let fieldSelector = 'input[name="fields[]"]';
		let valueSelector = 'input[name="values[]"]';

		let pptFields = blockPptDOM.querySelectorAll(".pairs " + fieldSelector);
		let pptValues = blockPptDOM.querySelectorAll(".pairs " + valueSelector);

		let fields = activeBlockDOM.querySelectorAll(fieldSelector);
		let values = activeBlockDOM.querySelectorAll(valueSelector);

		if (!fields?.length && !pptFields?.length) return;

		if (event.type == "change") {
			//get data from property block to active selected block in canvas.

			//clear active block fields and value
			if (fields)
				fields.forEach((element) => {
					element.remove();
				});

			if (values)
				values.forEach((element) => {
					element.remove();
				});

			if (
				pptFields &&
				pptValues &&
				pptValues.length == pptFields.length
			) {
				for (let index = 0; index < pptFields.length; index++) {
					//create input in canvas active block
					let field = pptFields[index].cloneNode();
					let value = pptValues[index].cloneNode();

					field.setAttribute("type", "hidden");
					value.setAttribute("type", "hidden");

					activeBlockDOM.append(field);
					activeBlockDOM.append(value);
				}
			}
		} else {
			//clear ppt block, get active block values and add to ppt block in pairs

			//clear block
			blockPptDOM.querySelector(".pairs").replaceChildren();

			if (fields && values && values.length == fields.length) {
				//simulate add button click for desire number of field
				for (let index = 0; index < fields.length; index++) {
					blockPptDOM.querySelector(".add-pair").click();
				}

				//reselect newly added inputs fields
				pptFields = blockPptDOM.querySelectorAll(
					".pairs " + fieldSelector
				);
				pptValues = blockPptDOM.querySelectorAll(
					".pairs " + valueSelector
				);

				//now let set respect field and value for the generated inputs
				if (
					pptFields &&
					pptValues &&
					pptFields.length == pptValues.length
				) {
					for (let index = 0; index < pptFields.length; index++) {
						//create input in canvas active block
						pptFields[index].value = fields[index].value;
						pptValues[index].value = values[index].value;
					}
				}
			}
		}
	}

	//generate summary text for the canvas blocks
	function setBlockSummaryTextFromValues() {
		const block = canvasStore.selectedBlock;
		const activeBlockDOM = document.querySelector(".selectedblock");
		let blockTitle = "";

		if (!block || !activeBlockDOM) return;

		const activeBlockInfoDOM =
			activeBlockDOM.querySelector(".block-canvas-info");

		if (!activeBlockInfoDOM) return;

		let summary = block.summary;
		console.log(summary);
		if (typeof summary === "undefined") return;

		const inputs = activeBlockDOM.querySelectorAll("[data-type='ppt']");

		for (let index = 0; index < inputs.length; index++) {
			const input = inputs[index];
			const title = input.title || input.value;
			blockTitle = `${blockTitle} ${title}`;
			summary = summary.replace(
				"$" + input.name,
				`<span>${title}</span>`
			);
		}

		activeBlockInfoDOM.innerHTML = summary;
		activeBlockDOM.setAttribute("title", activeBlockInfoDOM.textContent);
	}

	/////////////////////// Canvas import and export //////////////////

	const initFlow = (automationId) => {
		//get flow for the automation from server
	};

	const startFlow = (output) => {
		try {
			output = atob(output);
			output.html = DOMPurify.sanitize(output, {
				USE_PROFILES: {html: true},
			});
			flowy.import(output);

			//select first block
		} catch (error) {
			alert("Error importing flow: " + error.message);
		}
	};

	// @TODO: see template saving scheme.
	const saveFlow = () => {
		try {
			deselectBlock();

			let output = flowy.output();

			// sanitize html off XSS
			output.html = DOMPurify.sanitize(output.html, {
				USE_PROFILES: {html: true},
			});

			// encode for more save transfer
			output = compressData(output);
			console.log(output);

			//send to server
		} catch (error) {
			alert("Error exporting flow: " + error.message);
		}
	};

	// @TODO: see template saving scheme.
	const importFlow = () => {
		//import the text to convas
		let inputBox = document.getElementById("import-box"); //get input content;
		if (!inputBox) return;

		let content = inputBox.value.trim();

		try {
			if (!content.length) throw new Error("Content can not be empty");

			//uncompress/decode and parse to object
			content = decompressData(content);

			// sanitize html off XSS
			content.html = DOMPurify.sanitize(content.html, {
				USE_PROFILES: {html: true},
			});
			flowy.deleteBlocks();
			flowy.import(content);

			//close modal
			document.body.click();
		} catch (error) {
			alert(
				"Invalid content. Make sure the content is pasted without alteration:" +
					error.message
			);
		}
	};

	// @TODO: see template saving scheme.
	const exportFlow = () => {
		deselectBlock();

		let exportBox = document.getElementById("export-box");

		if (!exportBox) return;

		exportBox.value = "";

		let output = flowy.output();

		if (output) {
			// sanitize html
			output.html = DOMPurify.sanitize(output.html, {
				USE_PROFILES: {html: true},
			});

			// show madal with text box, paste the output into textarea
			exportBox.value = compressData(output);
		}
	};

	//copyToclipboard
	const copyToClipboard = async (e) => {
		let textArea = document.getElementById(e.target.dataset.target);

		if (navigator.clipboard) {
			navigator.clipboard.writeText(textArea.value).then(
				function () {
					notify("Copied!");
				},
				function (err) {
					notify("Could not copy text: " + err.message, "error");
				}
			);
		} else {
			textArea.focus();
			textArea.select();

			try {
				if (document.execCommand("copy")) {
					notify("Copied!");
				} else {
					throw new Error("Error copying text");
				}
				textArea.setSelectionRange(0, 0);
			} catch (err) {
				notify(err.message, "error");
			}
		}
	};

	/**
	 * Compress data for reduced storage and network request
	 * @param {object} dataObj
	 * @returns {string}
	 */
	function compressData(dataObj) {
		return LZString.compressToEncodedURIComponent(JSON.stringify(dataObj));
	}

	/**
	 * Decompress data and parse to object
	 * @param {string} dataString
	 * @returns {object}
	 */
	function decompressData(dataString) {
		return JSON.parse(
			LZString.decompressFromEncodedURIComponent(dataString)
		);
	}

	function notify(note, type = "error") {
		console.log(note, type);
	}

	/////////////////////// General Helpers //////////////////

	const setBlockCustomStyles = () => {
		let style = document.createElement("style");
		style.textContent = getBlocksStyle();
		document.getElementsByTagName("head")[0].append(style);
	};

	const removeAllBlocks = () => {
		if (confirm("Are you sure you want to delete all blocks ?")) {
			flowy.deleteBlocks();
			deselectBlock();
		}
	};

	const deselectBlock = () => {
		const block = canvasStore.selectedBlock;

		let lastAddedBlock = document.querySelector("#canvas .selectedblock");
		if (lastAddedBlock) {
			lastAddedBlock.classList.remove("selectedblock");
		}

		if (block) {
			canvasStore.setSelectedBlock(null);

			const blockPptDOM = document.getElementById(block.key);
			if (blockPptDOM) {
				blockPptDOM.classList.remove("active");
			}
		}
	};

	const openRightCard = (blockDom = null) => {
		if (blockDom) {
			deselectBlock();

			blockDom.classList.add("selectedblock");
			console.log(blockDom);
			let blockId = blockDom.querySelector(".blockelemtype").value;
			let blockGroup = blockDom.querySelector(".blockelemgroup").value;

			const block = getBlock(blockGroup, blockId);
			console.log("open content for ", blockId, blockGroup, block);
			canvasStore.setSelectedBlock(block);

			const blockPptDOM = document.getElementById(block.key);

			if (blockPptDOM) {
				blockPptDOM.classList.add("active");
				setBlockPropertiesValue(block);
			} else {
				setBlockSummaryTextFromValues();
			}
		}

		document.getElementById("right-card").classList.add("expanded");
	};

	const closeRightCard = () => {
		let rightCard = document.getElementById("right-card");
		rightCard.focus();
		setTimeout(() => {
			rightCard.classList.remove("expanded");
			deselectBlock();
		}, 100);
	};

	const toggleLeftCard = (show = "toggle") => {
		let store = Alpine.store("navs");

		if (show == "toggle") {
			store.toggle("leftnav");
			return;
		}

		store.set("leftnav", show);
	};

	const previewMode = () => {
		let store = Alpine.store("navs");
		if (store.preview) {
			toggleLeftCard(true);
			openRightCard();
			Alpine.store("zoom").in(1);
			store.toggle("preview");
		} else {
			toggleLeftCard(false);
			closeRightCard();
			Alpine.store("zoom").out(0.5);
			store.toggle("preview");
		}

		Alpine.store("blocklist").clearSearch();
	};

	//initate the flow builder
	flowy(canvas, onDrag, onRelease, onSnap, onRearrange, 20, 50);

	//generate and render components
	generateBlockComponents();
	renderComponents();

	//attached custom block styles to head
	setBlockCustomStyles();

	//////////////////////// DOM bindings ////////////////////
	//addCustomEventListener( "#canvas .blockelem.block", "click", openRightCard );
	setTimeout(() => {
		addCustomEventListener("#close", "click", closeRightCard);
		addCustomEventListener("#preview", "click", previewMode);
		addCustomEventListener("#close-left-card", "click", () => {
			toggleLeftCard("toggle");
		});
		addCustomEventListener("#edit", "click", () => {
			deselectBlock();
			openRightCard();
		});
		addCustomEventListener(".deselect", "click", deselectBlock);
		addCustomEventListener("#importModalBtn", "click", () => {
			document.getElementById("import-box").value = "";
		});
		addCustomEventListener("#import", "click", importFlow);
		addCustomEventListener("#export", "click", exportFlow);
		addCustomEventListener(".copy-to-clipboard", "click", copyToClipboard);
		addCustomEventListener("#save", "click", () => {
			saveFlow();
		});
		addCustomEventListener("#removeblocks", "click", removeAllBlocks);
		addCustomEventListener(
			"#components",
			"change",
			setBlockPropertiesValue
		);
		addClickEventOnly(
			canvas,
			(event) => {
				let targetBlock = event.target.closest(
					".blockelem.block:not(.selectedblock)"
				);
				if (targetBlock) {
					openRightCard(targetBlock);
				}
			},
			1
		);

		//confirm before leaving page
		window.onbeforeunload = function (e) {
			return "Do you want to exit this page?";
		};

		//open prop for automation info edit
		openRightCard();
	}, 0);
});
