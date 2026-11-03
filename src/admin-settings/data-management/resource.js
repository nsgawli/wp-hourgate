import apiFetch from '@wordpress/api-fetch';
import { apiCache } from '@framework/middlewares';

export const getGeneralSettings = () => {
	return apiFetch({
		path: '/hourgate/v1/settings/general-settings',
		method: 'GET',
		useApiCache: true,
	});
}

export const updateGeneralSettings = (data) => {
	apiCache.clear('/hourgate/v1/settings/general-settings');
	return apiFetch({
		path: '/hourgate/v1/settings/general-settings',
		method: 'PUT',
		data,
	});
};

export const resetGeneralSettings = () => {
	return apiFetch({
		path: '/hourgate/v1/settings/general-settings/defaults',
		method: 'GET',
		useApiCache: true,
	});
};