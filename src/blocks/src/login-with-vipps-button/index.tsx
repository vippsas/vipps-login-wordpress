import { registerBlockType } from '@wordpress/blocks';

import './style.css';
import Edit from './edit';
import metadata from './block.json';
import { blockConfig } from './blockConfig';

// @ts-ignore
registerBlockType( metadata.name, {
	// Override metadata. LP 08.11.2024
	title: blockConfig.title,
	icon: (
		<img
			className={
				'block-editor-block-icon has-colors vipps-smile vipps-component-icon'
			}
			src={ blockConfig.iconSrc }
			alt={ blockConfig.title + ' icon' }
		/>
	),


	edit: Edit,
} );
