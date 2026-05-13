/**
 * Inline Product Mention block — editor script.
 * Server-side rendered: save() returns null, PHP render_callback produces output.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const {
		name = '',
		desc = '',
		imageUrl = '',
		imageAlt = '',
		btnText = 'Check Price',
		btnUrl = '',
		btnRel = 'nofollow sponsored',
	} = attributes;
	const blockProps = useBlockProps( { className: 'lkst-inline-product lkst-inline-product--editor' } );

	const set = ( field ) => ( val ) => setAttributes( { [ field ]: val } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Product Link', 'leokoo-site-toolkit' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Button text', 'leokoo-site-toolkit' ) }
						value={ btnText }
						onChange={ set( 'btnText' ) }
					/>
					<TextControl
						label={ __( 'Button URL', 'leokoo-site-toolkit' ) }
						help={ __( 'Affiliate link or product page.', 'leokoo-site-toolkit' ) }
						value={ btnUrl }
						onChange={ set( 'btnUrl' ) }
					/>
					<SelectControl
						label={ __( 'Link rel', 'leokoo-site-toolkit' ) }
						value={ btnRel }
						options={ [
							{ label: __( 'nofollow sponsored (affiliate)',  'leokoo-site-toolkit' ), value: 'nofollow sponsored' },
							{ label: __( 'nofollow (non-affiliate)',         'leokoo-site-toolkit' ), value: 'nofollow' },
							{ label: __( 'none (internal / editorial)',      'leokoo-site-toolkit' ), value: '' },
						] }
						onChange={ set( 'btnRel' ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Image', 'leokoo-site-toolkit' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Image URL', 'leokoo-site-toolkit' ) }
						help={ __( 'Upload to media library, then paste the URL.', 'leokoo-site-toolkit' ) }
						value={ imageUrl }
						onChange={ set( 'imageUrl' ) }
					/>
					<TextControl
						label={ __( 'Image alt text', 'leokoo-site-toolkit' ) }
						value={ imageAlt }
						onChange={ set( 'imageAlt' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<p className="lkst-editor-label">
					{ __( 'Inline Product Mention', 'leokoo-site-toolkit' ) }
				</p>
				<TextControl
					label={ __( 'Product name', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'Logitech MX Master 3…', 'leokoo-site-toolkit' ) }
					value={ name }
					onChange={ set( 'name' ) }
				/>
				<TextControl
					label={ __( 'One-liner description', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'The best ergonomic mouse for power users.', 'leokoo-site-toolkit' ) }
					value={ desc }
					onChange={ set( 'desc' ) }
				/>
				{ ! btnUrl ? (
					<p style={ { color: '#b45309', fontSize: '12px' } }>
						{ __( '⚠ Add a Button URL in the sidebar to complete this block.', 'leokoo-site-toolkit' ) }
					</p>
				) : (
					<p style={ { fontSize: '12px', color: '#2e7d32' } }>
						{ __( '✓ Link set: ', 'leokoo-site-toolkit' ) }{ btnUrl }
					</p>
				) }
			</div>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
