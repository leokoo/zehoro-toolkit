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
					title={ __( 'Product Link', 'zehoro-toolkit' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Button text', 'zehoro-toolkit' ) }
						value={ btnText }
						onChange={ set( 'btnText' ) }
					/>
					<TextControl
						label={ __( 'Button URL', 'zehoro-toolkit' ) }
						help={ __( 'Affiliate link or product page.', 'zehoro-toolkit' ) }
						value={ btnUrl }
						onChange={ set( 'btnUrl' ) }
					/>
					<SelectControl
						label={ __( 'Link rel', 'zehoro-toolkit' ) }
						value={ btnRel }
						options={ [
							{ label: __( 'nofollow sponsored (affiliate)',  'zehoro-toolkit' ), value: 'nofollow sponsored' },
							{ label: __( 'nofollow (non-affiliate)',         'zehoro-toolkit' ), value: 'nofollow' },
							{ label: __( 'none (internal / editorial)',      'zehoro-toolkit' ), value: '' },
						] }
						onChange={ set( 'btnRel' ) }
					/>
				</PanelBody>
				<PanelBody
					title={ __( 'Image', 'zehoro-toolkit' ) }
					initialOpen={ false }
				>
					<TextControl
						label={ __( 'Image URL', 'zehoro-toolkit' ) }
						help={ __( 'Upload to media library, then paste the URL.', 'zehoro-toolkit' ) }
						value={ imageUrl }
						onChange={ set( 'imageUrl' ) }
					/>
					<TextControl
						label={ __( 'Image alt text', 'zehoro-toolkit' ) }
						value={ imageAlt }
						onChange={ set( 'imageAlt' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<p className="lkst-editor-label">
					{ __( 'Inline Product Mention', 'zehoro-toolkit' ) }
				</p>
				<TextControl
					label={ __( 'Product name', 'zehoro-toolkit' ) }
					placeholder={ __( 'Logitech MX Master 3…', 'zehoro-toolkit' ) }
					value={ name }
					onChange={ set( 'name' ) }
				/>
				<TextControl
					label={ __( 'One-liner description', 'zehoro-toolkit' ) }
					placeholder={ __( 'The best ergonomic mouse for power users.', 'zehoro-toolkit' ) }
					value={ desc }
					onChange={ set( 'desc' ) }
				/>
				{ ! btnUrl ? (
					<p style={ { color: '#b45309', fontSize: '12px' } }>
						{ __( '⚠ Add a Button URL in the sidebar to complete this block.', 'zehoro-toolkit' ) }
					</p>
				) : (
					<p style={ { fontSize: '12px', color: '#2e7d32' } }>
						{ __( '✓ Link set: ', 'zehoro-toolkit' ) }{ btnUrl }
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
