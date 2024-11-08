// @ts-nocheck

import { registerBlockType } from '@wordpress/blocks';

import './style.css';

import Edit, { blockConfig } from './edit';
import save from './save';
import metadata from './block.json';

registerBlockType( metadata.name, {
	// Override dynamic metadata. LP 08.11.2024
	title: blockConfig.title,
	icon: (
		<img
			className={
				blockConfig.title.includes( 'Vipps' )
					? 'vipps-component-icon vipps-smile'
					: 'vipps-component-icon mobilepay-mark'
			}
			src={ blockConfig.iconSrc }
			alt={ blockConfig.title + ' icon' }
		/>
	),

	// Set attribute defaults. LP 08.11.2024
	attributes: {
		application: { default: blockConfig.defaultApp },
		title: { default: blockConfig.defaultTitle },
		preLogo: { default: blockConfig.defaultTextPreLogo },
		postLogo: { default: blockConfig.defaultTextPostLogo },
		loginMethod: { default: blockConfig.loginMethod },
	},

	edit: Edit,
	save,
} );
