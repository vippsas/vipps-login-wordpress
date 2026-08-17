import { registerBlockType } from '@wordpress/blocks';

import Edit from './edit';
import metadata from './block.json';
import { blockConfig } from './config';

// @ts-ignore
registerBlockType(metadata.name, {
	edit: Edit,

	// Override metadata. LP 29.11.2024
	title: blockConfig['title'],
	description: blockConfig['description'],
});
