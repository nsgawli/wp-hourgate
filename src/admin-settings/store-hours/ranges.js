/**
 * Checks for a list of opening ranges (`{ start, end }`, `HH:MM`), shared by
 * weekly hours and special hours. For quick feedback only; the server decides.
 * They cover an empty or unreadable time, start equal to end, and overlaps
 * within the list.
 */
import { __, sprintf } from '@wordpress/i18n';
import { isHhmm, toMinutes } from '@framework/utils/siteTime';

/**
 * Problems in one list of ranges.
 *
 * @param {Array} ranges The ranges.
 * @return {Object} Messages keyed `"{index}"`, `"{index}.start"`, `"{index}.end"` (TimeRangesEditor's keys).
 */
export const validateRanges = (ranges) => {
	const errors = {};
	const spans = [];
	ranges.forEach((range, index) => {
		const start = range?.start;
		const end = range?.end;
		if (!isHhmm(start)) {
			errors[`${index}.start`] = start
				? __('Enter a valid time.', 'hourgate')
				: __('Enter an opening time.', 'hourgate');
		}
		if (!isHhmm(end, true)) {
			errors[`${index}.end`] = end
				? __('Enter a valid time.', 'hourgate')
				: __('Enter a closing time.', 'hourgate');
		}
		if (`${index}.start` in errors || `${index}.end` in errors) {
			return;
		}
		const s = toMinutes(start);
		const rawEnd = toMinutes(end);
		// Compared raw: `00:00`–`00:00` is empty (the server's empty_range);
		// only `00:00`–`24:00` is a full day.
		if (rawEnd === s) {
			errors[String(index)] = __(
				'Opening and closing times are the same. For a full day, choose Open 24 hours.',
				'hourgate'
			);
			return;
		}
		// An end of 00:00 is midnight; an end before the start runs past midnight.
		const e = rawEnd === 0 ? 1440 : rawEnd;
		spans.push({ index, s, e: e <= s ? e + 1440 : e });
	});
	spans.sort((a, b) => a.s - b.s || a.index - b.index);
	// Compare each range with the furthest-reaching one before it, not only
	// its neighbour: 09–23 overlaps 12–13 even with 10–11 between them.
	let reach = null;
	spans.forEach((current) => {
		if (
			reach &&
			current.s < reach.e &&
			!(String(current.index) in errors)
		) {
			errors[String(current.index)] = sprintf(
				/* translators: %d: number of the other time range. */
				__('Overlaps range %d.', 'hourgate'),
				reach.index + 1
			);
		}
		if (!reach || current.e > reach.e) {
			reach = current;
		}
	});
	return errors;
};
