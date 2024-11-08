import { __ } from '@wordpress/i18n';

import type { BlockAttributes, BlockEditProps } from '@wordpress/blocks';
import { useBlockProps, RichText } from '@wordpress/block-editor';

import './editor.css';

interface LoginWithVippsBlockAttributes extends BlockAttributes {
	application: string;
	title: string;
	preLogo: string;
	postLogo: string;
	loginMethod: string;
}

interface Application {
	label: string;
	value: string;
}

interface LoginWithVippsBlockConfig {
	title: string;
	iconSrc: string;
	defaultApp: string;
	defaultTitle: string;
	defaultTextPreLogo: string;
	defaultTextPostLogo: string;
	loginMethod: string;
	loginMethodLogoSrc: string;
	applications: Application[];
}

// const injectedBlockConfig gets injected from <pluginRoot>/blocks/login-with-vipps-blocks.php. It should follow the interface LoginWithVippsBlockConfig. LP 08.11.2024
// @ts-ignore
export const blockConfig: LoginWithVippsBlockConfig = injectedBlockConfig;

export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< LoginWithVippsBlockAttributes > ) {
	let formats = [ 'core/bold', 'core/italic' ];

	// Let the user choose the application. If the current one isn't in the list, add it (though we don't know the label then. IOK 2020-12-18
	let appOptions = blockConfig.applications;
	let current = attributes.application;
	let found = false;
	for ( let i = 0; i < appOptions.length; i++ ) {
		if ( current == appOptions[ i ].value ) {
			found = true;
			break;
		}
	}
	if ( ! found ) appOptions.push( { label: current, value: current } );

	let backgroundColorClass =
		attributes.loginMethod === 'Vipps'
			? 'vipps-background'
			: 'mobilepay-background';

	return (
		<>
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
				>
					<RichText
						className="prelogo"
						tagName="span"
						// inline // inline is from the previous implementation by iok, I can't find inline anywhere in the RichText doc: https://github.com/WordPress/gutenberg/blob/HEAD/packages/block-editor/src/components/rich-text/README.md. LP 08.11.2024
						allowedFormats={ formats }
						value={ attributes.preLogo }
						onChange={ ( v ) => setAttributes( { prelogo: v } ) }
					/>
					<img
						alt={ attributes.title }
						src={ blockConfig.loginMethodLogoSrc }
					/>
				</a>
			</span>
		</>
	);
}
