/**
 * The closed notice on the Cart and Checkout blocks (OW-048). PHP prints
 * `.hourgate-notice--closed` on these blocks whenever the cart has an
 * order to place: with the closed message and `data-blocked="1"` while
 * checkout is refused, hidden and empty otherwise. WooCommerce would put
 * "Please fix the following error before continuing" above the same message
 * sent as a cart error, so the shopper gets this notice instead.
 *
 * - The notice follows the status: shown with the current message while
 *   checkout is refused (also when the store closes after the page loaded),
 *   hidden as soon as it is allowed again. The mode 2 "scheduled for" notice
 *   is hidden while it is shown.
 * - The closed error (`hourgate_closed`, which WooCommerce uses as the
 *   notice id) is removed from the blocks' notices whenever a response brings
 *   it back, and so is WooCommerce's notice for a refused checkout (id
 *   `checkout`) when it carries the same message. The server's word wins:
 *   when the error arrives while the status still says open (Place order
 *   refused before the next status check), the notice is shown with its
 *   message and focused. PHP already left it out of the cart preloaded into
 *   the page.
 * - On the Checkout block, while refused, a hidden entry in WooCommerce's
 *   validation store (`wc/store/validation`) makes Place order stop before
 *   it sends anything, so WooCommerce has no error to show; the click moves
 *   focus to the notice instead. Place order reads "Ordering is closed"
 *   (`data-place-order-label`, WooCommerce's `placeOrderButtonLabel`
 *   checkout filter). Both are undone when checkout is allowed, so a
 *   checkout left open keeps working after the store opens.
 * - Without `wp.data` (or before WooCommerce registers its stores, retried
 *   for a few seconds) nothing of this happens and Place order is sent as
 *   before: the server refuses it. The server always decides.
 */

const ERROR_CODE = 'hourgate_closed';
const NOTICE_CONTEXTS = ['wc/cart', 'wc/checkout'];
const VALIDATION = 'wc/store/validation';
const KEY = 'hourgate-closed';
const NAMESPACE = 'hourgate';
const BUTTON = '.wc-block-components-checkout-place-order-button';
const TRIES = 50;
const TRY_EVERY = 200;

/**
 * `wp.data` once WooCommerce's validation store and the notices store are registered, or null.
 *
 * @return {Object|null} `wp.data`.
 */
function blocksData() {
	const data = window.wp && window.wp.data;
	try {
		return data && data.select(VALIDATION) && data.select('core/notices')
			? data
			: null;
	} catch (e) {
		return null;
	}
}

/**
 * Plain text of a notice's content: entities decoded, markup kept as text
 * (a textarea parses no elements).
 *
 * @param {*} content Notice content.
 * @return {string} Text.
 */
function noticeText(content) {
	const area = document.createElement('textarea');
	area.innerHTML = typeof content === 'string' ? content : '';
	return area.value.trim();
}

/**
 * Text to compare messages by: WordPress curls quotes in some copies of the
 * same message and not in others.
 *
 * @param {string} text Text.
 * @return {string} Comparable text.
 */
function comparable(text) {
	return text
		.replace(/[\u2018\u2019]/g, "'")
		.replace(/[\u201c\u201d]/g, '"')
		.replace(/\s+/g, ' ')
		.trim();
}

/**
 * Keep the closed notice, the blocks' notices and the Checkout block's Place order in step with the status.
 */
