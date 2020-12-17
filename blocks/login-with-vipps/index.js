( function( wp ) {
//        console.log("This is %j", LoginWithVippsBlockConfig);


	/**
	 * Registers a new block provided a unique name and an object defining its behavior.
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/#registering-a-block
	 */
	var registerBlockType = wp.blocks.registerBlockType;

        var components = wp.components;

        var SelectControl = components.SelectControl;

        var useBlockProps = wp.blockEditor.useBlockProps;
        var RichText = wp.blockEditor.RichText;
        var AlignmentToolbar = wp.blockEditor.AlignmentToolbar;
        var MediaUpload = wp.blockEditor.MediaUpload;

        var BlockControls = wp.blockEditor.BlockControls;
        var InspectorControls = wp.blockEditor.InspectorControls;

	/**
	 * Returns a new element of given type. Element is an abstraction layer atop React.
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/packages/packages-element/
	 */
	var el = wp.element.createElement;
	/**
	 * Retrieves the translation of text.
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/packages/packages-i18n/
	 */
	var __ = wp.i18n.__;

	/**
	 * Every block starts by registering a new block type definition.
	 * @see https://wordpress.org/gutenberg/handbook/designers-developers/developers/block-api/#registering-a-block
	 */
	registerBlockType( 'login-with-vipps/login-with-vipps', {
		/**
		 * This is the display title for your block, which can be translated with `i18n` functions.
		 * The block inserter will show this name.
		 */
		title: __( 'Log in with Vipps', 'login-with-vipps' ),

		/**
		 * Blocks are grouped into categories to help users browse and discover them.
		 * The categories provided by core are `common`, `embed`, `formatting`, `layout` and `widgets`.
		 */
		category: 'widgets',

		/**
		 * Optional block extended support features.
		 */
		supports: {
			html: false,
                        anchor: true,
                        align:true,
                        customClassName: true,
		},

                attributes: {
                  application: {
                       type: "string",
                       source: "attribute",
                       selector: "a",
                       attribute: "'data-application'" // 'data-application' 
                  },
                  title: {
                       type: "string",
                       source: "attribute",
                       selector: "a",
                       attribute: "title" 
                  },
                  prelogo: {
                      type: "array",
                  },
                  postlogo: {
                      type: "array",
                  },
                  alignment: {
                        type: "string"
                  }
                 },

	    edit: function( props ) {
                let logo =  LoginWithVippsBlockConfig['logosrc'];
		let attributes = props.attributes;

                const onChangeAlignment = function(newalignment) {
                  props.setAttributes( { alignment: newalignment } );
                }

		return el(
		    'span',
		    { className: 'continue-with-vipps-wrapper inline ' + props.className },
                    el("a", { className: "button vipps-orange vipps-button continue-with-vipps " + props.className, title:attributes.title, 'data-application':attributes.application},
                      el(RichText, { tagName: 'span', className:'prelogo', inline:true,value:attributes.prelogo,placeholder: "Fortsett med", onChange: v => props.setAttributes({prelogo: v}) }),
                      el("img", {alt:attributes.title, src: LoginWithVippsBlockConfig['logosrc'] }),
                      el(RichText, { tagName: 'span', className:'postlogo', inline:true, value:attributes.postlogo,placeholder: " !", onChange: v => props.setAttributes({postlogo: v}) }),
                    ),

/*
// This is for the menu-bar over the block
                   el(BlockControls, {}, 
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment  } )
                   ),
// This is for left-hand block properties thing
                   el(InspectorControls, {},
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment } ),
                        el(SelectControl, { onChange: x=>props.setAttributes({application: x}) , label: "Application", value:attributes.application, options: [ { label: "Foo", value:"foo" }, { label: "Bar", value: "bar" } ] }),
*/
                 );

            },

	    save: function( props ) {
		var attributes = props.attributes;
		return el( 'span', { className: 'continue-with-vipps-wrapper inline ' + props.className, onClick: e => { e.preventDefault(); alert("foon!"); }  },
                    el("a", { "data-test": "hest",className: "button vipps-orange vipps-button continue-with-vipps", title:attributes.title, 'data-application':attributes.application,
                              href: "javascript: login_with_vipps(" + (attributes.application ? JSON.stringify(attributes.application) : "")  + ");"},
                    el( RichText.Content, {
                       tagName: 'span',
                       className: 'prelogo',
                       value:attributes.prelogo,
                     }),
                      el("img", {alt:attributes.title, src: LoginWithVippsBlockConfig['logosrc'] }),
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
