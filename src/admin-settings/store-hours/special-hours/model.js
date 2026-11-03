/**
 * Special hours: stored list ↔ list rows, the dialog's checks, server error
 * placement, and converting a "closed" entry into a closure. Pure functions.
 *
 * Like closures, the list is kept in stored order and sent back in that
 * order, so `specialHours.{index}` in a server error is the edited entry.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	isYmd,
	formatSiteDate,
	formatSiteTime,
	withoutYear,
	getSiteSettings,
	toMinutes,
} from '@framework/utils/siteTime';
import { validateRanges } from '../ranges';
import { labelLength, MAX_LABEL } from '../closures/model';

export const MAX_SPECIAL_HOURS = 400;
export const MAX_RANGES = 24;
export const DEFAULT_RANGES = [{ start: '09:00', end: '17:00' }];
const FULL_DAY = [{ start: '00:00', end: '24:00' }];

const isObject = (value) =>
	value && typeof value === 'object' && !Array.isArray(value);

/**
 * Whether a stored entry can be shown and edited.
 *
 * @param {*} entry A stored entry.
 * @return {boolean} True for an object with a real `date` and a `ranges` list.
 */
export const isReadable = (entry) =>
	isObject(entry) && isYmd(entry.date) && Array.isArray(entry.ranges);

/**
 * Whether ranges are exactly one 00:00–24:00 range.
 *
 * @param {Array} ranges The ranges.
 * @return {boolean} True for a full day.
 */
export const isFullDay = (ranges) =>
	Array.isArray(ranges) &&
	ranges.length === 1 &&
	ranges[0]?.start === '00:00' &&
	ranges[0]?.end === '24:00';

/**
 * The date of an entry in the site's format; yearly entries without the year.
 *
 * @param {Object} entry A readable entry.
 * @return {string} E.g. "December 24, 2031" or "Every year: December 24".
 */
export const describeDate = (entry) =>
	entry.yearly === true
		? sprintf(
				/* translators: %s: a date or a date range without the year, e.g. "December 24" or "December 24 – January 2". */
				__('Every year: %s', 'hourgate'),
				formatSiteDate(
					entry.date,
					withoutYear(getSiteSettings().dateFormat)
				)
			)
		: formatSiteDate(entry.date);

/**
 * Ranges as text in the site's time format.
 *
 * @param {Array} ranges The ranges (readable).
 * @return {string} E.g. "10:00 am – 2:00 pm, 10:00 pm – 2:00 am the next day".
 */
export const describeRanges = (ranges) => {
	if (isFullDay(ranges)) {
		return __('Open 24 hours', 'hourgate');
	}
	return ranges
		.map((range) => {
			const e = toMinutes(range?.end);
			const start = formatSiteTime(range?.start);
			if (e === 0 || e === 1440) {
				/* translators: %s: opening time. */
				return sprintf(__('%s – midnight', 'hourgate'), start);
			}
			const s = toMinutes(range?.start);
			if (s !== null && e !== null && e < s) {
				return sprintf(
					/* translators: 1: opening time; 2: closing time on the next day. */
					__('%1$s – %2$s the next day', 'hourgate'),
					start,
					formatSiteTime(range.end)
				);
			}
			return sprintf(
				/* translators: 1: opening time; 2: closing time. */
				__('%1$s – %2$s', 'hourgate'),
				start,
				formatSiteTime(range?.end)
			);
		})
		.join(', ');
};

/**
 * List rows for the stored special hours, sorted like closures: one-off
 * entries by date, yearly entries by their next date from `today`.
 *
 * @param {Array}    list      The stored special hours.
 * @param {string}   today     The store's today, `Y-m-d`.
 * @param {Function} onConvert Called with a stored index, for entries with no ranges.
 * @return {Array} Rows for ExceptionList, each with the stored `index`.
 */
export const toRows = (list, today, onConvert) => {
	const todayMd = today.slice(5);
	return list
		.map((entry, index) => {
			if (!isReadable(entry)) {
				return {
					key: `i${index}`,
					index,
					unreadable: true,
					dates: __('Unreadable special hours', 'hourgate'),
					label:
						isObject(entry) && typeof entry.label === 'string'
							? entry.label
							: '',
					notes: [
						__(
							'The saved date or hours can’t be read. Edit or delete it.',
							'hourgate'
						),
					],
					sort: '',
				};
			}
			const yearly = entry.yearly === true;
			const md = entry.date.slice(5);
			const dates = describeDate(entry);
			const closed = entry.ranges.length === 0;
			const notes = [
				closed
					? __(
							'Closed, but the night before isn’t cut at midnight.',
							'hourgate'
						)
					: describeRanges(entry.ranges),
			];
			if (yearly && md === '02-29') {
				notes.push(
					__(
						'29 February applies only in leap years.',
						'hourgate'
					)
				);
			}
			const yearOfNext =
				md >= todayMd
					? today.slice(0, 4)
					: String(+today.slice(0, 4) + 1);
			return {
				key: `i${index}`,
				index,
				unreadable: false,
				past: !yearly && entry.date < today,
				dates,
				label: typeof entry.label === 'string' ? entry.label : '',
				notes,
				sort: yearly ? `${yearOfNext}-${md}` : entry.date,
				action: closed
					? {
							label: __(
								'Convert to a closure',
								'hourgate'
							),
							ariaLabel: sprintf(
								/* translators: %s: the entry's date. */
								__(
									'Convert the special hours on %s to a closure',
									'hourgate'
								),
								dates
							),
							onClick: () => onConvert(index),
						}
					: null,
			};
		})
		.sort((a, b) => a.sort.localeCompare(b.sort) || a.index - b.index);
};

