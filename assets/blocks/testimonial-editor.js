/**
 * Testimonial block — editor script (no build step, vanilla WP globals).
 * Server-side rendered: save() returns null, PHP render_callback produces output.
 */
( function () {
	var __ = wp.i18n.__;
	var el = wp.element.createElement;
	var registerBlockType  = wp.blocks.registerBlockType;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var PanelBody          = wp.components.PanelBody;
	var TextControl        = wp.components.TextControl;
	var TextareaControl    = wp.components.TextareaControl;
	var SelectControl      = wp.components.SelectControl;

	registerBlockType( 'lkst/testimonial', {
		edit: function ( props ) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps    = useBlockProps( { className: 'lkst-testimonial lkst-testimonial--editor' } );

			var quote    = attributes.quote    || '';
			var name     = attributes.name     || '';
			var role     = attributes.role     || '';
			var company  = attributes.company  || '';
			var imageUrl = attributes.imageUrl || '';
			var layout   = attributes.layout   || 'card';

			function set( field ) {
				return function ( val ) { setAttributes( { [field]: val } ); };
			}

			return [
				el( InspectorControls, { key: 'controls' },
					el( PanelBody, { title: __( 'Testimonial Settings', 'leokoo-site-toolkit' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Layout', 'leokoo-site-toolkit' ),
							value: layout,
							options: [
								{ label: __( 'Card (bordered)', 'leokoo-site-toolkit' ), value: 'card' },
								{ label: __( 'Minimal (no border)', 'leokoo-site-toolkit' ), value: 'minimal' },
								{ label: __( 'Highlighted (accent left border)', 'leokoo-site-toolkit' ), value: 'highlight' },
							],
							onChange: set( 'layout' ),
						} ),
						el( TextControl, {
							label: __( 'Avatar image URL', 'leokoo-site-toolkit' ),
							help:  __( 'Paste a URL or upload an image to the media library first.', 'leokoo-site-toolkit' ),
							value: imageUrl,
							onChange: set( 'imageUrl' ),
						} )
					)
				),

				el( 'figure', Object.assign( {}, blockProps, { key: 'block' } ),
					el( 'p', { className: 'lkst-editor-label' }, __( 'Testimonial', 'leokoo-site-toolkit' ) ),
					el( TextareaControl, {
						label: __( 'Quote', 'leokoo-site-toolkit' ),
						placeholder: __( 'The quote text…', 'leokoo-site-toolkit' ),
						value: quote,
						rows: 3,
						onChange: set( 'quote' ),
					} ),
					el( TextControl, {
						label: __( 'Name', 'leokoo-site-toolkit' ),
						placeholder: __( 'Jane Smith', 'leokoo-site-toolkit' ),
						value: name,
						onChange: set( 'name' ),
					} ),
					el( TextControl, {
						label: __( 'Role / Title', 'leokoo-site-toolkit' ),
						placeholder: __( 'CEO', 'leokoo-site-toolkit' ),
						value: role,
						onChange: set( 'role' ),
					} ),
					el( TextControl, {
						label: __( 'Company', 'leokoo-site-toolkit' ),
						placeholder: __( 'Acme Corp', 'leokoo-site-toolkit' ),
						value: company,
						onChange: set( 'company' ),
					} )
				)
			];
		},

		save: function () {
			return null; // server-side rendered
		},
	} );
} )();
