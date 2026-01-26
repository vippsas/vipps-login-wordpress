import type { BlockAttributes } from '@wordpress/blocks';

export interface Option {
	label: string;
	value: string;
}

export interface EditAttributes extends BlockAttributes {
	application: string;
	title: string;
	preLogo: string;
	postLogo: string;
	loginMethod: string;
}

export interface BlockConfig {
	title: string;
	iconSrc: string;
	defaultApp: string;
	defaultTitle: string;
	defaultTextPreLogo: string;
	defaultTextPostLogo: string;
	loginMethod: string;
	loginMethodLogoSrc: string;
	applications: Option[];
	applicationsText: string;
}
