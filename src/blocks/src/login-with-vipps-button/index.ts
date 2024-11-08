import { registerBlockType } from '@wordpress/blocks';

import './style.scss';

import Edit from './edit';
import save from './save';
import metadata from './block.json';


type blockConfig = {
	title: string,
	icon: string
};

// loginBlockConfig gets injected from <pluginRoot>/blocks/login-with-vipps-blocks.php 

// @ts-ignore
console.log(loginBlockConfig);


registerBlockType( metadata.name as never, {
	// @ts-ignore
	title: loginBlockConfig.title,
	edit: Edit,
	save,
} );
