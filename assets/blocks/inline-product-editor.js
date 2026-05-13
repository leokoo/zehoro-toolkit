/**
 * Inline Product Mention block — editor script (no build step, vanilla WP globals).
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
	var SelectControl      = wp.components.SelectControl;

	registerBlockType( 'lkst/inline-product', {
		edit: function ( props ) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps    = useBlockProps( { className: 'lkst-inline-product lkst-inline-product--editor' } );

			var name     = attributes.name     || '';
			var desc     = attributes.desc     || '';
			var imageUrl = attributes.imageUrl || '';
			var imageAlt = attributes.imageAlt || '';
			var btnText  = attributes.btnText  || 'Check Price';
			var btnUrl   = attributes.btnUrl   || '';
			var btnRel   = attributes.btnRel   || 'nofollow sponsored';

			function set( field ) {
				return function ( val ) { setAttributes( { [field]: val } ); };
			}

			return [
				el( InspectorControls, { key: 'controls' },
					el( PanelBody, { title: __( 'Product Link', 'leokoo-site-toolkit' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Button text', 'leokoo-site-toolkit' ),
							value: btnText,
							onChange: set( 'btnText' ),
						} ),
						el( TextControl, {
							label: __( 'Button URL', 'leokoo-site-toolkit' ),
							help:  __( 'Affiliate link or product page.', 'leokoo-site-toolkit' ),
							value: btnUrl,
							onChange: set( 'btnUrl' ),
						} ),
						el( SelectControl, {
							label: __( 'Link rel', 'leokoo-site-toolkit' ),
							value: btnRel,
							options: [
								{ label: __( 'nofollow sponsored (affiliate)',  'leokoo-site-toolkit' ), value: 'nofollow sponsored' },
								{ label: __( 'nofollow (non-affiliate)',         'leokoo-site-toolkit' ), value: 'nofollow' },
								{ label: __( 'none (internal / editorial)',      'leokoo-site-toolkit' ), value: '' },
							],
							onChange: set( 'btnRel' ),
						} )
					),
					el( PanelBody, { title: __( 'Image', 'leokoo-site-toolkit' ), initialOpen: false },
						el( TextControl, {
							label: __( 'Image URL', 'leokoo-site-toolkit' ),
							help:  __( 'Upload to media library, then paste the URL.', 'leokoo-site-toolkit' ),
							value: imageUrl,
							onChange: set( 'imageUrl' ),
						} ),
						el( TextControl, {
							label: __( 'Image alt text', 'leokoo-site-toolkit' ),
							value: imageAlt,
							onChange: set( 'imageAlt' ),
						} )
					)
				),

				el( 'div', Object.assign( {}, blockProps, { key: 'block' } ),
					el( 'p', { className: 'lkst-editor-label' }, __( 'Inline Product Mention', 'leokoo-site-toolkit' ) ),
					el( TextControl, {
						label: __( 'Product name', 'leokoo-site-toolkit' ),
						placeholder: __( 'Logitech MX Master 3…', 'leokoo-site-toolkit' ),
						value: name,
						onChange: set( 'name' ),
					} ),
					el( TextControl, {
						label: __( 'One-liner description', 'leokoo-site-toolkit' ),
						placeholder: __( 'The best ergonomic mouse for power users.', 'leokoo-site-toolkit' ),
						value: desc,
						onChange: set( 'desc' ),
					} ),
					! btnUrl
						? el( 'p', { style: { color: '#b45309', fontSize: '12px' } },
							__( '⚠ Add a Button URL in the sidebar to complete this block.', 'leokoo-site-toolkit' )
						)
						: el( 'p', { style: { fontSize: '12px', color: '#2e7d32' } },
							__( '✓ Link set: ', 'leokoo-site-toolkit' ), btnUrl
						)
				)
			];
		},

		save: function () {
			return null; // server-side rendered
		},
	} );
} )();
