/**
 * Stat Callout block — editor script (no build step, vanilla WP globals).
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

	registerBlockType( 'lkst/stat-callout', {
		edit: function ( props ) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps    = useBlockProps( { className: 'lkst-stat-callout lkst-stat-callout--editor' } );

			var stat      = attributes.stat      || '';
			var label     = attributes.label     || '';
			var desc      = attributes.desc      || '';
			var source    = attributes.source    || '';
			var sourceUrl = attributes.sourceUrl || '';
			var layout    = attributes.layout    || 'centered';

			function set( field ) {
				return function ( val ) { setAttributes( { [field]: val } ); };
			}

			return [
				el( InspectorControls, { key: 'controls' },
					el( PanelBody, { title: __( 'Stat Callout Settings', 'leokoo-site-toolkit' ), initialOpen: true },
						el( SelectControl, {
							label: __( 'Layout', 'leokoo-site-toolkit' ),
							value: layout,
							options: [
								{ label: __( 'Centred', 'leokoo-site-toolkit' ),       value: 'centered' },
								{ label: __( 'Left-aligned', 'leokoo-site-toolkit' ),  value: 'left' },
								{ label: __( 'Highlighted box', 'leokoo-site-toolkit' ), value: 'boxed' },
							],
							onChange: set( 'layout' ),
						} ),
						el( TextControl, {
							label: __( 'Source label', 'leokoo-site-toolkit' ),
							help:  __( 'e.g. "Statista, 2024"', 'leokoo-site-toolkit' ),
							value: source,
							onChange: set( 'source' ),
						} ),
						el( TextControl, {
							label: __( 'Source URL (optional)', 'leokoo-site-toolkit' ),
							value: sourceUrl,
							onChange: set( 'sourceUrl' ),
						} )
					)
				),

				el( 'div', Object.assign( {}, blockProps, { key: 'block' } ),
					el( 'p', { className: 'lkst-editor-label' }, __( 'Stat Callout', 'leokoo-site-toolkit' ) ),
					el( TextControl, {
						label: __( 'Stat / Number', 'leokoo-site-toolkit' ),
						placeholder: __( '10,000+', 'leokoo-site-toolkit' ),
						value: stat,
						onChange: set( 'stat' ),
					} ),
					el( TextControl, {
						label: __( 'Label', 'leokoo-site-toolkit' ),
						placeholder: __( 'users trust our platform', 'leokoo-site-toolkit' ),
						value: label,
						onChange: set( 'label' ),
					} ),
					el( TextControl, {
						label: __( 'Description (optional)', 'leokoo-site-toolkit' ),
						placeholder: __( 'Additional context…', 'leokoo-site-toolkit' ),
						value: desc,
						onChange: set( 'desc' ),
					} )
				)
			];
		},

		save: function () {
			return null; // server-side rendered
		},
	} );
} )();
