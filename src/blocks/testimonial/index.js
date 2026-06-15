/**
 * Testimonial block — editor script.
 * Server-side rendered: save() returns null, PHP render_callback produces output.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, SelectControl } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { quote = '', name = '', role = '', company = '', imageUrl = '', layout = 'card' } = attributes;
	const blockProps = useBlockProps( { className: `lkst-testimonial lkst-testimonial--editor lkst-testimonial--${ layout }` } );

	const set = ( field ) => ( val ) => setAttributes( { [ field ]: val } );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Testimonial Settings', 'zehoro-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Layout', 'zehoro-toolkit' ) }
						value={ layout }
						options={ [
							{ label: __( 'Card (bordered)', 'zehoro-toolkit' ),               value: 'card' },
							{ label: __( 'Minimal (no border)', 'zehoro-toolkit' ),            value: 'minimal' },
							{ label: __( 'Highlighted (accent left border)', 'zehoro-toolkit' ), value: 'highlight' },
						] }
						onChange={ set( 'layout' ) }
					/>
					<TextControl
						label={ __( 'Avatar image URL', 'zehoro-toolkit' ) }
						help={ __( 'Paste a URL or upload to the media library first.', 'zehoro-toolkit' ) }
						value={ imageUrl }
						onChange={ set( 'imageUrl' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<figure { ...blockProps }>
				<p className="lkst-editor-label">{ __( 'Testimonial', 'zehoro-toolkit' ) }</p>
				<TextareaControl
					label={ __( 'Quote', 'zehoro-toolkit' ) }
					placeholder={ __( 'The quote text…', 'zehoro-toolkit' ) }
					value={ quote }
					rows={ 3 }
					onChange={ set( 'quote' ) }
				/>
				<TextControl
					label={ __( 'Name', 'zehoro-toolkit' ) }
					placeholder={ __( 'Jane Smith', 'zehoro-toolkit' ) }
					value={ name }
					onChange={ set( 'name' ) }
				/>
				<TextControl
					label={ __( 'Role / Title', 'zehoro-toolkit' ) }
					placeholder={ __( 'CEO', 'zehoro-toolkit' ) }
					value={ role }
					onChange={ set( 'role' ) }
				/>
				<TextControl
					label={ __( 'Company', 'zehoro-toolkit' ) }
					placeholder={ __( 'Acme Inc.', 'zehoro-toolkit' ) }
					value={ company }
					onChange={ set( 'company' ) }
				/>
			</figure>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