export function watchCheckout() {
	const notices = document.querySelectorAll(
		'.hourgate-notice--closed'
	);
	if (!notices.length) {
		return;
	}

	const isCheckout = !!document.querySelector(
		'.wp-block-woocommerce-checkout'
	);
	const label = notices[0].getAttribute('data-place-order-label') || '';
	// PHP said whether checkout is refused now.
	let blocked = notices[0].getAttribute('data-blocked') === '1';
	let data = null;
	let tries = 0;

	function focusNotice() {
		const notice = notices[0];
		notice.setAttribute('tabindex', '-1');
		notice.scrollIntoView({ block: 'center' });
		notice.focus({ preventScroll: true });
	}

	function showNotices(message) {
		notices.forEach((notice) => {
			// Not `hidden`: WooCommerce's banner CSS sets `display` and would win.
			notice.style.display = blocked ? '' : 'none';
			const content = notice.querySelector(
				'.wc-block-components-notice-banner__content'
			);
			if (
				blocked &&
				content &&
				message &&
				content.textContent !== message
			) {
				content.textContent = message;
			}
		});
		document
			.querySelectorAll('.hourgate-notice--scheduled')
			.forEach((notice) => {
				notice.style.display = blocked ? 'none' : '';
			});
	}

	// Our error's notice in the contexts the blocks show: removed, and its text returned (null when there was none).
	function takeErrorNotice() {
		let found = null;
		NOTICE_CONTEXTS.forEach((context) => {
			const notice = data
				.select('core/notices')
				.getNotices(context)
				.find((item) => item.id === ERROR_CODE);
			if (notice) {
				data.dispatch('core/notices').removeNotice(ERROR_CODE, context);
				found = { text: noticeText(notice.content), context };
			}
		});
		return found;
	}

	// WooCommerce's own notice for a refused checkout (id `checkout`) carrying our message: removed; whether there was one.
	function takeRefusal() {
		const ours = comparable(notices[0].textContent);
		const refusal = data
			.select('core/notices')
			.getNotices('wc/checkout')
			.find(
				(item) =>
					item.status === 'error' &&
					ours &&
					comparable(noticeText(item.content)) === ours
			);
		if (refusal) {
			data.dispatch('core/notices').removeNotice(
				refusal.id,
				'wc/checkout'
			);
		}
		return !!refusal;
	}

	// Whether the registered label filter relabels; nothing is registered until checkout is refused.
	let labelled = false;

	function relabel() {
		const checkout = window.wc && window.wc.blocksCheckout;
		if (
			!label ||
			labelled === blocked ||
			!checkout ||
			!checkout.registerCheckoutFilters
		) {
			return;
		}
		labelled = blocked;
		// Registering again clears WooCommerce's cached label.
		checkout.registerCheckoutFilters(NAMESPACE, {
			placeOrderButtonLabel: (value) => (blocked ? label : value),
		});
	}

	function syncValidation() {
		const has = !!data.select(VALIDATION).getValidationError(KEY);
		if (blocked && !has) {
			data.dispatch(VALIDATION).setValidationErrors({
				[KEY]: { message: notices[0].textContent, hidden: true },
			});
		} else if (!blocked && has) {
			data.dispatch(VALIDATION).clearValidationError(KEY);
		}
	}

	function syncBlocks() {
		if (!data) {
			return;
		}
		if (blocked) {
			takeErrorNotice();
		}
		if (isCheckout) {
			relabel();
			// Also makes WooCommerce draw the button again, with the new label.
			syncValidation();
		}
	}

	function onNotices() {
		const taken = takeErrorNotice();
		if (taken && !blocked) {
			// The server refused before the status said so: it decides.
			blocked = true;
			showNotices(taken.text);
			syncBlocks();
		}
		// A checkout the server refused (Place order on a page loaded while open, an express payment).
		if (
			blocked &&
			(takeRefusal() || (taken && taken.context === 'wc/checkout'))
		) {
			focusNotice();
		}
	}

	function start() {
		data = blocksData();
		if (!data) {
			if (++tries < TRIES) {
				window.setTimeout(start, TRY_EVERY);
			}
			return;
		}
		data.subscribe(onNotices);
		syncBlocks();
	}

	document.addEventListener('hourgate:status', (event) => {
		const status = event.detail;
		blocked = status.checkout === 'blocked';
		showNotices(typeof status.message === 'string' ? status.message : '');
		syncBlocks();
	});

	if (isCheckout) {
		// Capture phase: runs before WooCommerce's handler, which then stops on the validation entry.
		document.addEventListener(
			'click',
			(event) => {
				if (
					blocked &&
					event.target.closest &&
					event.target.closest(BUTTON)
				) {
					focusNotice();
				}
			},
			true
		);
	}

	start();
}
