<?php
$assets_dir = AssetsUrl::base('ext-automation', false, 'customer');
?>
<!DOCTYPE html>
<html x-data :class="{dark: $store.darkMode.on,'preview-mode': $store.navs.preview}" lang="en">

<head>
    <!-- Primary Meta Tags -->
    <title><?= $automation->title ?> - <?= $this->extension->t('automation'); ?></title>
    <meta charset="utf-8">
    <meta name="title" content="Flowy - The simple flowchart engine">
    <meta name="description" content="Flowy is a minimal javascript library to create flowcharts. Use it for automation software, mind mapping tools, programming platforms, and more. Made by Alyssa X.">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://alyssax.com/x/flowy">
    <meta property="og:title" content="Flowy - The simple flowchart engine">
    <meta property="og:description" content="Flowy is a minimal javascript library to create flowcharts. Use it for automation software, mind mapping tools, programming platforms, and more. Made by Alyssa X.">
    <meta property="og:image" content="https://alyssax.com/x/assets/images/meta.png">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://alyssax.com/x/flowy">
    <meta property="twitter:site" content="alyssaxuu">
    <meta property="twitter:title" content="Flowy - The simple flowchart engine">
    <meta property="twitter:description" content="Flowy is a minimal javascript library to create flowcharts. Use it for automation software, mind mapping tools, programming platforms, and more. Made by Alyssa X.">
    <meta property="twitter:image" content="https://alyssax.com/x/assets/images/meta.png">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    <!-- css style sheets -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,500,700&amp;display=swap" rel="stylesheet">
    <link href="<?= $assets_dir; ?>/css/styles.css" rel="stylesheet" type="text/css">
    <link href="<?= $assets_dir; ?>/css/flowy.min.css" rel="stylesheet" type="text/css">


    <!-- libraries -->
    <script src="<?= $assets_dir; ?>/js/dompurify/purify.min.js"></script>
    <script src="<?= $assets_dir; ?>/js/alpine.min.js" defer=""></script>
    <script src="<?= $assets_dir; ?>/js/flowy.min.js"></script>
    <script language="javascript" src="<?= $assets_dir; ?>/js/lz-string.min.js"></script>

    <!-- internal -->
    <script>
        //asset path for loading images and other static resources
        const ASSETS_PATH = "<?= $assets_dir; ?>";

        //webhook url for the automation. This is used for webhook trigger block component
        const AUTOMATION_WEBHOOK_URL =
            "<?= $this->createUrl('automations/' . $automation->automation_id . '/webhook'); ?>";

        /**
         * Automation data object.
         * Should have following properties:
         * { title: "Demo automation title", canvas_data: {blocks: [...], blocks_html_compressed: ... } }
         */
        const AUTOMATION_DETAILS = <?= json_encode($automation->attributes) ?>;
        const AUTOMATION_SAVE_URL = window.location.href;
        const MAIL_LISTS_FETCH_URL =
            "<?= $this->createUrl('automations/lists', ['id' => $automation->automation_id]); ?>";
        const CAMPAIGNS_FETCH_URL =
            "<?= $this->createUrl('automations/campaigns', ['id' => $automation->automation_id]); ?>";
        const CAMPAIGN_TEMPLATE_URLS_FETCH_URL =
            "<?= $this->createUrl('automations/campaign_urls', ['id' => $automation->automation_id]); ?>";

        const BLOCK_TYPES = <?= AutomationExtBlockTypes::getConstantsJson() ?>;
        const BLOCK_GROUPS = <?= AutomationExtBlockGroups::getConstantsJson() ?>;
    </script>



    <script src="<?= $assets_dir; ?>/js/blocks.js"></script>
    <script src="<?= $assets_dir; ?>/js/main.js"></script>
    <script src="<?= $assets_dir; ?>/js/store.js"></script>


</head>

