document.addEventListener( 'alpine:init', () => {

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
    Alpine.store( 'automation', {
        title: 'Your automation title',
    } );


    //Campaign URL component store
    Alpine.store( 'url', {
        label: 'Select an URL',
        name: 'url',
        items: [],
        fetchCampaignUrls ( uid, urlId ) {
            //make api call,
            //set the data
            this.items = [
                { 'label': '---', key: '0' },
                { 'label': 'Get 50 off' + uid, key: '1' + uid },
                { 'label': 'Get 20 off' + uid, key: '2' + uid },
                { 'label': 'Get 30 off' + uid, key: '3' + uid }
            ];

            //trigger change on component
            setTimeout( () => {
                componentShouldUpdate();
            }, 100 );
        },
        init () {

            document.addEventListener( 'campaign-change', ( e ) => {

                this.fetchCampaignUrls( e.detail.campaignId, e.detail.urlId );
            }, false );
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

    if ( selectElement.name == "campaign-with-url" ) {
        campaignSelectionChange( selectElement )
    }
}

function MailListComponent () {
    return {
        label: 'Select an audience',
        name: 'mail-list',
        items: [
            { 'label': '---', key: '0' },
            { 'label': 'Demo list', key: '14543234' },
            { 'label': 'Demo list2', key: '2454973234' },
            { 'label': 'Demo list3', key: '332543234' }
        ]
    }
}

function CampaignListComponent () {
    return {
        label: '',
        name: 'campaign',
        items: [
            { 'label': '---', key: '0' },
            { 'label': 'Welcome email', key: '1' },
            { 'label': 'Welcome email2', key: '2' },
            { 'label': 'Good bye', key: '3' }
        ]
    }
}

function UrlListComponent () {
    return Alpine.store( 'url' );
}

function campaignSelectionChange ( element ) {
    console.log( "campaing change" )

    let campaignId = element.querySelector( "option:checked" ).value;
    let urlId = document.querySelector( '.selectedblock [name="url"]' );

    const event = new CustomEvent( 'campaign-change', { detail: { campaignId, urlId } } );
    // Dispatch the event.
    document.dispatchEvent( event );
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