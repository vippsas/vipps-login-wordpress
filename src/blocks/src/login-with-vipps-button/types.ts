import { BlockAttributes } from '@wordpress/blocks';

export interface Option {
	label: string;
	value: string;
}

export interface Attributes extends BlockAttributes {
	application: string;
	language: string;
	variant: string;
	verb: string;
	rounded: boolean;
	branded: boolean;
}
