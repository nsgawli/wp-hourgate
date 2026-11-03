/**
 * The "Opening hours" block and shortcode (OW-041): PHP prints the table;
 * this rebuilds the rows and the closures list from `status.hours` whenever
 * a status with hours arrives, so a page served from a page cache shows the
 * saved hours and the right "today". Text goes in with `textContent` only.
 */

/**
 * Add an element with plain text.
 *
 * @param {string}      tag  Tag name.
 * @param {string}      text Text.
 * @param {HTMLElement} into Parent.
 * @return {HTMLElement} The new element.
 */
function add(tag, text, into) {
	const element = document.createElement(tag);
	element.textContent = text;
	into.appendChild(element);
	return element;
}

/**
 * One closures-list line, as `HoursTable::closure_text()` writes it.
 *
 * @param {Object} entry          An `upcoming` entry.
 * @param {Object} words          Separators and the word for closed.
 * @param {string} words.dateSep
 * @param {string} words.hoursSep
 * @param {string} words.closed
 * @return {string} Text.
 */
function closureText(entry, words) {
	const label = entry.label || '';
	let what;
	if (entry.type === 'closure') {
		what = label === '' ? words.closed : label;
	} else {
		const hours = entry.hours || '';
		what = label === '' ? hours : label + words.hoursSep + hours;
	}
	return entry.dates + words.dateSep + what;
}

function fill(root, hours) {
	const body = root.querySelector('tbody');
	if (!body || !Array.isArray(hours.days)) {
		return;
	}

	const rows = [];
	hours.days.forEach((day) => {
		const row = document.createElement('tr');
		row.setAttribute('data-day', day.day);
		if (day.today) {
			row.className = 'is-today';
			row.setAttribute('aria-current', 'date');
		}
		const head = add('th', day.name, row);
		head.setAttribute('scope', 'row');
		add('td', day.label, row);
		rows.push(row);
	});

	// Replace only when something changed, so a screen reader isn't disturbed every minute.
	const next = rows.map((row) => row.outerHTML).join('');
	if (body.innerHTML !== next) {
		body.replaceChildren(...rows);
	}

	const box = root.querySelector('.hourgate-hours__closures');
	if (!box || root.getAttribute('data-closures') !== '1') {
		return;
	}
	const list = box.querySelector('ul');
	const words = {
		dateSep: root.getAttribute('data-date-sep') || ': ',
		hoursSep: root.getAttribute('data-hours-sep') || ' · ',
		closed: root.getAttribute('data-closed') || '',
	};
	const items = (Array.isArray(hours.upcoming) ? hours.upcoming : []).map(
		(entry) => closureText(entry, words)
	);
	if (
		items.join('\n') !==
		Array.from(list.children)
			.map((li) => li.textContent)
			.join('\n')
	) {
		list.replaceChildren();
		items.forEach((text) => add('li', text, list));
	}
	box.hidden = items.length === 0;
}

/**
 * Keep every hours table on the page in step with the status.
 */
export function watchHoursBlocks() {
	const elements = document.querySelectorAll('.hourgate-hours');
	if (!elements.length) {
		return;
	}

	document.addEventListener('hourgate:status', (event) => {
		const hours = event.detail && event.detail.hours;
		if (!hours) {
			return;
		}
		elements.forEach((element) => fill(element, hours));
	});
}
