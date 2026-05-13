/**
 * Steps / Process block — editor script (no build step, vanilla WP globals).
 * Server-side rendered: save() returns null, PHP render_callback produces output.
 */
( function () {
	var __ = wp.i18n.__;
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps    = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody        = wp.components.PanelBody;
	var TextControl      = wp.components.TextControl;
	var TextareaControl  = wp.components.TextareaControl;
	var Button           = wp.components.Button;

	registerBlockType( 'lkst/steps', {
		edit: function ( props ) {
			var attributes  = props.attributes;
			var setAttributes = props.setAttributes;
			var steps       = attributes.steps || [];
			var taskName    = attributes.taskName || '';
			var blockProps  = useBlockProps( { className: 'lkst-steps lkst-steps--editor' } );

			function updateStep( index, field, value ) {
				var next = steps.slice();
				next[ index ] = Object.assign( {}, next[ index ], { [field]: value } );
				setAttributes( { steps: next } );
			}

			function addStep() {
				setAttributes( { steps: steps.concat( [ { title: '', content: '' } ] ) } );
			}

			function removeStep( index ) {
				setAttributes( { steps: steps.filter( function ( _, i ) { return i !== index; } ) } );
			}

			function moveStep( index, direction ) {
				var next  = steps.slice();
				var other = index + direction;
				if ( other < 0 || other >= next.length ) return;
				var tmp       = next[ index ];
				next[ index ] = next[ other ];
				next[ other ] = tmp;
				setAttributes( { steps: next } );
			}

			return [
				el( InspectorControls, { key: 'controls' },
					el( PanelBody, { title: __( 'How-To Schema', 'leokoo-site-toolkit' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Task / Tutorial name (for HowTo schema)', 'leokoo-site-toolkit' ),
							help:  __( 'Defaults to post title if left blank.', 'leokoo-site-toolkit' ),
							value: taskName,
							onChange: function ( val ) { setAttributes( { taskName: val } ); },
						} )
					)
				),

				el( 'div', Object.assign( {}, blockProps, { key: 'block' } ),
					el( 'p', { className: 'lkst-steps-editor-label' }, __( 'Steps / Process', 'leokoo-site-toolkit' ) ),

					steps.map( function ( step, i ) {
						return el( 'div', { key: i, className: 'lkst-step-editor-row' },
							el( 'div', { className: 'lkst-step-editor-header' },
								el( 'span', { className: 'lkst-step-num' }, i + 1 ),
								el( Button, {
									icon: 'arrow-up-alt2',
									label: __( 'Move up', 'leokoo-site-toolkit' ),
									isSmall: true,
									disabled: i === 0,
									onClick: function () { moveStep( i, -1 ); },
								} ),
								el( Button, {
									icon: 'arrow-down-alt2',
									label: __( 'Move down', 'leokoo-site-toolkit' ),
									isSmall: true,
									disabled: i === steps.length - 1,
									onClick: function () { moveStep( i, 1 ); },
								} ),
								el( Button, {
									icon: 'trash',
									label: __( 'Remove step', 'leokoo-site-toolkit' ),
									isDestructive: true,
									isSmall: true,
									onClick: function () { removeStep( i ); },
								} )
							),
							el( TextControl, {
								placeholder: __( 'Step title…', 'leokoo-site-toolkit' ),
								value: step.title || '',
								onChange: function ( val ) { updateStep( i, 'title', val ); },
							} ),
							el( TextareaControl, {
								placeholder: __( 'Step description (optional)…', 'leokoo-site-toolkit' ),
								value: step.content || '',
								rows: 2,
								onChange: function ( val ) { updateStep( i, 'content', val ); },
							} )
						);
					} ),

					el( Button, {
						variant: 'secondary',
						icon: 'plus',
						onClick: addStep,
					}, __( 'Add step', 'leokoo-site-toolkit' ) )
				)
			];
		},

		save: function () {
			return null; // server-side rendered
		},
	} );
} )();
