import { FormField, loginMethodText } from '../lib/wp-data';
import { useWP } from '../wp-options-provider';
import { WPFormField, WPInput, WPLabel, WPOption, WPSelect, WPSwitchToggle } from './form-elements';
import { UnsafeHtmlText } from './unsafe-html-text';

interface Props { name: string; field: FormField; errors?: string[] }

/** Render the PHP field schema with the same form primitives as payment settings. */
export function OptionsFormField({ name, field, errors = [] }: Props): JSX.Element {
  const { getOption, setOption } = useWP();
  const value = getOption(name);
  const method = String(getOption('login_method'));
  const title = loginMethodText(field.title, method);
  const description = loginMethodText(field.description ?? '', method);
  const id = `vipps-login-${name}`;
  const describedBy = [description ? `${id}-description` : '', errors.length ? `${id}-error` : ''].filter(Boolean).join(' ') || undefined;
  const scalarValue = typeof value === 'object' ? '' : String(value);

  // Description HTML comes exclusively from PHP's sanitized field definitions.
  const help = description && (
    <div id={`${id}-description`} className="vipps-mobilepay-react-secondary-label">
      <UnsafeHtmlText className="vipps-mobilepay-react-field-description" htmlString={description} />
    </div>
  );
  const error = errors.length > 0 && (
    <div id={`${id}-error`} className="vipps-mobilepay-react-field-error">{errors.map((message, index) => <div key={index}>{message}</div>)}</div>
  );

  if (field.type === 'checkbox') {
    return (
      <WPFormField className="vipps-mobilepay-react-switch-field">
        <WPSwitchToggle id={id} name={name} checked={value === 1 || value === '1' || value === true ? 'yes' : 'no'}
          aria-describedby={describedBy} aria-invalid={errors.length > 0}
          onChange={(checked) => setOption(name, checked === 'yes' ? 1 : 0)} />
        <div className="vipps-mobilepay-react-switch-info"><WPLabel htmlFor={id}>{title}</WPLabel>{help}{error}</div>
      </WPFormField>
    );
  }

  if (field.type === 'multicheck') {
    const roles = typeof value === 'object' ? value : {};
    return (
      <fieldset className="vipps-mobilepay-react-form-field vipps-login-role-fields" aria-describedby={describedBy} aria-invalid={errors.length > 0}>
        <legend className="vipps-mobilepay-react-label">{title}</legend>
        {Object.entries(field.options ?? {}).map(([role, label]) => (
          <label key={role}>
            <input type="checkbox" checked={Object.prototype.hasOwnProperty.call(roles, role)}
              onChange={(event) => {
                // Delete unchecked entries: PHP treats presence of a role as required.
                const next = { ...roles };
                if (event.target.checked) next[role] = 1;
                else delete next[role];
                setOption(name, next);
              }} /> {label}
          </label>
        ))}
        {help}{error}
      </fieldset>
    );
  }

  return (
    <WPFormField>
      {field.type === 'description' ? <div className="vipps-mobilepay-react-label">{title}</div> : <WPLabel htmlFor={id}>{title}</WPLabel>}
      <div className="vipps-mobilepay-react-col">
        {field.type === 'description' ? (
          // The callback is text, never an editable value or injected HTML.
          <code className="vipps-login-callback">{String(field.default ?? '')}</code>
        ) : field.type === 'select' ? (
          <WPSelect id={id} name={name} value={name === 'continuepageid' && !Number(value) ? '' : scalarValue}
            aria-describedby={describedBy} aria-invalid={errors.length > 0}
            onChange={(event) => setOption(name, event.target.value)}>
            {Object.entries(field.options ?? {}).map(([key, label]) => <WPOption key={key} value={key}>{label}</WPOption>)}
          </WPSelect>
        ) : (
          <div className="vipps-login-input-row">
            <WPInput id={id} name={name} type={field.type === 'password' ? 'password' : 'text'}
              value={scalarValue} placeholder={loginMethodText(field.placeholder ?? '', method)}
              autoComplete={field.type === 'password' ? 'off' : undefined}
              spellCheck={field.type === 'password' ? false : undefined}
              aria-describedby={describedBy} aria-invalid={errors.length > 0}
              onFocus={(event) => {
                // Match the former settings page: credentials are readable only
                // while the field has focus, without adding a show/hide button.
                if (field.type === 'password') event.currentTarget.type = 'text';
              }}
              onBlur={(event) => {
                if (field.type === 'password') event.currentTarget.type = 'password';
              }}
              onChange={(event) => setOption(name, event.target.value)} />
          </div>
        )}
        {help}{error}
      </div>
    </WPFormField>
  );
}
