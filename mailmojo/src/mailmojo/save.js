import { useBlockProps } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function save( { attributes } ) {
	const { popupUrl } = attributes;

	return (
		<a
			{ ...useBlockProps.save( {
				className: 'mailmojo-popup-button wp-element-button',
			} ) }
			href={ popupUrl || '#' }
		>
			{ __( 'Subscribe to our newsletter', 'mailmojo' ) }
		</a>
	);
}
