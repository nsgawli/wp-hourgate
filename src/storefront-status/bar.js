/**
 * The notice bar (OW-038): fills the empty `.hourgate-notice-bar`
 * container PHP printed, from the status, while ordering is closed or paused.
 *
 * - Shown when the status has `bar: true`, `state` is not `open`, there is a
 *   `message`, and the shopper hasn't dismissed this `version`.
 * - The message goes in with `textContent` (it is plain text), next to a
 *   Dismiss button whose label PHP printed in `data-dismiss` (translated).
 * - Dismissal is kept in `sessionStorage` as the dismissed `version`, a hash
 *   of the state and the message: a new state or an edited message shows the
 *   bar again. Storage that is blocked just means the bar comes back on the
 *   next page.
 * - Hidden again (and emptied) as soon as the status says open, or `bar` is
 *   false (the owner turned it off after this page was cached).
 */

const KEY = 'hourgate-bar-dismissed';

function dismissedVersion() {
	try {
		return window.sessionStorage.getItem(KEY);
	} catch (e) {
		return null;
	}
}

function rememberDismissed(version) {
	try {
		window.sessionStorage.setItem(KEY, version);
	} catch (e) {
		// Blocked or full: dismissed for this page only.
	}
}

/**
 * Keep the page's notice bar in step with the status.
 */
export function watchBar() {
	const bar = document.querySelector('.hourgate-notice-bar');
	if (!bar) {
		return;
	}
	// A version dismissed on this page, for when storage can't remember it.
	let dismissedHere = null;

	function hide() {
		bar.hidden = true;
		bar.textContent = '';
	}

	function apply(status) {
		const show =
			status.bar === true &&
			status.state !== 'open' &&
			typeof status.message === 'string' &&
			status.message !== '' &&
			status.version !== dismissedHere &&
			status.version !== dismissedVersion();
		if (!show) {
			hide();
			return;
		}
		const text = document.createElement('span');
		text.textContent = status.message;
		const button = document.createElement('button');
		button.type = 'button';
		button.textContent = bar.getAttribute('data-dismiss') || 'Dismiss';
		button.addEventListener('click', () => {
			dismissedHere = status.version;
			rememberDismissed(status.version);
			hide();
		});
		// One replace: no flash of an empty bar, and no stale text.
		bar.replaceChildren(text, button);
		bar.hidden = false;
	}

	document.addEventListener('hourgate:status', (event) =>
		apply(event.detail)
	);
}
