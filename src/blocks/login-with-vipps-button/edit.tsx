import { __ } from '@wordpress/i18n';

import { useBlockProps } from '@wordpress/block-editor';

import './editor.scss';

export default function Edit() {
	return (
		<p { ...useBlockProps() }>
			{ __( 'Login with Vipps&#x2F;MobilePay-button – hello from the editor!', 'login-with-vipps-button' ) }
		</p>
	);
}
