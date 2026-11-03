/**
 * HourGate storefront status (OW-037).
 *
 * Reads the public `GET /status` route and hands the answer to everything on
 * the page that shows the store's state (the notice bar, the status and
 * hours blocks, catalogue buttons), so a page served from a page cache still
 * shows the current state. Vanilla JS on purpose: no `@wordpress/*` import,
 * no script dependency on the storefront.
 *
 * - One fetch per page. The answer is kept in `sessionStorage` for 60 s, so
 *   browsing pages within a minute costs no request at all.
 * - Re-checked on focus or when the tab becomes visible once 60 s have
 *   passed, and every 60 s while visible; nothing runs while hidden.
 * - Each answer is announced as a `hourgate:status` event on
 *   `document` (`event.detail` is the response's `data`) and kept in
 *   `window.hourGateStorefront.status` for listeners added later.
 * - The notice bar container PHP printed is filled while closed or paused
 *   (OW-038, `bar.js`).
 * - The status block and shortcode text follows the status (OW-040,
 *   `status-block.js`).
 * - The hours table follows `hours` (OW-041, `hours-block.js`).
 * - In catalogue mode the add-to-cart controls follow `addToCart` (OW-039,
 *   `buttons.js`).
 * - The closed notice on the Cart and Checkout blocks, and Place order
 *   while checkout is refused, follow `checkout` (OW-048, `checkout.js`).
 * - A failed fetch (offline, 5xx, blocked, malformed) changes nothing on the
 *   page and is tried again after 60 s. The server still decides every
 *   add-to-cart and checkout, so a stale page can mislead but never lets an
 *   order through.
 */

import { watchBar } from './bar';
import { watchButtons } from './buttons';
import { watchStatusBlocks } from './status-block';
import { watchHoursBlocks } from './hours-block';
import { watchCheckout } from './checkout';

const EVENT = 'hourgate:status';
const MAX_AGE = 60 * 1000;
const STATES = ['open', 'closed', 'paused'];
const STORAGE_PREFIX = 'hourgate-status:';

const settings = window.hourGateStorefront;

/**
 * Whether a value looks like the route's `data`.
 *
 * @param {*} data Value.
 * @return {boolean} Valid.
 */
function isStatus(data) {
	return (
		!!data &&
		typeof data === 'object' &&
		STATES.indexOf(data.state) !== -1 &&
		typeof data.version === 'string'
	);
}

/**
 * The status script.
 *
 * @param {Object} config `{ url }` from PHP.
 */
function start(config) {
	const key = STORAGE_PREFIX + config.url;
	// When the last request was made (success or failure), for the 60 s rhythm.
	let checkedAt = 0;
	let inFlight = false;
	let timer = null;

	function readCache() {
		try {
			const entry = JSON.parse(window.sessionStorage.getItem(key));
			const age = Date.now() - (entry && entry.at);
			return entry && isStatus(entry.data) && age >= 0 && age < MAX_AGE
				? entry
				: null;
		} catch (e) {
			// Storage blocked or throwing (private mode, sandboxed frames): fetch instead.
			return null;
		}
	}

	function writeCache(entry) {
		try {
			window.sessionStorage.setItem(key, JSON.stringify(entry));
		} catch (e) {
			// Storage full or blocked: this page still works, the next one fetches.
		}
	}

	function publish(data) {
		config.status = data;
		document.dispatchEvent(new window.CustomEvent(EVENT, { detail: data }));
	}

	function schedule() {
		window.clearTimeout(timer);
		timer = null;
		if (document.hidden) {
			return;
		}
		timer = window.setTimeout(
			refreshIfStale,
			Math.max(0, checkedAt + MAX_AGE - Date.now())
		);
	}

	function load() {
		if (inFlight) {
			return;
		}
		inFlight = true;
		checkedAt = Date.now();
		window
			.fetch(config.url, {
				credentials: 'omit',
				cache: 'no-store',
				headers: { Accept: 'application/json' },
			})
			.then((response) => (response.ok ? response.json() : null))
			.then((body) => {
				const data = body && body.success ? body.data : null;
				if (isStatus(data)) {
					writeCache({ at: Date.now(), data });
					publish(data);
				}
			})
			.catch(() => {
				// Offline, blocked or not JSON: leave the page as it is.
			})
			.then(() => {
				inFlight = false;
				schedule();
			});
	}

	function refreshIfStale() {
		if (document.hidden) {
			return;
		}
		if (Date.now() - checkedAt >= MAX_AGE) {
			load();
		} else {
			schedule();
		}
	}

	document.addEventListener('visibilitychange', () =>
		document.hidden ? schedule() : refreshIfStale()
	);
	window.addEventListener('focus', refreshIfStale);

	// The Cart and Checkout blocks' closed notice holds Place order on the status: a remembered answer
	// could be up to 60 s older than the page PHP just rendered, so these pages always ask (OW-048).
	const cached = document.querySelector('.hourgate-notice--closed')
		? null
		: readCache();
	if (cached) {
		checkedAt = cached.at;
		publish(cached.data);
		schedule();
	} else {
		load();
	}
}

if (
	settings &&
	typeof settings.url === 'string' &&
	typeof window.fetch === 'function' &&
	!settings.started
) {
	settings.started = true;
	// Listening before the first status, which a fresh memo publishes at once.
	watchButtons(settings);
	watchBar();
	watchStatusBlocks();
	watchHoursBlocks();
	watchCheckout();
	start(settings);
}
