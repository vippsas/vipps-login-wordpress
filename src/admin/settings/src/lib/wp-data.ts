/** PHP owns field definitions, translations and defaults, just as in payments. */
export type OptionValue = string | number | boolean | Record<string, number>;
export type Options = Record<string, OptionValue>;
export type FieldErrors = Record<string, string[]>;

export interface FormField {
  type: 'checkbox' | 'password' | 'text' | 'select' | 'multicheck' | 'description';
  title: string;
  description?: string;
  placeholder?: string;
  default?: OptionValue;
  options?: Record<string, string>;
}

export interface SettingsData {
  values: Options;
  sections: { title: string; fields: Record<string, FormField> }[];
  ajax_url: string;
  action: string;
  nonce: string;
  translations: Record<string, string>;
}

const wpWindow = window as Window & { VippsLoginReactSettings?: SettingsData };
if (!wpWindow.VippsLoginReactSettings) {
  throw new Error('VippsLoginReactSettings not found; load the settings through WordPress.');
}
export const VippsLoginReactSettings = wpWindow.VippsLoginReactSettings;

/** Common UI strings are localized by PHP using the login plugin text domain. */
export function gettext(key: string): string {
  return VippsLoginReactSettings.translations[key] ?? key;
}

/** Change product labels immediately without changing company names or saved data. */
export function loginMethodText(text: string, method: string): string {
  return text.replace(/\bVipps MobilePay\b|\bVipps\b|\bMobilePay\b/g, (name) =>
    name === 'Vipps MobilePay' ? name : method
  );
}
