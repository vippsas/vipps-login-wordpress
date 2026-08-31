export interface BlockConfig {
	title: string;
	description: string;
	buttonHtml: string;
}

// Injected config from php. LP 27.11.2024
declare const continueWithVippsCartBlockConfig: BlockConfig;
export const blockConfig = continueWithVippsCartBlockConfig;
