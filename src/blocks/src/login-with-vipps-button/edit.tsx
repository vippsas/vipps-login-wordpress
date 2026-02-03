import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	SelectControl,
	PanelBody,
	CheckboxControl,
} from '@wordpress/components';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

import { blockConfig } from './blockConfig';
import metadata from './block.json';
import './editor.css';
import { Attributes } from './types';

export type EditProps = BlockEditProps< Attributes >;

console.log( metadata.attributes );
console.log( metadata.attributes[ 'variant' ].default );
export default function Edit( { attributes, setAttributes }: EditProps ) {
	/** Returns attribute with default provided by metadata in block.json.
	 * Annoying wrapper, but this is used because if an attribute is equal to its default value, then its not set in the attributes object at all... LP 2026-02-03 */
	function getAttribute(
		attributeName: string,
		defaultValue: any = null
	): any {
		if ( null === defaultValue ) {
			// @ts-ignore
			defaultValue = metadata.attributes[ attributeName ]?.default;
		}
		return attributes[ attributeName ] ?? defaultValue;
	}
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
		'Vipps' === blockConfig.loginMethod
			? 'vipps-background'
			: 'mobilepay-background';

	const language =
		'store' === getAttribute( 'language' )
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
					title={ getAttribute( 'title' ) }
					data-application={ getAttribute( 'application' ) }
				>
					{ /* Web component https://developer.vippsmobilepay.com/docs/knowledge-base/design-guidelines/buttons/#javascript-button-library. :LP 2026-01-26 */ }
					{ /* @ts-ignore */ }
					<vipps-mobilepay-button
						brand={ blockConfig.loginMethod }
						language={ language }
						variant={ getAttribute( 'variant' ) }
						rounded={ getAttribute( 'rounded' ) }
						verb={ getAttribute( 'verb' ) }
						stretched="true"
						branded={ getAttribute( 'branded' ) }
						// @ts-ignore
					></vipps-mobilepay-button>
				</a>
			</div>

			{ /* The block controls on the right side-panel. LP 11.11.2024 */ }
			<InspectorControls>
				<PanelBody>
					<SelectControl
						onChange={ ( application: string ) =>
							setAttributes( { application } )
						}
						label={ __( 'Application', 'login-with-vipps' ) }
						value={ getAttribute( 'application' ) }
						options={ appOptions }
						help={ blockConfig.applicationsText }
					/>
					<SelectControl
						onChange={ ( language: string ) =>
							setAttributes( { language } )
						}
						label={ __( 'Language', 'login-with-vipps' ) }
						value={ getAttribute( 'language' ) }
						options={ blockConfig.languages }
					/>
					<SelectControl
						onChange={ ( variant: string ) =>
							setAttributes( { variant } )
						}
						label={ __( 'Variant', 'login-with-vipps' ) }
						value={ getAttribute( 'variant' ) }
						options={ blockConfig.variants }
					/>
					<SelectControl
						onChange={ ( verb: string ) =>
							setAttributes( { verb } )
						}
						label={ __( 'Verb', 'login-with-vipps' ) }
						value={ getAttribute( 'verb' ) }
						options={ blockConfig.verbs }
					/>
					<CheckboxControl
						onChange={ ( rounded: boolean ) =>
							setAttributes( { rounded } )
						}
						label={ __( 'Rounded', 'login-with-vipps' ) }
						checked={ getAttribute( 'rounded' ) }
					/>
					<CheckboxControl
						onChange={ ( branded: boolean ) =>
							setAttributes( { branded } )
						}
						label={ __( 'Branded', 'login-with-vipps' ) }
						checked={ getAttribute( 'branded' ) }
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
