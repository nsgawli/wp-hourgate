/**
 * Preview wording. Pure functions.
 *
 * Every date and time shown here comes from the server's own local values:
 * `startTime` / `endTime`, and the date and time parts of the ISO strings,
 * which `wp_date( 'c' )` writes in the store's zone ("2026-10-17T09:00:00+00:00").
 * Nothing is turned into a browser `Date`, so the browser's zone can't shift
 * anything (OW-025).
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	formatSiteDate,
	formatSiteTime,
	addDays,
} from '@framework/utils/siteTime';

const ISO = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/;

/**
 * The store-local date and time of an ISO string from the server.
 *
 * @param {string} iso E.g. "2026-10-17T09:00:00+00:00".
 * @return {{date: string, time: string}|null} Its local parts, or null.
 */
export const localParts = (iso) => {
	const m = typeof iso === 'string' && iso.match(ISO);
	return m ? { date: m[1], time: m[2] } : null;
};

/**
 * "today at 9:00 am", "tomorrow at 2:00 am" or "on March 4, 2027 at 9:00 am".
 *
 * @param {string} iso   An ISO string from the server.
 * @param {string} today The store's today, `Y-m-d`.
 * @return {string} The phrase, or '' for an unreadable value.
 */
export const when = (iso, today) => {
	const parts = localParts(iso);
	if (!parts) {
		return '';
	}
	const time = formatSiteTime(parts.time);
	if (parts.date === today) {
		return sprintf(
			/* translators: %s: a time, e.g. "9:00 am". */
			__('today at %s', 'hourgate'),
			time
		);
	}
	if (parts.date === addDays(today, 1)) {
		return sprintf(
			/* translators: %s: a time, e.g. "9:00 am". */
			__('tomorrow at %s', 'hourgate'),
			time
		);
	}
	return sprintf(
		/* translators: 1: a date; 2: a time. */
		__('on %1$s at %2$s', 'hourgate'),
		formatSiteDate(parts.date),
		time
	);
};

/**
 * The status sentence for `now` from the preview route. It describes the
 * schedule only.
 *
 * @param {Object} now     `{ time, open, nextOpen, nextClose }`.
 * @param {number} horizon Days the server searches ahead (400).
 * @return {{open: boolean, text: string}} The state and its sentence.
 */
export const statusText = (now, horizon = 400) => {
	const today = localParts(now?.time)?.date || '';
	if (now?.open) {
		return {
			open: true,
			text: now.nextClose
				? sprintf(
						/* translators: %s: when, e.g. "today at 5:00 pm". */
						__('Open now. Closes %s.', 'hourgate'),
						when(now.nextClose, today)
					)
				: sprintf(
						/* translators: %d: number of days. */
						__(
							'Open now, with no closing time in the next %d days.',
							'hourgate'
						),
						horizon
					),
		};
	}
	return {
		open: false,
		text: now?.nextOpen
			? sprintf(
					/* translators: %s: when, e.g. "tomorrow at 9:00 am". */
					__('Closed now. Opens %s.', 'hourgate'),
					when(now.nextOpen, today)
				)
			: sprintf(
					/* translators: %d: number of days. */
					__(
						'Closed now, with no opening in the next %d days.',
						'hourgate'
					),
					horizon
				),
	};
};

/**
 * One window as text: "9:00 am – 5:00 pm", "10:00 pm – 2:00 am next day",
 * or "10:00 pm – midnight" (`endsNextDay` with an end of 00:00).
 *
 * @param {Object} window `{ startTime, endTime, endsNextDay }`.
 * @return {string} The text.
 */
export const windowText = (window) => {
	const start = formatSiteTime(window.startTime);
	if (window.endsNextDay && window.endTime === '00:00') {
		/* translators: %s: opening time. */
		return sprintf(__('%s – midnight', 'hourgate'), start);
	}
	if (window.endsNextDay) {
		return sprintf(
			/* translators: 1: opening time; 2: closing time on the next day. */
			__('%1$s – %2$s next day', 'hourgate'),
			start,
			formatSiteTime(window.endTime)
		);
	}
	return sprintf(
		/* translators: 1: opening time; 2: closing time. */
		__('%1$s – %2$s', 'hourgate'),
		start,
		formatSiteTime(window.endTime)
	);
};

/**
 * Last night's window still running in a date's row: "until 2:00 am (from
 * Friday)".
 *
 * @param {Object} carried `{ endTime, fromDate }` from the preview.
 * @return {string} The text.
 */
export const carriedText = (carried) =>
	sprintf(
		/* translators: 1: closing time, e.g. "2:00 am"; 2: the weekday it started, e.g. "Friday". */
		__('until %1$s (from %2$s)', 'hourgate'),
		formatSiteTime(carried.endTime),
		formatSiteDate(carried.fromDate, 'l')
	);

/**
 * The date column: "Today", "Tomorrow", or the site date with its weekday.
 *
 * @param {string} date  `Y-m-d`.
 * @param {string} today The store's today.
 * @return {string} The text.
 */
export const dateText = (date, today) => {
	const full = formatSiteDate(date);
	if (date === today) {
		return sprintf(
			/* translators: %s: a date. */
			__('Today, %s', 'hourgate'),
			full
		);
	}
	if (date === addDays(today, 1)) {
		return sprintf(
			/* translators: %s: a date. */
			__('Tomorrow, %s', 'hourgate'),
			full
		);
	}
	return sprintf(
		/* translators: 1: weekday name; 2: a date. */
		__('%1$s, %2$s', 'hourgate'),
		formatSiteDate(date, 'l'),
		full
	);
};

/**
 * Why a date has its hours, and the sub-tab that holds the rule.
 *
 * @param {Object} day `{ rule, label }` from the preview.
 * @return {{text: string, tab: string}} The reason and the sub-tab key.
 */
export const reason = (day) => {
	if (day.rule === 'special') {
		return {
			text: day.label
				? sprintf(
						/* translators: %s: the entry's label. */
						__('Special hours: %s', 'hourgate'),
						day.label
					)
				: __('Special hours', 'hourgate'),
			tab: 'special-hours',
		};
	}
	if (day.rule === 'closure') {
		return {
			text: day.label
				? sprintf(
						/* translators: %s: the closure's label. */
						__('Closure: %s', 'hourgate'),
						day.label
					)
				: __('Closure', 'hourgate'),
			tab: 'closures',
		};
	}
	return { text: __('Weekly hours', 'hourgate'), tab: 'weekly' };
};
