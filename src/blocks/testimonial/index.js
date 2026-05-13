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
					title={ __( 'Testimonial Settings', 'leokoo-site-toolkit' ) }
					initialOpen={ true }
				>
					<SelectControl
						label={ __( 'Layout', 'leokoo-site-toolkit' ) }
						value={ layout }
						options={ [
							{ label: __( 'Card (bordered)', 'leokoo-site-toolkit' ),               value: 'card' },
							{ label: __( 'Minimal (no border)', 'leokoo-site-toolkit' ),            value: 'minimal' },
							{ label: __( 'Highlighted (accent left border)', 'leokoo-site-toolkit' ), value: 'highlight' },
						] }
						onChange={ set( 'layout' ) }
					/>
					<TextControl
						label={ __( 'Avatar image URL', 'leokoo-site-toolkit' ) }
						help={ __( 'Paste a URL or upload to the media library first.', 'leokoo-site-toolkit' ) }
						value={ imageUrl }
						onChange={ set( 'imageUrl' ) }
					/>
				</PanelBody>
			</InspectorControls>

			<figure { ...blockProps }>
				<p className="lkst-editor-label">{ __( 'Testimonial', 'leokoo-site-toolkit' ) }</p>
				<TextareaControl
					label={ __( 'Quote', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'The quote text…', 'leokoo-site-toolkit' ) }
					value={ quote }
					rows={ 3 }
					onChange={ set( 'quote' ) }
				/>
				<TextControl
					label={ __( 'Name', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'Jane Smith', 'leokoo-site-toolkit' ) }
					value={ name }
					onChange={ set( 'name' ) }
				/>
				<TextControl
					label={ __( 'Role / Title', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'CEO', 'leokoo-site-toolkit' ) }
					value={ role }
					onChange={ set( 'role' ) }
				/>
				<TextControl
					label={ __( 'Company', 'leokoo-site-toolkit' ) }
					placeholder={ __( 'Acme Corp', 'leokoo-site-toolkit' ) }
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