/**
 * Dialog fields for a stored entry, or a new one on `today` with 09:00–17:00.
 *
 * @param {*}      entry The stored entry, or null.
 * @param {string} today The store's today, `Y-m-d`.
 * @return {Object} Dialog fields.
 */
export const toForm = (entry, today) => {
	const source = isObject(entry) ? entry : null;
	const ranges = Array.isArray(source?.ranges) ? source.ranges : null;
	return {
		label: typeof source?.label === 'string' ? source.label : '',
		date: typeof source?.date === 'string' ? source.date : today,
		yearly: source?.yearly === true,
		open24: isFullDay(ranges),
		ranges:
			source === null
				? DEFAULT_RANGES.map((r) => ({ ...r }))
				: (ranges && !isFullDay(ranges) ? ranges : []).map((r) =>
						isObject(r) ? { ...r } : r
					),
	};
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
		date: form.date,
		yearly: form.yearly,
		ranges: form.open24
			? FULL_DAY.map((r) => ({ ...r }))
			: form.ranges.map((r) => ({ start: r?.start, end: r?.end })),
	};
	if (isObject(entry) && typeof entry.id === 'string' && entry.id !== '') {
		out.id = entry.id;
	}
	return out;
};

/**
 * Checks in the dialog before saving.
 *
 * @param {Object} form Dialog fields.
 * @return {{fields: Object, ranges: Object}} Messages for `label` / `date` /
 *                                            `ranges`, and per range (TimeRangesEditor keys).
 */
export const validateForm = (form) => {
	const fields = {};
	if (labelLength(form.label) > MAX_LABEL) {
		fields.label = sprintf(
			/* translators: %d: the most characters allowed. */
			__('Use at most %d characters.', 'hourgate'),
			MAX_LABEL
		);
	}
	if (!isYmd(form.date)) {
		fields.date = form.date
			? __('Enter a valid date.', 'hourgate')
			: __('Choose a date.', 'hourgate');
	}
	if (!form.open24 && form.ranges.length === 0) {
		fields.ranges = __(
			'Add at least one time range, or choose Open 24 hours. To close for the whole day, add a closure instead.',
			'hourgate'
		);
	}
	return { fields, ranges: form.open24 ? {} : validateRanges(form.ranges) };
};

/**
 * The other entry an edited one would clash with on its date (one per date;
 * a yearly entry clashes by month and day).
 *
 * @param {Array}   list   The stored special hours.
 * @param {number}  index  The edited entry's index (ignored in the search).
 * @param {string}  date   Its date.
 * @param {boolean} yearly Whether it repeats yearly.
 * @return {Object|null} The clashing entry.
 */
export const findClash = (list, index, date, yearly) =>
	list.find(
		(other, i) =>
			i !== index &&
			isReadable(other) &&
			(yearly || other.yearly === true
				? other.date.slice(5) === date.slice(5)
				: other.date === date)
	) || null;

/**
 * Server errors for the entry at `index`, placed for the dialog.
 * `duplicate_date` is reworded to name the clashing entry, because the
 * server's message refers to positions in a list the merchant sees sorted.
 *
 * @param {Array}  errors `[ { path, code, message } ]` from the server.
 * @param {number} index  The edited entry's index in the list that was sent.
 * @param {Array}  sent   The list that was sent.
 * @return {{fields: Object, ranges: Object, general: string[]}} Where each message goes.
 */
export const placeErrors = (errors, index, sent) => {
	const fields = {};
	const ranges = {};
	const general = [];
	const prefix = `specialHours.${index}`;
	errors.forEach(({ path, code, message }) => {
		if (!path.startsWith(`${prefix}.`)) {
			general.push(message);
			return;
		}
		const rest = path.slice(prefix.length + 1).split('.');
		if (rest[0] === 'ranges' && rest.length > 1) {
			const key = rest.slice(1).join('.');
			ranges[key] = ranges[key] || message;
		} else if (rest[0] === 'date' && code === 'duplicate_date') {
			const entry = sent[index];
			const clash = findClash(
				sent,
				index,
				entry.date,
				entry.yearly === true
			);
			fields.date = clash
				? sprintf(
						/* translators: 1: the other entry's date, e.g. "Every year: December 24"; 2: its label in parentheses, or nothing. */
						__(
							'Special hours already exist for this date: %1$s%2$s. Only one entry per date is allowed; edit that one instead.',
							'hourgate'
						),
						describeDate(clash),
						clash.label ? ` (${clash.label})` : ''
					)
				: message;
		} else if (['label', 'date'].includes(rest[0])) {
			fields[rest[0]] = fields[rest[0]] || message;
		} else {
			general.push(message);
		}
	});
	return { fields, ranges, general };
};

/**
 * The two lists after converting a "closed" special-hours entry into a
 * one-day closure with the same label, date and yearly flag. Saved in one
 * PUT, so either both change or neither does.
 *
 * @param {Array}  closures     The stored closures.
 * @param {Array}  specialHours The stored special hours.
 * @param {number} index        The entry to convert.
 * @return {{closures: Array, specialHours: Array}} The new lists.
 */
export const convertToClosure = (closures, specialHours, index) => {
	const entry = specialHours[index];
	return {
		closures: [
			...closures,
			{
				label: typeof entry.label === 'string' ? entry.label : '',
				from: entry.date,
				to: entry.date,
				yearly: entry.yearly === true,
			},
		],
		specialHours: specialHours.filter((_, i) => i !== index),
	};
};
