/**
 * Steps / Process block — editor script.
 * Server-side rendered: save() returns null, PHP render_callback produces output.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, TextareaControl, Button } from '@wordpress/components';
import metadata from './block.json';

function Edit( { attributes, setAttributes } ) {
	const { steps = [], taskName = '' } = attributes;
	const blockProps = useBlockProps( { className: 'lkst-steps lkst-steps--editor' } );

	function updateStep( index, field, value ) {
		const next = steps.slice();
		next[ index ] = { ...next[ index ], [ field ]: value };
		setAttributes( { steps: next } );
	}

	function addStep() {
		setAttributes( { steps: [ ...steps, { title: '', content: '' } ] } );
	}

	function removeStep( index ) {
		setAttributes( { steps: steps.filter( ( _, i ) => i !== index ) } );
	}

	function moveStep( index, direction ) {
		const next  = steps.slice();
		const other = index + direction;
		if ( other < 0 || other >= next.length ) return;
		[ next[ index ], next[ other ] ] = [ next[ other ], next[ index ] ];
		setAttributes( { steps: next } );
	}

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'How-To Schema', 'leokoo-site-toolkit' ) }
					initialOpen={ true }
				>
					<TextControl
						label={ __( 'Task / Tutorial name (for HowTo schema)', 'leokoo-site-toolkit' ) }
						help={ __( 'Defaults to post title if left blank.', 'leokoo-site-toolkit' ) }
						value={ taskName }
						onChange={ ( val ) => setAttributes( { taskName: val } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps }>
				<p className="lkst-steps-editor-label">
					{ __( 'Steps / Process', 'leokoo-site-toolkit' ) }
				</p>

				{ steps.map( ( step, i ) => (
					<div key={ i } className="lkst-step-editor-row">
						<div className="lkst-step-editor-header">
							<span className="lkst-step-num">{ i + 1 }</span>
							<Button
								icon="arrow-up-alt2"
								label={ __( 'Move up', 'leokoo-site-toolkit' ) }
								isSmall
								disabled={ i === 0 }
								onClick={ () => moveStep( i, -1 ) }
							/>
							<Button
								icon="arrow-down-alt2"
								label={ __( 'Move down', 'leokoo-site-toolkit' ) }
								isSmall
								disabled={ i === steps.length - 1 }
								onClick={ () => moveStep( i, 1 ) }
							/>
							<Button
								icon="trash"
								label={ __( 'Remove step', 'leokoo-site-toolkit' ) }
								isDestructive
								isSmall
								onClick={ () => removeStep( i ) }
							/>
						</div>
						<TextControl
							placeholder={ __( 'Step title…', 'leokoo-site-toolkit' ) }
							value={ step.title || '' }
							onChange={ ( val ) => updateStep( i, 'title', val ) }
						/>
						<TextareaControl
							placeholder={ __( 'Step description (optional)…', 'leokoo-site-toolkit' ) }
							value={ step.content || '' }
							rows={ 2 }
							onChange={ ( val ) => updateStep( i, 'content', val ) }
						/>
					</div>
				) ) }

				<Button variant="secondary" icon="plus" onClick={ addStep }>
					{ __( 'Add step', 'leokoo-site-toolkit' ) }
				</Button>
			</div>
		</>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: () => null,
} );
