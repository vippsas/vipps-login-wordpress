import type { BlockEditProps } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { SelectControl, TextControl, PanelBody } from '@wordpress/components';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';

import type { LoginWithVippsBlockAttributes } from './types';
import { blockConfig } from './blockConfig';
import './editor.css';

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< LoginWithVippsBlockAttributes > ) {
	const formats = [ 'core/bold', 'core/italic' ];

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

	return (
		<>
			{ /* The block itself. LP 11.11.2024 */ }
			<span
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
					<RichText
						className="prelogo"
						tagName="span"
						allowedFormats={ formats }
						value={ attributes.preLogo }
						onChange={ ( val ) =>
							setAttributes( { preLogo: val } )
						}
					/>
					<img
						className="vipps-block-logo-img"
						alt={ attributes.title }
						src={ blockConfig.loginMethodLogoSrc }
					/>
					<RichText
						className="postlogo"
						tagName="span"
						allowedFormats={ formats }
						value={ attributes.postLogo }
						onChange={ ( newVal ) =>
							setAttributes( { postLogo: newVal } )
						}
					/>
				</a>
			</span>

			{ /* The block controls on the right side-panel. LP 11.11.2024 */ }
			<InspectorControls>
				<PanelBody>
					<SelectControl
						onChange={ ( newApp ) =>
							setAttributes( { application: newApp } )
						}
						label={ __( 'Application', 'login-with-vipps' ) }
						value={ attributes.application }
						options={ appOptions }
						help={ blockConfig.applicationsText }
					/>
					<TextControl
						onChange={ ( newTitle ) =>
							setAttributes( { title: newTitle } )
						}
						label={ __( 'Title', 'login-with-vipps' ) }
						value={ attributes.title }
						help={ __(
							'This will be used as the title/popup of the button',
							'login-with-vipps'
						) }
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}
