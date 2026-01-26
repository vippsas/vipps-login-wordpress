import { __ } from '@wordpress/i18n';

import type { Option } from './types';

export interface BlockConfig {
	title: string;
	iconSrc: string;
	defaultApp: string;
	loginMethod: string;
	applications: Option[];
	applicationsText: string;
	languages: Option[];
	storeLanguage: string;
	variants: Option[];
	verbs: Option[];
}

// gets injected from <pluginRoot>/blocks/login-with-vipps-blocks.php. It should follow the interface LoginWithVippsBlockConfig. LP 08.11.2024
// @ts-ignore
export const blockConfig: BlockConfig = injectedLoginWithVippsBlockConfig;
