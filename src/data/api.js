/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { REST_PATH } from './boot';

export const api = {
	list: () => apiFetch( { path: REST_PATH } ),
	activate: ( slug ) =>
		apiFetch( {
			path: `${ REST_PATH }/${ encodeURIComponent( slug ) }/activate`,
			method: 'POST',
		} ),
};

export const errorMessage = ( error ) =>
	error?.message ||
	__(
		'That did not work. Please reload the page and try again.',
		'lw-admin-hub'
	);
