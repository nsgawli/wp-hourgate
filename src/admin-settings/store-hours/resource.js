import apiFetch from '@wordpress/api-fetch';
import { apiCache } from '@framework/middlewares';

/**
 * The schedule REST routes (OW-017; contract in docs/EXTENSION-POINTS.md →
 * "REST routes (schedule)"). Responses are `{ success, data }`; these return
 * `data`. A rejected PUT rejects with WordPress's error object intact, so
 * `error.data.errors` (`[ { path, code, message } ]`) reaches the editor.
 */

const NS = '/hourgate/v1';
export const ROUTES = {
	schedule: `${NS}/settings/schedule`,
	closures: `${NS}/settings/closures`,
	preview: `${NS}/schedule/preview`,
};

const get = (path) =>
	apiFetch({ path, method: 'GET', useApiCache: true }).then(
		(response) => response?.data ?? response
	);

const put = (route, data) => {
	// Clear before and after: before, so no read during the save is served
	// from the old cache; after, so a read that started meanwhile can't
	// leave a stale answer (the middleware also refuses to cache it).
	const clear = () => {
		apiCache.clear(route);
		apiCache.clear(ROUTES.preview);
	};
	clear();
	return apiFetch({ path: route, method: 'PUT', data })
		.then((response) => response?.data ?? response)
		.finally(clear);
};

export const getSchedule = () => get(ROUTES.schedule);
export const getScheduleDefaults = () => get(`${ROUTES.schedule}/defaults`);
export const saveSchedule = (weekly) => put(ROUTES.schedule, { weekly });

export const getClosures = () => get(ROUTES.closures);
export const getClosuresDefaults = () => get(`${ROUTES.closures}/defaults`);
/**
 * Save closures and/or special hours; a key not sent keeps its stored value.
 *
 * @param {Object} data `{ closures?, specialHours? }`.
 * @return {Promise<Object>} The group as saved.
 */
export const saveClosures = (data) => put(ROUTES.closures, data);

/**
 * The preview is never cached: it depends on the current time.
 *
 * @param {number} days Dates from today, 1–60.
 * @return {Promise<Object>} The preview.
 */
export const getPreview = (days = 14) =>
	apiFetch({
		path: `${ROUTES.preview}?days=${Number(days)}`,
		method: 'GET',
	}).then((response) => response?.data ?? response);

/**
 * Server validation errors from a rejected save, as `{ path: message }`.
 * Anything else (network error, expired nonce, 403) gives `null`, and the
 * caller shows `error.message`.
 *
 * @param {*} error What apiFetch rejected with.
 * @return {Object|null} Messages by path, or null.
 */
export const validationErrors = (error) => {
	if (
		error?.code !== 'hourgate_invalid_schedule' ||
		!Array.isArray(error?.data?.errors)
	) {
		return null;
	}
	const byPath = {};
	error.data.errors.forEach(({ path, message }) => {
		if (typeof path === 'string' && !(path in byPath)) {
			byPath[path] = String(message ?? '');
		}
	});
	return byPath;
};

/**
 * Server validation errors from a rejected save, as a list with their codes
 * (`[ { path, code, message } ]`), for callers that word some codes
 * themselves. `null` for anything that isn't a validation error.
 *
 * @param {*} error What apiFetch rejected with.
 * @return {Array|null} The errors, or null.
 */
export const validationErrorList = (error) =>
	error?.code === 'hourgate_invalid_schedule' &&
	Array.isArray(error?.data?.errors)
		? error.data.errors.filter((e) => e && typeof e.path === 'string')
		: null;
