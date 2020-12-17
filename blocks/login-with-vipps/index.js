( function( wp ) {

/*
    public function continue_with_vipps_shortcode($atts,$content,$tag) {
        $args = shortcode_atts(array('application'=>'wordpress', 'text'=>__('Continue with', 'login-with-vipps')), $atts);
        $text = esc_html($args['text']);
        $application = $args['application'];
        ob_start();
        ?>
            <span class='continue-with-vipps-wrapper inline'>
            <?php $this->login_button_html($text, $application); ?>
            </span>
            <?php
        return ob_get_clean();
    }

    public function login_button_html($text, $application) {
        $logo = plugins_url('img/vipps_logo_negativ_rgb_transparent.png',__FILE__);
        ob_start();
        ?>
            <a href='javascript:login_with_vipps("<?php echo $application; ?>");' class="button vipps-orange vipps-button continue-with-vipps" title="<?php echo $text; ?>"><?php echo $text;?> <img
            alt="<?php _e('Log in without password using Vipps', 'login-with-vipps'); ?>" src="<?php echo $logo; ?>">!</a>
            <?php
        echo apply_filters('continue_with_vipps_login_button_html', ob_get_clean(), $application, $text);
    }
*/

        console.log("This is %j", LoginWithVippsBlockConfig);


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
                       // source: "attribute",
                       // selector: "a",
                       // attribute: "bleh" // data-application 
                  },

		  title: {
			type: "array",
			source: "children",
			selector: ".callout-title"
	              },
                  mediaID: {
                        type: "number"
                  },
                  mediaURL: {
                        type: "string",
		        source: "attribute",
		        selector: "img",
		        attribute: "src"
	          },
                  body: {
                        type: "array",
                        source: "children",
                        selector: ".callout-body"
                  },
                  alignment: {
                        type: "string"
                  }
                 },

	    example: {
		attributes: {
		    title: __( 'Chocolate Chip Cookies', 'gutenberg-examples' ),
		    mediaURL:
		    'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f1/2ChocolateChipCookies.jpg/320px-2ChocolateChipCookies.jpg',
		    ingredients: [
			__( 'flour', 'gutenberg-examples' ),
			__( 'sugar', 'gutenberg-examples' ),
			__( 'chocolate', 'gutenberg-examples' ),
			'💖',
		    ],
		    instructions: [
			__( 'Mix', 'gutenberg-examples' ),
			__( 'Bake', 'gutenberg-examples' ),
			__( 'Enjoy', 'gutenberg-examples' ),
		    ],
		},
	    },

	    edit: function( props ) {
		var attributes = props.attributes;

		var onSelectImage = function( media ) {
		    return props.setAttributes( {
			mediaURL: media.url,
			mediaID: media.id,
		    } );
		};

                var onChangeAlignment = function(newalignment) {
                  props.setAttributes( { alignment: newalignment } );
                }

		return el(
		    'div',
		    { className: props.className },

// This is for the menu-bar over the block
                   el(BlockControls, {}, 
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment  } )
                   ),
// This is for left-hand block properties thing
                   el(InspectorControls, {},
                        el(AlignmentToolbar,{ value: attributes.alignment, onChange: onChangeAlignment } ),
                        el(SelectControl, { onChange: x=>props.setAttributes({application: x}) , label: "Application", value:attributes.application, options: [ { label: "Foo", value:"foo" }, { label: "Bar", value: "bar" } ] }),
                    ),


		    el( RichText, {
			tagName: 'h2',
			inline: true,
			placeholder: __(
			    'Write Recipe title…',
			    'gutenberg-examples'
			),
			value: attributes.title,
			onChange: function( value ) {
			    props.setAttributes( { title: value } );
			},
		    } ),
		    el(
			'div',
			{ className: 'recipe-image' },
			el( MediaUpload, {
			    onSelect: onSelectImage,
			    allowedTypes: 'image',
			    value: attributes.mediaID,
			    render: function( obj ) {
				return el(
				    components.Button,
				    {
					className: attributes.mediaID
					    ? 'image-button'
					    : 'button button-large',
					onClick: obj.open,
				    },
				    ! attributes.mediaID
					? __( 'Upload Image', 'gutenberg-examples' )
					: el( 'img', { src: attributes.mediaURL } )
				);
			    },
			} )
		    ),
		    el( 'h3', {}, __( 'Ingredients', 'gutenberg-examples' ) ),
		    el( RichText, {
			tagName: 'ul',
			multiline: 'li',
			placeholder: __(
			    'Write a list of ingredients…',
			    'gutenberg-examples'
			),
			value: attributes.ingredients,
			onChange: function( value ) {
			    props.setAttributes( { ingredients: value } );
			},
			className: 'ingredients',
		    } ),
		    el( 'h3', {}, __( 'Instructions', 'gutenberg-examples' ) ),
		    el( RichText, {
			tagName: 'div',
			inline: false,
			placeholder: __(
			    'Write instructions…',
			    'gutenberg-examples'
			),
			value: attributes.instructions,
			onChange: function( value ) {
			    props.setAttributes( { instructions: value } );
			},
		    } )
		);
	    },
	    save: function( props ) {
		var attributes = props.attributes;

		return el(
		    'div',
		    { className: props.className },
		    el( RichText.Content, {
			tagName: 'h2',
			value: attributes.title,
		    } ),
		    attributes.mediaURL &&
			el(
			    'div',
			    { className: 'recipe-image' },
			    el( 'img', { src: attributes.mediaURL } )
			),
		    el( 'h3', {}, __( 'Ingredients', 'gutenberg-examples' ) ),
		    el( RichText.Content, {
			tagName: 'ul',
			className: 'ingredients',
			value: attributes.ingredients,
		    } ),
		    el( 'h3', {}, __( 'Instructions', 'gutenberg-examples' ) ),
		    el( RichText.Content, {
			tagName: 'div',
			className: 'steps',
			value: attributes.instructions,
		    } )
		);
	    },
	} );

} )(
	window.wp
);
