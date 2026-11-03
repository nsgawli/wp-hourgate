/**
 * Catalogue mode (OW-039): while the status says `addToCart: 'blocked'`,
 * every add-to-cart control on the page is disabled; it is restored when the
 * store reopens. Prices and the product links stay.
 *
 * - **Disabled** means `aria-disabled="true"`, the `hourgate-closed`
 *   class, the label from the response ("Closed") as its visible text and
 *   accessible name, and clicks, Enter, Space and form submits cancelled.
 *   It stays focusable, so keyboard and screen-reader users hear why.
 * - The label replaces the text of the control's own text nodes; no node is
 *   removed or added. The Product Button block's text belongs to the
 *   Interactivity API, which keeps working on the same nodes.
 * - Controls rendered later (the All Products block, other scripts) and text
 *   set back by their owners are caught by a MutationObserver that runs only
 *   while add-to-cart is blocked.
 * - Links that only lead to a product page (variable "Select options",
 *   grouped "View products") and external products' "Buy" links are not
 *   add-to-cart controls and are never touched.
 *
 * The server still rejects every add-to-cart while blocked (OW-030): this is
 * only what the shopper sees.
 */

const CLASS = 'hourgate-closed';
const SELECTOR = [
	// Classic loop links and buttons, older grid blocks, the Product Button block.
	'.add_to_cart_button',
	// Product Button block and the All Products block.
	'.wc-block-components-product-button__button',
	// The single product form.
	'form.cart .single_add_to_cart_button',
].join(',');
const KEYS = ['Enter', ' ', 'Spacebar'];

/**
 * Whether a matched element adds to the cart.
 *
 * @param {Element} el Element matching `SELECTOR`.
 * @return {boolean} An add-to-cart control.
 */
function isControl(el) {
	if (el.classList.contains('single_add_to_cart_button')) {
		// Simple, variable and grouped forms post an `add-to-cart` field; an external product's form only links out.
		const form = el.closest('form.cart');
		return (
			!!form &&
			(el.name === 'add-to-cart' ||
				!!form.querySelector('[name="add-to-cart"]'))
		);
	}
	// Loop and block buttons add; a link adds only when it points at `add-to-cart=`.
	return (
		el.tagName === 'BUTTON' ||
		/[?&]add-to-cart=/.test(el.getAttribute('href') || '')
	);
}

/**
 * The control an event is for, or null.
 *
 * @param {Event} event Event.
 * @return {Element|null} Control.
 */
function controlOf(event) {
	const target = event.target;
	const el =
		target && typeof target.closest === 'function'
			? target.closest(SELECTOR)
			: null;
	return el && isControl(el) ? el : null;
}

/**
 * Text nodes inside an element, in document order.
 *
 * @param {Element} el Element.
 * @return {Text[]} Nodes.
 */
function textNodes(el) {
	const walker = document.createTreeWalker(el, window.NodeFilter.SHOW_TEXT);
	const nodes = [];
	while (walker.nextNode()) {
		nodes.push(walker.currentNode);
	}
	return nodes;
}

/**
 * Watch the page's status and keep its add-to-cart controls in step.
 *
 * @param {Object} config `window.hourGateStorefront`.
 */
export function watchButtons(config) {
	// Element → its original attributes; Text → its original text.
	const originals = new WeakMap();
	let label = null;
	let blocked = false;
	let observer = null;

	function setAttr(el, name, value) {
		if (value === null) {
			el.removeAttribute(name);
		} else if (el.getAttribute(name) !== value) {
			el.setAttribute(name, value);
		}
	}

	function disable(el) {
		if (!originals.has(el)) {
			originals.set(el, {
				ariaLabel: el.getAttribute('aria-label'),
				ariaDisabled: el.getAttribute('aria-disabled'),
			});
			// Appended as text, not with classList (which would also tidy the theme's own spacing),
			// so the restored attribute is the original byte for byte.
			el.setAttribute(
				'class',
				(el.getAttribute('class') || '') + ' ' + CLASS
			);
		}
		setAttr(el, 'aria-disabled', 'true');
		if (!label) {
			return;
		}
		setAttr(el, 'aria-label', label);
		// The label goes into the first text node with text; the others are emptied.
		let placed = false;
		textNodes(el).forEach((node) => {
			if (!originals.has(node)) {
				if (!node.data.trim()) {
					return;
				}
				originals.set(node, node.data);
			}
			const text = placed ? '' : label;
			placed = true;
			if (node.data !== text) {
				node.data = text;
			}
		});
	}

	function restore(el) {
		const saved = originals.get(el);
		if (!saved) {
			return;
		}
		textNodes(el).forEach((node) => {
			if (originals.has(node)) {
				node.data = originals.get(node);
				originals.delete(node);
			}
		});
		setAttr(el, 'aria-label', saved.ariaLabel);
		setAttr(el, 'aria-disabled', saved.ariaDisabled);
		const classes = el.getAttribute('class') || '';
		if (classes.slice(-CLASS.length - 1) === ' ' + CLASS) {
			setAttr(el, 'class', classes.slice(0, -CLASS.length - 1) || null);
		} else {
			// Another script rewrote the class meanwhile: keep its changes.
			el.classList.remove(CLASS);
		}
		originals.delete(el);
	}

	function update() {
		document.querySelectorAll(SELECTOR).forEach((el) => {
			if (blocked && isControl(el)) {
				disable(el);
			} else {
				restore(el);
			}
		});
	}

	function apply(status) {
		blocked = status.addToCart === 'blocked';
		label =
			typeof status.addToCartLabel === 'string' && status.addToCartLabel
				? status.addToCartLabel
				: null;
		if (observer) {
			observer.disconnect();
			observer = null;
		}
		update();
		if (blocked && typeof window.MutationObserver === 'function') {
			// Each batch of changes costs one pass; the pass changes only what differs, so it settles.
			observer = new window.MutationObserver(update);
			observer.observe(document.body, {
				childList: true,
				subtree: true,
				characterData: true,
			});
		}
	}

	function cancel(event) {
		event.preventDefault();
		event.stopImmediatePropagation();
	}

	// Capture on window: before WooCommerce's and the Interactivity API's own handlers.
	window.addEventListener(
		'click',
		(event) => {
			if (blocked && controlOf(event)) {
				cancel(event);
			}
		},
		true
	);
	window.addEventListener(
		'keydown',
		(event) => {
			if (blocked && KEYS.indexOf(event.key) !== -1 && controlOf(event)) {
				cancel(event);
			}
		},
		true
	);
	window.addEventListener(
		'submit',
		(event) => {
			const form = event.target;
			if (
				blocked &&
				form &&
				typeof form.matches === 'function' &&
				form.matches('form.cart') &&
				form.querySelector('[name="add-to-cart"]')
			) {
				cancel(event);
			}
		},
		true
	);

	document.addEventListener('hourgate:status', (event) =>
		apply(event.detail)
	);
	if (config.status) {
		apply(config.status);
	}
}
