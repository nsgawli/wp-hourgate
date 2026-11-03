/**
 * Closures: stored list ↔ list rows, the dialog's checks, and server error
 * placement. Pure functions, no React.
 *
 * The stored list is kept as it came from the server, in its stored order:
 * the PUT sends it back in that order with one entry replaced, added or
 * removed, so a server error path `closures.{index}` points at the entry the
 * dialog is editing. Rows are only a sorted view.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	isYmd,
	formatSiteDate,
	withoutYear,
	getSiteSettings,
} from '@framework/utils/siteTime';

export const MAX_CLOSURES = 200;
export const MAX_LABEL = 100;

const isObject = (value) =>
	value && typeof value === 'object' && !Array.isArray(value);

/**
 * Characters as people count them (code points), after trimming, like the server.
 *
 * @param {*} label The label.
 * @return {number} Its length.
 */
export const labelLength = (label) => [...String(label ?? '').trim()].length;

/**
 * Whether a stored entry can be shown and edited as a closure.
 *
 * @param {*} entry A stored entry.
 * @return {boolean} True when it's an object with real `from` and `to` dates.
 */
export const isReadable = (entry) =>
	isObject(entry) && isYmd(entry.from) && isYmd(entry.to);

/**
 * The dates of a closure for the list, in the site's date format.
 *
 * @param {Object} entry A readable entry.
 * @return {string} E.g. "December 24, 2026 – January 2, 2027" or, yearly, "Every year: December 24 – January 2".
 */
export const describeDates = (entry) => {
	if (entry.yearly === true) {
		const format = withoutYear(getSiteSettings().dateFormat);
		const from = formatSiteDate(entry.from, format);
		const to = formatSiteDate(entry.to, format);
		return sprintf(
			/* translators: %s: a date or a date range without the year, e.g. "December 24" or "December 24 – January 2". */
			__('Every year: %s', 'hourgate'),
			entry.from.slice(5) === entry.to.slice(5) && entry.from === entry.to
				? from
				: `${from} – ${to}`
		);
	}
	const from = formatSiteDate(entry.from);
	return entry.from === entry.to
		? from
		: `${from} – ${formatSiteDate(entry.to)}`;
};

/**
 * List rows for the stored closures, sorted: one-off entries by start date,
 * yearly entries by their next start from `today`.
 *
 * @param {Array}  list  The stored closures.
 * @param {string} today The store's today, `Y-m-d`.
 * @return {Array} Rows for ExceptionList, each with the stored `index`.
 */
export const toRows = (list, today) => {
	const todayMd = today.slice(5);
	return list
		.map((entry, index) => {
			if (!isReadable(entry)) {
				return {
					key: `i${index}`,
					index,
					unreadable: true,
					dates: __('Unreadable closure', 'hourgate'),
					label:
						isObject(entry) && typeof entry.label === 'string'
							? entry.label
							: '',
					notes: [
						__(
							'The saved dates can’t be read. Edit or delete it.',
							'hourgate'
						),
					],
					sort: '',
				};
			}
			const yearly = entry.yearly === true;
			const notes = [];
			if (
				yearly &&
				(entry.from.slice(5) === '02-29' ||
					entry.to.slice(5) === '02-29')
			) {
				notes.push(
					__(
						'29 February applies only in leap years.',
						'hourgate'
					)
				);
			}
			const md = entry.from.slice(5);
			return {
				key: `i${index}`,
				index,
				unreadable: false,
				past: !yearly && entry.to < today,
				dates: describeDates(entry),
				label: typeof entry.label === 'string' ? entry.label : '',
				notes,
				sort: yearly
					? `${md >= todayMd ? today.slice(0, 4) : String(+today.slice(0, 4) + 1)}-${md}`
					: entry.from,
			};
		})
		.sort((a, b) => a.sort.localeCompare(b.sort) || a.index - b.index);
};

/**
 * Dialog fields for a stored entry (or a new one starting on `today`).
 *
 * @param {*}      entry The stored entry, or null.
 * @param {string} today The store's today, `Y-m-d`.
 * @return {Object} Dialog fields.
 */
export const toForm = (entry, today) => {
	const source = isObject(entry) ? entry : {};
	const from = typeof source.from === 'string' ? source.from : today;
	const to = typeof source.to === 'string' ? source.to : from;
	return {
		label: typeof source.label === 'string' ? source.label : '',
		from,
		to,
		yearly: source.yearly === true,
		oneDay: from === to,
	};
};

/**
 * The "Last closed date" after "First closed date" changes: it follows the
 * new first date while it was still equal to the old one (so the calendar for
 * it opens in the same month) or would end before it. A last date the person
 * already moved past the first date is kept.
 *
 * @param {Object} form Dialog fields before the change.
 * @param {string} from The new first date, `Y-m-d` or ''.
 * @return {string} The last date to use.
 */
export const toAfterFromChange = (form, from) => {
	if (!isYmd(from)) {
		return form.to;
	}
	return form.to === form.from || !isYmd(form.to) || form.to < from
		? from
		: form.to;
};

/**
 * The entry to store for dialog fields, keeping the stored id.
 *
 * @param {Object} form  Dialog fields.
 * @param {*}      entry The stored entry, or null.
 * @return {Object} The entry to store.
 */
export const fromForm = (form, entry) => {
	const out = {
		label: form.label.trim(),
		from: form.from,
		to: form.oneDay ? form.from : form.to,
		yearly: form.yearly,
	};
	if (isObject(entry) && typeof entry.id === 'string' && entry.id !== '') {
		out.id = entry.id;
	}
	return out;
};

/**
 * Checks in the dialog before saving; the server decides the rest (the
 * yearly length, ids).
 *
 * @param {Object} form Dialog fields.
 * @return {Object} Messages keyed `label`, `from`, `to`.
 */
export const validateForm = (form) => {
	const errors = {};
	if (labelLength(form.label) > MAX_LABEL) {
		errors.label = sprintf(
			/* translators: %d: the most characters allowed. */
			__('Use at most %d characters.', 'hourgate'),
			MAX_LABEL
		);
	}
	if (!isYmd(form.from)) {
		errors.from = form.from
			? __('Enter a valid date.', 'hourgate')
			: __('Choose the first closed date.', 'hourgate');
	}
	if (!form.oneDay) {
		if (!isYmd(form.to)) {
			errors.to = form.to
				? __('Enter a valid date.', 'hourgate')
				: __('Choose the last closed date.', 'hourgate');
		} else if (isYmd(form.from) && form.to < form.from) {
			errors.to = __(
				'The last date must be on or after the first date.',
				'hourgate'
			);
		}
	}
	return errors;
};

/**
 * Server errors for the entry at `index`, keyed by dialog field, plus
 * anything else (another entry, the whole list) as general messages.
 *
 * @param {Object} byPath Messages keyed by server path.
 * @param {number} index  The edited entry's index in the list that was sent.
 * @param {string} group  `closures` or `specialHours`.
 * @return {{fields: Object, general: string[]}} Where each message goes.
 */
export const placeErrors = (byPath, index, group = 'closures') => {
	const fields = {};
	const general = [];
	const prefix = `${group}.${index}`;
	Object.entries(byPath).forEach(([path, message]) => {
		if (path === prefix) {
			general.push(message);
		} else if (path.startsWith(`${prefix}.`)) {
			const field = path.slice(prefix.length + 1).split('.')[0];
			if (field in fields) {
				return;
			}
			fields[field] = message;
		} else {
			general.push(message);
		}
	});
	return { fields, general };
};
