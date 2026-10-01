import './App.css';
import './login.css';
import { AdminSettings } from './components/admin-settings/admin-settings';
import { WPOptionsProvider } from './wp-options-provider';

/** Reuse the payment settings shell while keeping state owned by the login app. */
export default function App(): JSX.Element {
  return (
    <div className="vipps-mobilepay-react-admin-page vipps-login-react-admin-page">
      <WPOptionsProvider><AdminSettings /></WPOptionsProvider>
    </div>
  );
}
