export interface BlockConfig {
	title: string;
	description: string;
	buttonHtml: string;
}

// Injected config from php. LP 27.11.2024
declare const continueWithVippsCheckoutBlockConfig: BlockConfig;
export const blockConfig = continueWithVippsCheckoutBlockConfig;
