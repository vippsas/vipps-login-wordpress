import type { BlockConfig } from './types';

// gets injected from <pluginRoot>/blocks/login-with-vipps-blocks.php. It should follow the interface LoginWithVippsBlockConfig. LP 08.11.2024
// @ts-ignore
export const blockConfig: BlockConfig = injectedLoginWithVippsBlockConfig;
