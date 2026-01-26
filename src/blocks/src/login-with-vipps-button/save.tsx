import type { BlockSaveProps } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

import type { Attributes } from './edit';
import VMPLoginButton from './VippsMobilePayButton';

export type SaveProps = BlockSaveProps< Attributes >;

export default function save( { attributes }: SaveProps ) {
	const backgroundColorClass =
		attributes.loginMethod === 'Vipps'
			? 'vipps-background'
			: 'mobilepay-background';

	return (
		<>
			<div
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
					<vipps-mobilepay-button
						type="button"
						brand="vipps"
						language="en"
						variant="primary"
						rounded="true"
						verb="buy"
						stretched="false"
						branded="true"
						loading="false"
					></vipps-mobilepay-button>
				</a>
			</div>
		</>
	);
}
