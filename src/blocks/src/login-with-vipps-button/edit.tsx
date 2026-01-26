import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	PanelBody,
	CheckboxControl,
} from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

import { blockConfig } from './blockConfig';
import './editor.css';
import { Attributes } from './types';

export type EditProps = BlockEditProps< Attributes >;

export default function Edit( { attributes, setAttributes }: EditProps ) {
	console.log( 'LP attributes: ', attributes );

	// Let the user choose the application. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
	const appOptions = blockConfig.applications;
	const current = attributes.application;
	let found = false;
	for ( let i = 0; i < appOptions.length; i++ ) {
		if ( current === appOptions[ i ].value ) {
			found = true;
			break;
		}
	}
	if ( ! found ) {
		appOptions.push( { label: current, value: current } );
	}

	const backgroundColorClass =
		attributes.loginMethod === 'Vipps'
			? 'vipps-background'
			: 'mobilepay-background';

	const language =
		attributes.language === 'store'
			? blockConfig.storeLanguage
			: attributes.language;

	return (
		<>
			{ /* The block itself. LP 11.11.2024 */ }
			<div
				{ ...useBlockProps( {
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
				>
					{ /* Web component https://developer.vippsmobilepay.com/docs/knowledge-base/design-guidelines/buttons/#javascript-button-library. :LPLP 2026-01-26 */ }
					{ /* @ts-ignore */ }
					<vipps-mobilepay-button
						type="button"
						loading="false"
						brand={ blockConfig.loginMethod }
						language={ language }
						variant={ attributes.variant }
						rounded={ attributes.rounded }
						verb={ attributes.verb }
						stretched="true"
						branded={ attributes.branded }
						// @ts-ignore
					></vipps-mobilepay-button>
				</a>
			</div>

			{ /* The block controls on the right side-panel. LP 11.11.2024 */ }
			<InspectorControls>
				<PanelBody>
					<SelectControl
						onChange={ ( newApp: string ) =>
							setAttributes( { application: newApp } )
						}
						label={ __( 'Application', 'login-with-vipps' ) }
						value={ attributes.application }
						options={ appOptions }
						help={ blockConfig.applicationsText }
					/>
					<SelectControl
						onChange={ ( language: string ) =>
							setAttributes( { language } )
						}
						label={ __( 'Language', 'login-with-vipps' ) }
						value={ attributes.language }
						options={ blockConfig.languages }
					/>
					<SelectControl
						onChange={ ( variant: string ) =>
							setAttributes( { variant } )
						}
						label={ __( 'Variant', 'login-with-vipps' ) }
						value={ attributes.variant }
						options={ blockConfig.variants }
					/>
					<SelectControl
						onChange={ ( verb: string ) =>
							setAttributes( { verb } )
						}
						label={ __( 'Verb', 'login-with-vipps' ) }
						value={ attributes.verb }
						options={ blockConfig.verbs }
					/>
					<CheckboxControl
						onChange={ ( rounded: boolean ) =>
							setAttributes( { rounded } )
						}
						label={ __( 'Rounded', 'login-with-vipps' ) }
						value={ attributes.rounded }
					/>
					<CheckboxControl
						onChange={ ( branded: boolean ) =>
							setAttributes( { branded } )
						}
						label={ __( 'Branded', 'login-with-vipps' ) }
						value={ attributes.branded }
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
