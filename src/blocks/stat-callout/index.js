/**
 * Stat Callout block — editor script.
 * Server-side rendered: save() returns null, PHP render_callback produces output.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, SelectControl } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { stat = '', label = '', desc = '', source = '', sourceUrl = '', layout = 'centered' } = attributes;
	const blockProps = useBlockProps( { className: `lkst-stat-callout lkst-stat-callout--editor lkst-stat-callout--${ layout }` } );

	const set = ( field ) => ( val ) => setAttributes( { [ field ]: val } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Stat Callout Settings', 'leokoo-site-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Layout', 'leokoo-site-toolkit' ) }
						value={ layout }
						options={ [
							{ label: __( 'Centred', 'leokoo-site-toolkit' ),        value: 'centered' },
							{ label: __( 'Left-aligned', 'leokoo-site-toolkit' ),   value: 'left' },
							{ label: __( 'Highlighted box', 'leokoo-site-toolkit' ), value: 'boxed' },
						] }
						onChange={ set( 'layout' ) }
					/>
					<TextControl
						label={ __( 'Source label', 'leokoo-site-toolkit' ) }
						help={ __( 'e.g. "Statista, 2024"', 'leokoo-site-toolkit' ) }
						value={ source }
						onChange={ set( 'source' ) }
					/>
					<TextControl
						label={ __( 'Source URL (optional)', 'leokoo-site-toolkit' ) }
						value={ sourceUrl }
						onChange={ set( 'sourceUrl' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<p className="lkst-editor-label">{ __( 'Stat Callout', 'leokoo-site-toolkit' ) }</p>
				<TextControl
					label={ __( 'Stat / Number', 'leokoo-site-toolkit' ) }
					placeholder={ __( '10,000+', 'leokoo-site-toolkit' ) }
					value={ stat }
					onChange={ set( 'stat' ) }
				/>
				<TextControl
					label={ __( 'Label', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'users trust our platform', 'leokoo-site-toolkit' ) }
					value={ label }
					onChange={ set( 'label' ) }
				/>
				<TextControl
					label={ __( 'Description (optional)', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'Additional context…', 'leokoo-site-toolkit' ) }
					value={ desc }
					onChange={ set( 'desc' ) }
				/>
			</div>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
