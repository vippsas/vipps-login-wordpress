import { useBlockProps } from '@wordpress/block-editor';

export default function save() {
	return (
		<p { ...useBlockProps.save() }>
			{
				'Login with Vipps/MobilePay-button – hello from the saved content!'
			}
		</p>
	);
}
