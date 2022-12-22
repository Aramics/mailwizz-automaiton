document.addEventListener( 'alpine:init', async () => {

    //block management store
    Alpine.store( 'blocklist', {
        current: 'triggers',
        search: '',
        items: window.blockData.blockList,
        setCurrent ( current ) {
            this.current = current
        },
        clearSearch () {
            this.search = '';
        },
        getItems () {

            const search = this.search?.trim();
            const items = this.items;

            if ( !search || !search.length ) {

                this.current = this.items[0].key;
                return items;
            }

            let newItems = [];

            for ( let index = 0; index < items.length; index++ ) {

                const item = { ...items[index] };
                const blocks = item.blocks.filter( function ( block ) {
                    return block.title.concat( " ", block.description ).toLowerCase().includes( search.toLowerCase() )
                } )

                if ( blocks.length ) {
                    item.blocks = blocks;
                    newItems.push( item );
                }
            }

            this.current = newItems[0]?.key;

            return newItems;
        },
        stringify () {
            if ( this.item && this.blockItem ) //in a loop from template rendering.
                return JSON.stringify( { blockItem: { ...this.blockItem, ...{ group: this.item.key } } } )
        }
    } );

    //canvas state management
    Alpine.store( 'canvas', {
        selectedBlock: null,
        setSelectedBlock ( selectedBlock ) {
            this.selectedBlock = selectedBlock
        }
    } )

    //theme
    Alpine.store( 'darkMode', {
        init () {
            this.on = sessionStorage.getItem( "darkMode" ) == "true" || window.matchMedia( '(prefers-color-scheme: dark)' ).matches
        },

        on: false,

        toggle () {
            this.on = !this.on
            sessionStorage.setItem( "darkMode", this.on )
        }
    } )

    //nav store
    Alpine.store( 'navs', {
        leftnav: true,
        preview: false,
        import: false,
        export: false,
        set ( key, val ) {
            this[key] = val;
        },
        toggle ( nav ) {
            console.log( nav )
            this[nav] = !this[nav];
        }
    } );

    //zooming state
    Alpine.store( 'zoom', {
        active: true,
        zoomLevels: [0.5, 0.75, 0.85, 0.9, 1],
        currentZoomLevel: 1,
        zoomIndex: 4,
        in ( val = '' ) {
            if ( this.zoomIndex < this.zoomLevels.length - 1 ) {
                this.zoom( this.zoomIndex + 1, val );
            }
        },
        out ( val = '' ) {

            if ( this.zoomIndex > 0 ) {
                this.zoom( this.zoomIndex - 1, val );
            }
        },
        zoom ( zoomIndex = 4, val = '' ) {
            this.currentZoomLevel = val === '' ? this.zoomLevels[zoomIndex] : val;
            this.zoomIndex = zoomIndex;
            document.getElementById( 'canvas' ).style['transform'] = `scale(${this.currentZoomLevel})`;
        }
    } );


    //automation object
    Alpine.store( 'automation', AUTOMATION_DETAILS );


    //Campaign URL component store
    Alpine.store( 'url', {
        label: 'Select an URL',
        name: 'url',
        items: [],
        async fetchCampaignUrls ( campaignId ) {
            //make api call,
            let resp = await automationHttpRequest( `${CAMPAIGN_TEMPLATE_URLS_FETCH_URL}?campaign_id=${campaignId}`, 'GET' );

            if ( resp.success )
                this.items = resp.data;

            //trigger change on component
            setTimeout( () => {
                componentShouldUpdate();
            }, 100 );
        },
        init () {

            this.items = [{ 'label': 'loading...', key: '0' }];

            document.addEventListener( 'campaign-change', ( e ) => {

                this.fetchCampaignUrls( e.detail.campaignId );
            }, false );
        }
    } );

    //One time loading of list component items such as campaign, email list e.t.c to reduce network call
    Alpine.store( 'global_lists', {
        mail_list: [],
        campaigns: [],

        async init () {
            let resp = await automationHttpRequest( MAIL_LISTS_FETCH_URL, 'GET' );
            if ( resp.success )
                this.mail_list = resp.data;


            resp = await automationHttpRequest( CAMPAIGNS_FETCH_URL, 'GET' );
            if ( resp.success )
                this.campaigns = resp.data;

            let event = new CustomEvent( "global-lists-load", {
                detail: {
                    mail_list: this.mail_list,
                    campaigns: this.campaigns
                }
            } );

            //notify components to update with respective "global_list_key"
            window.dispatchEvent( event );
        }
    } );

} );

//usemodal
function useModal ( props ) {
    const store = Alpine.store( 'navs' );
    console.log( store[props.modal], props.modal )
    return {
        ...{
            header: true,
            title: '',
        },
        ...props,
        ...{
            showModal: () => store[props.modal],
            closeModal: () => store.set( props.modal, false )
        }
    }
}

//trigger an event on components block to update value and summary
function componentShouldUpdate ( event = '' ) {
    document.getElementById( "components" ).dispatchEvent( ( new CustomEvent( event ) ) );
}

//called on every chage to list component
function onListHasChange ( selectElement ) {

    //this allow for dependent list on campaign -> url selection.
    if ( selectElement.name == "campaign-with-url" ) {
        campaignSelectionChange( selectElement )
    }
}

function campaignSelectionChange ( element ) {
    console.log( "campaing change" )

    let campaignId = element.querySelector( "option:checked" ).value;
    let urlId = document.querySelector( '.selectedblock [name="url"]' );

    const event = new CustomEvent( 'campaign-change', { detail: { campaignId, urlId } } );
    // Dispatch the event.
    document.dispatchEvent( event );
}

function MailListComponent () {
    return {
        label: 'Select an audience',
        name: 'mail-list',
        global_list_key: 'mail_list',
        items: [],
    };
}

function CampaignListComponent () {
    return {
        label: 'Select',
        name: 'campaign',
        global_list_key: 'campaigns', //
        items: []
    };
}

function UrlListComponent () {
    return Alpine.store( 'url' );
}
//Input pari repeat component
function InputPairRepeat () {

    return {
        label: '',
        pairRemove ( event ) {
            event.target.parentElement.remove();
            componentShouldUpdate( 'change' );
        },
        pairAdd ( event ) {
            let parent = event.target.closest( '.pairs-wrapper' );
            parent.querySelector( '.pairs' ).insertAdjacentHTML( 'beforeend', parent.querySelector( "#inputReps" ).innerHTML )
        }
    }
}