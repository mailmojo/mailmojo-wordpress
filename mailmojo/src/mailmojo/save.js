import { RichText, useBlockProps } from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { popupUrl, buttonText } = attributes;

	return (
		<a
			{ ...useBlockProps.save( {
				className: 'mailmojo-popup-button wp-element-button',
			} ) }
			href={ popupUrl || '#' }
		>
			<RichText.Content tagName="span" value={ buttonText } />
		</a>
	);
}