<body :class="{'left-card-closed': !$store.navs.leftnav}">
    <header id="navigation" class="flex">
        <div id="leftside">
            <div id="details">
                <a href="javascript:window.history.back();" id="back"><img src="<?= $assets_dir; ?>/images/arrow.svg" alt="back"></a>
                <div id="names">
                    <p id="title" x-text="$store.automation.title"></p>
                    <p id="subtitle">Marketing automation</p>
                </div>
            </div>
        </div>
        <div id="buttonsright" class="flex">
            <button @click="$store.darkMode.toggle()" class="theme-switch">
                <img :src="$store.darkMode.on ? '<?= $assets_dir; ?>/images/light-mode.svg':'<?= $assets_dir; ?>/images/dark-mode.svg'" alt="Dark mode" title="Dark mode" />
            </button>
            <button id="preview" class="btn btn-default">
                <img :src="!$store.navs.preview ? '<?= $assets_dir; ?>/images/eye.svg':'<?= $assets_dir; ?>/images/eyeblue.svg'" alt="Toggle preview" title="Toggle preview" />
            </button>

            <button x-show="!$store.navs.preview" id="edit" class="btn btn-default">Edit</button>
            <button x-show="!$store.navs.preview" id="save" class="btn btn-primary">Save</button>
        </div>
        </div>
    </header>

    <main class="wrapper">
        <div id="left-card">
            <div id="close-left-card">
                <img src="<?= $assets_dir; ?>/images/closeleft.svg" alt="close left">
            </div>

            <div class="left-card-body" x-show="$store.navs.leftnav">
                <p class="title">Blocks</p>
                <div id="search">
                    <img src="<?= $assets_dir; ?>/images/search.svg" alt="search">
                    <input type="text" placeholder="Search blocks" x-model.debounce.300ms="$store.blocklist.search">
                </div>


                <div class="nav">
                    <template x-for="(item,index) in $store.blocklist.getItems()">
                        <div x-bind:id="item.key" class="side" :class="$store.blocklist.current == item.key ? 'nav-active':'nav-disabled'" x-text="item.title" @click="$store.blocklist.setCurrent(item.key)">
                        </div>
                    </template>
                </div>


                <!-- Block list -->
                <div id="blocklist">
                    <template x-for="item in $store.blocklist.getItems()">
                        <div x-show="item.key == $store.blocklist.current" :id="item.key+'-blocklist'">

                            <template x-for="blockItem in item.blocks">

                                <div class="blockelem create-flowy noselect" x-bind:x-data="$store.blocklist.stringify" x-bind:class="`${blockItem.key} ${blockItem.group ?? item.key} ${blockItem.shape ?? ''}`">

                                    <input type="hidden" name='blockelemtype' class="blockelemtype" x-bind:value="blockItem.key">
                                    <input type="hidden" name='blockelemgroup' class="blockelemgroup" x-bind:value="blockItem.group">

                                    <div class="grabme">
                                        <img src="<?= $assets_dir; ?>/images/grabme.svg">
                                    </div>

                                    <div class="block-content">
                                        <div class="block-icon">
                                            <span></span>
                                        </div>
                                        <div class="block-text">
                                            <p class="block-title" x-text="blockItem.title"></p>
                                            <p class="block-desc" x-text="blockItem.description"></p>
                                        </div>
                                    </div>

                                    <div class="block-canvas-content">
                                        <div class="block-canvas-left">
                                            <div class="block-icon">
                                                <span></span>
                                            </div>
                                            <p class="block-canvas-name" x-text="blockItem.title"></p>
                                        </div>
                                        <div class='block-canvas-right'><img src="<?= $assets_dir; ?>/images/more.svg">
                                        </div>
                                        <div class='block-canvas-div'></div>
                                        <div class='block-canvas-info'>...</div>
                                    </div>

                                </div>

                                <!--<div x-html="getBlockHtml(blockItem,item.key)"></div>-->
                            </template>

                        </div>
                    </template>
                    <div x-show="!$store.blocklist.getItems().length" class="empty-search">
                        No result found for <em x-text="$store.blocklist.search"></em>
                    </div>
                </div>
            </div>
        </div>

        <div id="canvas" x-ignore>
        </div>

        <!-- Block contents -->
        <div id="right-card" class="expanded flex flex-col">

            <div class="right-card-header">
                <div class="flex">
                    <p class="title">Properties</p>
                    <div id="close" class="close">
                        <img src="<?= $assets_dir; ?>/images/close.svg" alt="close">
                    </div>

                </div>
                <div class="divider"></div>
            </div>

            <div class="right-card-body">

                <!-- automation property-->
                <div id="automation-ppt" x-show="!$store.canvas.selectedBlock">
                    <div class="nav flex">
                        <div class="nav-active">Settings</div>
                        <div>Insight</div>
                        <div>Statistic</div>
                    </div>
                    <div id="proplist">
                        <p class="input-label">Name</p>
                        <input aria-label="automation title" name="title" type="text" x-model="$store.automation.title" />
                    </div>
                </div>

                <div x-show="$store.canvas.selectedBlock">

                    <h3 x-text="$store.canvas.selectedBlock?.title"></h3>

                    <!-- list component-->
                    <template x-component="list">
                        <div x-data="{ ...$el.parentElement.props }" x-on:global-lists-load.window="if(typeof global_list_key !== 'undefined') items=$event.detail[global_list_key]">
                            <label x-text="label"></label>
                            <select :name="name" onchange="onListHasChange(this)" data-onmanualupdate="onListHasChange">
                                <template x-for="item in items">
                                    <option :value="item.key" x-text="item.label" x-bind:title="item.label"></option>
                                </template>
                            </select>
                        </div>
                    </template>

                    <!-- mail list component-->
                    <template x-component="mail-list">
                        <x-list x-data="{ ...MailListComponent(), ...$el.parentElement.props,...{global_list_key:'mail_list'} }">
                        </x-list>
                    </template>

                    <!-- campaign list component -->
                    <template x-component="campaign-list">
                        <x-list x-data="{ ...CampaignListComponent(), ...$el.parentElement.props }"></x-list>
                    </template>

                    <!-- url list component -->
                    <template x-component="url-list">
                        <x-list x-data="{ ...UrlListComponent(), ...$el.parentElement.props }"></x-list>
                    </template>

                    <!-- interval component -->
                    <template x-component="interval">
                        <div class="flex" x-data="{ ...{intervals: INTERVALS, label:''}, ...$el.parentElement.props }">

                            <label>
                                <span x-text="label"></span>
                                <input type="number" step="1.0" :name="interval_name" min="1" value="1" />
                            </label>

                            <select :name="unit_name">
                                <template x-for="item in intervals">
                                    <option :value="item.key" x-text="item.label" x-bind:title="item.label"></option>
                                </template>
                            </select>
                        </div>
                    </template>

                    <!-- arithmetic component -->
                    <template x-component="operators">
                        <div x-data="{...{operators: OPERATORS}, ...$el.parentElement.props}">
                            <select :name="name">
                                <template x-for="item in operators">
                                    <option :value="item.key" x-text="item.label" x-bind:title="item.label"></option>
                                </template>
                            </select>
                        </div>
                    </template>

                    <!-- input component -->
                    <template x-component="input">
                        <div x-data="{ 
                                ...{type: 'text',label: '',value:'', disabled: false, readonly:false}, 
                                ...$el.parentElement.props 
                            }">
                            <label>
                                <span x-text="label"></span>
                                <input :type="type" :value="value" x-bind:title="value" :name="name" :disabled="disabled" :readonly="readonly" />
                            </label>
                        </div>
                    </template>

                    <!-- modal component-->
                    <template x-component="modal">
                        <div x-data="{ ...useModal($el.parentElement.props) }" class="modal" x-show="showModal">

                            <div class="modal-container" @click.outside="closeModal">
                                <div class="modal-header flex" x-show="header">
                                    <h3 x-text="title"></h3>
                                    <button class="close" @click="closeModal">
                                        <img src="<?= $assets_dir; ?>/images/close.svg" alt="close">
                                    </button>
                                </div>

                                <div class="modal-body" x-html="children"></div>
                            </div>
                        </div>
                    </template>


                    <!-- input repeat component -->
                    <template x-component="input-pair-repeat">
                        <div class="pairs-wrapper" x-data="{ ...InputPairRepeat(), ...$el.parentElement.props}">

                            <div class="mt-10 form-group">
                                <label x-text="label"></label>
                                <p class="small" x-html="help"></p>
                            </div>

                            <template id="inputReps">
                                <div class="flex mt-10">
                                    <input type="text" value="" name="fields[]" placeholder="key" data-exclude />
                                    <input type="text" value="" name="values[]" placeholder="value" data-exclude />
                                    <button class="btn btn-danger remove-pair" @click="pairRemove">-</button>
                                </div>
                            </template>

                            <div class="pairs"></div>

                            <div class="flex-center mt-10">
                                <button class="btn btn-primary mt-10 add-pair" @click="pairAdd">+</button>
                            </div>
                        </div>
                    </template>


                    <!-- render all dynamic block components -->
                    <div id="components"></div>
                </div>
            </div>

            <div class="right-card-footer" x-show="!$store.canvas.selectedBlock">
                <div class="divider spacer"></div>
                <div class="flex">
                    <button id="importModalBtn" type="button" class="btn btn-default deselect" @click="$store.navs.toggle('import')">
                        Import
                    </button>
                    <button id="export" type="button" class="btn btn-default deselect" @click="$store.navs.toggle('export')">
                        Export
                    </button>
                </div>
                <button id="removeblocks" type="button" class="btn btn-danger">
                    <div>Delete all blocks</div>
                </button>
            </div>
        </div>
    </main>




    <!-- modals -->
    <div class="modals">

        <!-- import modal -->
        <x-modal data-x-modal='import' data-x-title="Import from text">
            <div>
                <textarea id="import-box" aria-label="flow content" placeholder="Paste your canvas content here"></textarea>
                <button id="import" type="button" class="btn btn-primary">
                    Import
                </button>
            </div>
        </x-modal>

        <!-- export modal -->
        <x-modal data-x-modal='export' data-x-title="Export">
            <textarea id="export-box" aria-label="Copy content" readonly></textarea>
            <button type="button" class="btn btn-primary copy-to-clipboard mt-10" data-target="export-box">
                Copy to clipboard
            </button>
        </x-modal>

    </div>

</body>

</html>