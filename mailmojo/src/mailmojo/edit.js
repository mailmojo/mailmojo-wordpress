import apiFetch from '@wordpress/api-fetch';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';

import './editor.scss';

const POPUPS_ENDPOINT = '/mailmojo/v1/popups';

export default function Edit( { attributes, setAttributes } ) {
	const { popupId } = attributes;
	const [ popups, setPopups ] = useState( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( '' );

	useEffect( () => {
		let isMounted = true;

		setIsLoading( true );
		apiFetch( { path: POPUPS_ENDPOINT } )
			.then( ( response ) => {
				if ( ! isMounted ) {
					return;
				}

				setPopups(
					Array.isArray( response?.popups ) ? response.popups : []
				);
				setErrorMessage( '' );
			} )
			.catch( () => {
				if ( ! isMounted ) {
					return;
				}

				setPopups( [] );
				setErrorMessage(
					__(
						'There was a problem retrieving popup forms from Mailmojo.',
						'mailmojo'
					)
				);
			} )
			.finally( () => {
				if ( isMounted ) {
					setIsLoading( false );
				}
			} );

		return () => {
			isMounted = false;
		};
	}, [] );

	const popupOptions = useMemo(
		() => [
			{
				label: __( 'Select a popup…', 'mailmojo' ),
				value: '',
			},
			...popups.map( ( popup ) => ( {
				label: popup.name,
				value: String( popup.id ),
			} ) ),
		],
		[ popups ]
	);

	const onChangePopup = ( nextValue ) => {
		if ( ! nextValue ) {
			setAttributes( {
				popupId: undefined,
				popupUrl: '',
			} );
			return;
		}

		const nextPopup = popups.find(
			( popup ) => String( popup.id ) === String( nextValue )
		);
		if ( ! nextPopup ) {
			return;
		}

		setAttributes( {
			popupId: nextPopup.id,
			popupUrl: nextPopup.public_url,
		} );
	};

	const blockProps = useBlockProps( {
		className: 'mailmojo-popup-button wp-element-button',
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Popup settings', 'mailmojo' ) }
					initialOpen={ true }
				>
					{ isLoading ? <Spinner /> : null }
					{ errorMessage ? (
						<Notice status="warning" isDismissible={ false }>
							{ errorMessage }
						</Notice>
					) : null }
					<SelectControl
						label={ __( 'Popup form', 'mailmojo' ) }
						value={ popupId ? String( popupId ) : '' }
						options={ popupOptions }
						onChange={ onChangePopup }
						disabled={ isLoading || 0 === popups.length }
						help={
							0 === popups.length && ! isLoading
								? __(
										'No published popup forms were found in Mailmojo.',
										'mailmojo'
								  )
								: undefined
						}
					/>
				</PanelBody>
			</InspectorControls>
			<button { ...blockProps } type="button">
				{ __( 'Subscribe to our newsletter', 'mailmojo' ) }
			</button>
		</>
	);
}
