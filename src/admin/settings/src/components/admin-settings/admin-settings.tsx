import React, { useRef, useState } from 'react';
import { FieldErrors, gettext } from '../../lib/wp-data';
import { useHash } from '../../hooks/use-hash';
import { SettingsError, useWP } from '../../wp-options-provider';
import { WPButton, WPForm } from '../form-elements';
import { NotificationBanner, NotificationBannerProps } from '../notification-banner';
import { OptionsFormField } from '../options-form-fields';
import { SettingsTab, Tabs } from '../tabs';

/** Login-specific sections inside the payment plugin's familiar settings layout. */
export function AdminSettings(): JSX.Element {
  const { settings, isDirty, submitChanges, copyPaymentKeys, getOption } = useWP();
  const [activeTab, setActiveTab] = useHash('general');
  const [isLoading, setIsLoading] = useState(false);
  const [isCopyingKeys, setIsCopyingKeys] = useState(false);
  const saving = useRef(false);
  const [banner, setBanner] = useState<NotificationBannerProps | null>(null);
  const [errors, setErrors] = useState<FieldErrors>({});
  const notice = useRef<HTMLDivElement>(null);
  const fields = Object.assign({}, ...settings.sections.map((section) => section.fields));
  const loginEnabled = [1, '1', true].includes(getOption('use_vipps_login') as number | string | boolean);
  // The login method is held in provider state, so its palette changes immediately.
  const brandClass = getOption('login_method') === 'MobilePay' ? 'MobilePay' : 'Vipps';

  // IDs are stable across language changes; WooCommerce appears only when PHP
  // supplies its integration fields. API keys remain available while login is
  // disabled; behavioral settings are only useful once login is enabled.
  const tabs: SettingsTab[] = [
    { id: 'general', title: gettext('main_options'), fields: loginEnabled ? ['login_method', 'use_vipps_login', 'login_page'] : ['login_method', 'use_vipps_login'] },
  ];
  const wooFields = Object.keys(fields).filter((key) => key.startsWith('woo-'));
  if (loginEnabled && wooFields.length) tabs.push({ id: 'woocommerce', title: gettext('woocommerce'), fields: wooFields });
  tabs.push({ id: 'keys', title: gettext('api_keys'), fields: ['clientid', 'clientsecret', 'redirect-uri'] });
  if (loginEnabled) tabs.push({ id: 'advanced', title: gettext('advanced'), fields: ['required_roles', 'continuepageid'] });
  const selectedTab = tabs.find((tab) => tab.id === activeTab) ?? tabs[0];

  async function handleSaveSettings(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (saving.current) return;
    saving.current = true;
    setIsLoading(true);
    setErrors({});
    setBanner(null);
    try {
      await submitChanges();
      setBanner({ variant: 'success', text: gettext('settings_saved') });
    } catch (error) {
      const fieldErrors = error instanceof SettingsError ? error.errors : { save: [gettext('save_failed')] };
      setErrors(fieldErrors);
      setBanner({ variant: 'error', text: Object.values(fieldErrors).flat() });
      const errorTab = tabs.find((tab) => tab.fields.some((field) => fieldErrors[field]));
      if (errorTab) setActiveTab(errorTab.id);
    } finally {
      saving.current = false;
      setIsLoading(false);
      // Announce the result and make errors discoverable to keyboard users.
      window.requestAnimationFrame(() => notice.current?.focus());
    }
  }

  async function handleCopyPaymentKeys() {
    setIsCopyingKeys(true);
    setBanner(null);
    setErrors({});
    try {
      await copyPaymentKeys();
      setBanner({ variant: 'success', text: gettext('settings_saved') });
    } catch (error) {
      const fieldErrors = error instanceof SettingsError ? error.errors : { keys: [gettext('copy_keys_failed')] };
      setErrors(fieldErrors);
      setBanner({ variant: 'error', text: Object.values(fieldErrors).flat() });
    } finally {
      setIsCopyingKeys(false);
    }
  }

  const keysAreEmpty = !getOption('clientid') && !getOption('clientsecret');

  return (
    <div className={`vipps-settings-shell ${brandClass}`}>
      <header className="vipps-settings-header">
        <h1>{gettext('company_name')}</h1>
        <p className="vipps-login-subtitle">{gettext('page_title')}</p>
      </header>
      {banner && (
        <div className="vipps-settings-notices" role="status" tabIndex={-1} ref={notice}>
          <NotificationBanner {...banner} />
        </div>
      )}
      <WPForm onSubmit={handleSaveSettings} className="vippsAdminSettings" aria-busy={isLoading}
        onChange={() => { setBanner(null); setErrors({}); }}>
        <div className="vipps-settings-layout">
          <nav className="vipps-settings-navigation" aria-label={gettext('page_title')}>
            <Tabs tabs={tabs} activeTab={selectedTab.id} onTabChange={setActiveTab} />
          </nav>
          <div className="vipps-settings-content">
            <section className="vipps-settings-panel" id="vipps-login-tab-panel" role="tabpanel"
              aria-labelledby={`vipps-login-tab-${selectedTab.id}`} tabIndex={0}>
              <div>
                <h2>{selectedTab.title}</h2>
                {/* Keep the fields rendered normally while the save request runs;
                    the save guard prevents duplicate submissions. */}
                <fieldset className="vipps-login-fields">
                  {selectedTab.fields.filter((name) => fields[name]).map((name) => (
                    <OptionsFormField key={name} name={name} field={fields[name]} errors={errors[name]} />
                  ))}
                  {selectedTab.id === 'keys' && settings.payment_keys_available && keysAreEmpty && (
                    <WPButton type="button" variant="secondary" isLoading={isCopyingKeys} onClick={handleCopyPaymentKeys}>
                      {gettext('copy_keys')}
                    </WPButton>
                  )}
                </fieldset>
              </div>
            </section>
            <div className="vipps-mobilepay-react-save-section">
              {isDirty && <span className="vipps-login-unsaved">{gettext('unsaved_changes')}</span>}
              <WPButton type="submit" variant="primary" isLoading={isLoading}>{gettext('save_changes')}</WPButton>
            </div>
          </div>
        </div>
      </WPForm>
    </div>
  );
}
