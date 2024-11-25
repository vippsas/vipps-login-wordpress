import type { BlockAttributes } from '@wordpress/blocks';

interface Application {
	label: string;
	value: string;
}

export interface LoginWithVippsBlockAttributes extends BlockAttributes {
	application: string;
	title: string;
	preLogo: string;
	postLogo: string;
	loginMethod: string;
}

export interface LoginWithVippsBlockConfig {
	title: string;
	iconSrc: string;
	defaultApp: string;
	defaultTitle: string;
	defaultTextPreLogo: string;
	defaultTextPostLogo: string;
	loginMethod: string;
	loginMethodLogoSrc: string;
	applications: Application[];
	applicationsText: string;
}
