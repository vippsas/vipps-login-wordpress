import { PropsWithChildren, createContext, useContext, useEffect, useState } from 'react';
import { FieldErrors, Options, OptionValue, SettingsData, VippsLoginReactSettings, gettext } from './lib/wp-data';

interface WPContext {
  getOption: (key: string) => OptionValue;
  setOption: (key: string, value: OptionValue) => void;
  settings: SettingsData;
  isDirty: boolean;
  submitChanges: () => Promise<void>;
}

/** Keep field errors structured so the form can open the relevant tab. */
export class SettingsError extends Error {
  constructor(public errors: FieldErrors) {
    super(Object.values(errors).flat().join('\n'));
  }
}

const WPContext = createContext<WPContext>(null!);

/** Same provider pattern as the payment app, with login's typed option contract. */
export function WPOptionsProvider({ children }: PropsWithChildren) {
  const [settings, setSettings] = useState(VippsLoginReactSettings);
  const [values, setValues] = useState<Options>(settings.values);
  const isDirty = JSON.stringify(values) !== JSON.stringify(settings.values);

  // Tab changes keep the provider mounted. Warn only when leaving the whole page.
  useEffect(() => {
    if (!isDirty) return;
    const beforeUnload = (event: BeforeUnloadEvent) => {
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', beforeUnload);
    return () => window.removeEventListener('beforeunload', beforeUnload);
  }, [isDirty]);

  function setOption(key: string, value: OptionValue) {
    setValues((current) => ({ ...current, [key]: value }));
  }

  async function submitChanges() {
    // Send one snapshot of all editable fields. JSON preserves empty role maps
    // and zero-valued switches, unlike payment's yes/no form encoding.
    const params = new URLSearchParams({
      action: settings.action,
      nonce: settings.nonce,
      values: JSON.stringify(values),
    });
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(settings.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
        credentials: 'same-origin',
        body: params.toString(),
        signal: controller.signal,
      });
      // Expired sessions can return HTML or WordPress's bare -1/0 response.
      const result = await response.json().catch(() => null);
      if (!response.ok || result?.success !== true) {
        throw new SettingsError(result?.data?.errors ?? { save: [gettext('save_failed')] });
      }
      const saved = result.data as SettingsData;
      if (!saved?.values || !Array.isArray(saved.sections) || !saved.nonce) {
        throw new SettingsError({ save: [gettext('save_failed')] });
      }
      // Refresh values AND metadata: new page IDs, choices, labels and nonce may change.
      setSettings(saved);
      setValues(saved.values);
    } catch (error) {
      if (error instanceof SettingsError) throw error;
      throw new SettingsError({ save: [gettext('save_failed')] });
    } finally {
      window.clearTimeout(timeout);
    }
  }

  return (
    <WPContext.Provider value={{ settings, isDirty, getOption: (key) => values[key] ?? '', setOption, submitChanges }}>
      {children}
    </WPContext.Provider>
  );
}

/** Read login settings without importing payment-plugin globals. */
export function useWP() {
  const context = useContext(WPContext);
  if (!context) throw new Error('useWP must be used within a WPOptionsProvider');
  return context;
}
