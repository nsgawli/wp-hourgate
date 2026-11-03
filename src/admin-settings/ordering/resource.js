import apiFetch from '@wordpress/api-fetch';
import { apiCache } from '@framework/middlewares';

/**
 * The ordering routes (OW-027, OW-032, OW-033; contract in
 * docs/EXTENSION-POINTS.md). Responses are `{ success, data }`; these return
 * `data`. A rejected request rejects with WordPress's error object intact.
 */

const NS = '/hourgate/v1';
export const ROUTES = {
	behaviour: `${NS}/settings/behaviour`,
	status: `${NS}/ordering/status`,
	pause: `${NS}/pause`,
};

const data = (response) => response?.data ?? response;

export const getBehaviour = () =>
	apiFetch({ path: ROUTES.behaviour, method: 'GET', useApiCache: true }).then(
		data
	);

/**
 * Save the behaviour fields together.
 *
 * @param {Object} values `{ enforce, closedMode, closedMessage, noticeBar, noticeBarScope }`.
 * @return {Promise<Object>} The group as saved.
 */
export const saveBehaviour = (values) => {
	// Before and after, as the Store hours resource does.
	const clear = () => apiCache.clear(ROUTES.behaviour);
	clear();
	return apiFetch({ path: ROUTES.behaviour, method: 'PUT', data: values })
		.then(data)
		.finally(clear);
};

/** The status is never cached: it depends on the current time. */
export const getStatus = () =>
	apiFetch({ path: ROUTES.status, method: 'GET' }).then(data);

/**
 * Start a pause now.
 *
 * @param {Object} body `{ mode, minutes?, until? }`.
 * @return {Promise<Object>} The pause.
 */
export const startPause = (body) =>
	apiFetch({ path: ROUTES.pause, method: 'POST', data: body }).then(data);

export const resumeOrdering = () =>
	apiFetch({ path: ROUTES.pause, method: 'DELETE' }).then(data);

/**
 * Per-field messages from a 400 `rest_invalid_param` (`{ closedMessage: '…' }`),
 * or null for any other error.
 *
 * @param {*} error What apiFetch rejected with.
 * @return {Object|null} Messages by field, or null.
 */
export const fieldErrors = (error) => {
	const params = error?.data?.params;
	if (
		error?.code !== 'rest_invalid_param' ||
		!params ||
		typeof params !== 'object'
	) {
		return null;
	}
	const out = {};
	Object.entries(params).forEach(([key, message]) => {
		out[key] = String(message ?? '');
	});
	return out;
};
