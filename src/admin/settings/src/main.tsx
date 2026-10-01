import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';

// PHP renders the root first and loads this script in the admin footer.
const root = document.getElementById('vipps-login-react-ui');
if (root) ReactDOM.createRoot(root).render(<React.StrictMode><App /></React.StrictMode>);
