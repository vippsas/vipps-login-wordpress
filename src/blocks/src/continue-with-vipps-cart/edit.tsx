import { useBlockProps } from '@wordpress/block-editor';

import { blockConfig } from './config';

export default function Edit() {
	return (
		<div
			{ ...useBlockProps( {
				className:
					'login-with-vipps-block continue-with-vipps-cart backend',
			} ) }
		>
			{ /* The buy-now button. LP 2026-01-19 */ }
			<a
				title={ blockConfig[ 'title' ] }
				dangerouslySetInnerHTML={ {
					__html: blockConfig[ 'buttonHtml' ],
				} }
			></a>
		</div>
	);
}
