( function( wp ) {

	const registerBlockType = wp.blocks.registerBlockType;

	const el  = wp.element.createElement;
        const components = wp.components;

        const SelectControl = components.SelectControl;
        const TextControl = components.TextControl;

        const RichText = wp.blockEditor.RichText;

        const useBlockProps = wp.blockEditor.useBlockProps;
        const InspectorControls = wp.blockEditor.InspectorControls;


	registerBlockType( 'login-with-vipps/login-with-vipps-button', {
                apiVersion: 3,
		title: LoginWithVippsBlockConfig['BlockTitle'],
		category: 'widgets',
                icon: el('img', {"class": "vipps-smile vipps-component-icon", "src": LoginWithVippsBlockConfig['vmplogosrc']}),
		supports: {
			html: true,
                        customClassName: true,
		},

                attributes: {
                  application: {
                       default: LoginWithVippsBlockConfig['defaultapp'],
                       type: "string",
                       source: "attribute",
                       selector: "a",
                       attribute: "data-application"
                  },
                  title: {
                       default: LoginWithVippsBlockConfig['DefaultTitle'],
                       type: "string",
                       source: "attribute",
                       selector: "a",
                       attribute: "title" 
                  },
                  prelogo: {
                      default: LoginWithVippsBlockConfig['DefaultTextPrelogo'],
                      type: "text",
                      source: "html",
                      selector: ".prelogo",
                  },
                  postlogo: {
                      default: LoginWithVippsBlockConfig['DefaultTextPostlogo'],
                      type: "text",
                      source: "html",
                      selector: ".postlogo",
                  },
                  loginmethod: {
                      default: LoginWithVippsBlockConfig['loginmethod'] || 'Vipps',
                      type: "string",
                      source: "attribute",
                  }
                 },

	    edit: function( props ) {
                let logo =  LoginWithVippsBlockConfig['loginmethodlogosrc'];
		let attributes = props.attributes;
                let formats = ['core/bold', 'core/italic'];

                // Let the user choose the application. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
                let appOptions =  LoginWithVippsBlockConfig['applications'];
                let current = attributes.application;
                let found=false;
                for(let i=0; i<appOptions.length; i++) {
                   if (current == appOptions[i].value) {
                       found=true; break;
                   } 
                }
                if (!found) appOptions.push({label: current, value: current});

                let backgroundColorClass = props.attributes.loginmethod === 'Vipps' ? 'vipps-background' : 'mobilepay-background';

		return el(
		    'span',
		    { className: 'continue-with-vipps-wrapper inline ' + props.className },
                    el("a", { className: "button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action " + backgroundColorClass + " " + props.className, 
                              title:attributes.title, 'data-application':attributes.application},
                      el(RichText, { tagName: 'span', className:'prelogo', inline:true, allowedFormats:formats, value:attributes.prelogo, onChange: v => props.setAttributes({prelogo: v}) }),
                      el("img", {alt:attributes.title, src: LoginWithVippsBlockConfig['loginmethodlogosrc'], className: 'vipps-block-logo-img' }),
                      el(RichText, { tagName: 'span', className:'postlogo', inline:true, allowedFormats:formats, value:attributes.postlogo, onChange: v => props.setAttributes({postlogo: v}) }),
                    ),

// This is for the menu-bar over the block - not used here
/*
                   el(BlockControls, {}, 
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment  } )
                   ),
*/

                        // This is for left-hand block properties thing
                        el(InspectorControls, {},
                            el(SelectControl, { onChange: x=>props.setAttributes({application: x}) , 
                                label: LoginWithVippsBlockConfig['Application'], value:attributes.application, 
                                options: appOptions,
                                help:  LoginWithVippsBlockConfig['ApplicationsText']  }),
                            el(TextControl, { onChange: x=>props.setAttributes({title: x}) , 
                                label:  LoginWithVippsBlockConfig['Title'] , value:attributes.title,
                                help:  LoginWithVippsBlockConfig['TitleText']   })
                          ),
                        );

            },

	    save: function( props ) {
		var attributes = props.attributes;
                let backgroundColorClass = props.attributes.loginmethod === 'Vipps' ? 'vipps-background' : 'mobilepay-background';
		return el( 'span', { className: 'continue-with-vipps-wrapper inline ' + props.className   },
                    el("a", { className: "button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action " + backgroundColorClass + " " + props.className, 
                              title:attributes.title, 'data-application':attributes.application, href: "javascript: void(0);" },
                    el( RichText.Content, {
                       tagName: 'span',
                       className: 'prelogo',
                       value:attributes.prelogo,
                     }),
                      el("img", {alt:attributes.title, src: LoginWithVippsBlockConfig['loginmethodlogosrc'], className: 'vipps-block-logo-img' }),
                    el( RichText.Content, {
                       tagName: 'span',
                       className: 'postlogo',
                       value: attributes.postlogo
                     }),

                    ));
            },
                
  });

} )(
	window.wp
);
