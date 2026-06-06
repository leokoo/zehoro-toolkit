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
					title={ __( 'Stat Callout Settings', 'zehoro-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Layout', 'zehoro-toolkit' ) }
						value={ layout }
						options={ [
							{ label: __( 'Centred', 'zehoro-toolkit' ),        value: 'centered' },
							{ label: __( 'Left-aligned', 'zehoro-toolkit' ),   value: 'left' },
							{ label: __( 'Highlighted box', 'zehoro-toolkit' ), value: 'boxed' },
						] }
						onChange={ set( 'layout' ) }
					/>
					<TextControl
						label={ __( 'Source label', 'zehoro-toolkit' ) }
						help={ __( 'e.g. "Statista, 2024"', 'zehoro-toolkit' ) }
						value={ source }
						onChange={ set( 'source' ) }
					/>
					<TextControl
						label={ __( 'Source URL (optional)', 'zehoro-toolkit' ) }
						value={ sourceUrl }
						onChange={ set( 'sourceUrl' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<p className="lkst-editor-label">{ __( 'Stat Callout', 'zehoro-toolkit' ) }</p>
				<TextControl
					label={ __( 'Stat / Number', 'zehoro-toolkit' ) }
					placeholder={ __( '10,000+', 'zehoro-toolkit' ) }
					value={ stat }
					onChange={ set( 'stat' ) }
				/>
				<TextControl
					label={ __( 'Label', 'zehoro-toolkit' ) }
					placeholder={ __( 'users trust our platform', 'zehoro-toolkit' ) }
					value={ label }
					onChange={ set( 'label' ) }
				/>
				<TextControl
					label={ __( 'Description (optional)', 'zehoro-toolkit' ) }
					placeholder={ __( 'Additional context…', 'zehoro-toolkit' ) }
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
