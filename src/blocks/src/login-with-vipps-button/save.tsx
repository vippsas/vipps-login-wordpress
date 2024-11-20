import type { BlockSaveProps } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';

import type { LoginWithVippsBlockAttributes } from './types';
import { blockConfig } from './blockConfig';

export default function save( {
	attributes,
}: BlockSaveProps< LoginWithVippsBlockAttributes > ) {
	const backgroundColorClass =
		attributes.loginMethod === 'Vipps'
			? 'vipps-background'
			: 'mobilepay-background';

	return (
		<>
			<span
				{ ...useBlockProps.save( {
					className: 'continue-with-vipps-wrapper inline',
				} ) }
			>
				<a
					className={
						'button vipps-orange vipps-button continue-with-vipps continue-with-vipps-action ' +
						backgroundColorClass
					}
					title={ attributes.title }
					data-application={ attributes.application }
					href="javascript: void(0);"
				>
					<RichText.Content
						className="prelogo"
						tagName="span"
						value={ attributes.preLogo }
					/>
					<img
						className="vipps-block-logo-img"
						alt={ attributes.title }
						src={ blockConfig.loginMethodLogoSrc }
					/>
					<RichText.Content
						className="postlogo"
						tagName="span"
						value={ attributes.postLogo }
					/>
				</a>
			</span>
		</>
	);
}
