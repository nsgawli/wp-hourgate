/**
 * The "Store status" block and shortcode (OW-040): PHP prints the current
 * text in `.hourgate-store-status` with the templates in `data-`
 * attributes; this replaces the text (with `textContent`) and the state
 * class whenever a status arrives, so a page served from a page cache is
 * corrected within a minute.
 */

import { statusText } from './status-text';

/**
 * Keep every status element on the page in step with the status.
 */
export function watchStatusBlocks() {
	const elements = document.querySelectorAll('.hourgate-store-status');
	if (!elements.length) {
		return;
	}

	document.addEventListener('hourgate:status', (event) => {
		const status = event.detail;
		const open = status.state === 'open';
		elements.forEach((element) => {
			const text = statusText(status, {
				open: element.getAttribute('data-open') || '',
				closed: element.getAttribute('data-closed') || '',
				openFallback: element.getAttribute('data-open-fallback') || '',
				later: element.getAttribute('data-later') || '',
			});
			if (element.textContent !== text) {
				element.textContent = text;
			}
			element.setAttribute('data-state', status.state);
			element.classList.toggle('is-open', open);
			element.classList.toggle('is-closed', !open);
		});
	});
}
