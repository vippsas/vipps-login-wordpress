import type { LoginWithVippsBlockConfig } from './types';

// const injectedLoginWithVippsBlockConfig gets injected from <pluginRoot>/blocks/login-with-vipps-blocks.php. It should follow the interface LoginWithVippsBlockConfig. LP 08.11.2024
// @ts-ignore
export const blockConfig: LoginWithVippsBlockConfig = injectedLoginWithVippsBlockConfig;
