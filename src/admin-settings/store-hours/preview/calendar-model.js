/**
 * Week calendar model (OW-059). Pure functions.
 *
 * Same rule as `model.js`: every date and time is the server's own local
 * string (`Y-m-d`, `HH:MM`). Dates move by calendar arithmetic (`addDays`),
 * minutes come from `toMinutes()`, and nothing becomes a browser `Date` in
 * the browser's zone, so a DST day (23 or 25 hours) is still drawn on a fixed
 * 00:00–24:00 wall-clock axis.
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import {
	addDays,
	isYmd,
	toMinutes,
	formatSiteTime,
	formatSiteDate,
} from '@framework/utils/siteTime';
import { windowText, reason } from './model';

const DAY_MINUTES = 1440;

/**
 * The weekday of a calendar date, 0 = Sunday.
 *
 * @param {string} ymd `Y-m-d`.
 * @return {number} 0–6, or -1 for an invalid date.
 */
export const weekday = (ymd) =>
	isYmd(ymd) ? new Date(`${ymd}T12:00:00Z`).getUTCDay() : -1;

/**
 * The first date of the week holding `ymd`.
 *
 * @param {string} ymd         `Y-m-d`.
 * @param {number} startOfWeek WordPress's `start_of_week` (0 = Sunday).
 * @return {string} `Y-m-d`, or '' for an invalid date.
 */
export const weekStart = (ymd, startOfWeek = 0) => {
	const day = weekday(ymd);
	if (day < 0) {
		return '';
	}
	const first =
		Number.isInteger(startOfWeek) && startOfWeek >= 0 && startOfWeek <= 6
			? startOfWeek
			: 0;
	return addDays(ymd, -((day - first + 7) % 7));
};

/**
 * The preview's dates grouped into weeks of seven cells. A cell is
 * `{ date, day, past, beyond }`: `day` is the preview entry or null, `past`
 * marks dates before today in the first week, `beyond` dates after the last
 * one the preview covers. `previous` is the entry of the date before (or
 * null), which holds the rule of a window carried over into this date.
 *
 * @param {Object[]} days        The preview's `days`, in date order.
 * @param {string}   today       The store's today, `Y-m-d`.
 * @param {number}   startOfWeek WordPress's `start_of_week`.
 * @return {Object[][]} The weeks; empty when there is nothing to show.
 */
export const buildWeeks = (days, today, startOfWeek = 0) => {
	const list = Array.isArray(days)
		? days.filter((day) => day && isYmd(day.date))
		: [];
	if (!list.length) {
		return [];
	}
	const byDate = new Map(list.map((day) => [day.date, day]));
	const first = isYmd(today) ? today : list[0].date;
	const last = list[list.length - 1].date;
	const weeks = [];
	for (
		let start = weekStart(first, startOfWeek);
		start && start <= last;
		start = addDays(start, 7)
	) {
		const week = [];
		for (let i = 0; i < 7; i++) {
			const date = addDays(start, i);
			week.push({
				date,
				day: byDate.get(date) || null,
				previous: byDate.get(addDays(date, -1)) || null,
				past: date < first,
				beyond: date > last,
			});
		}
		weeks.push(week);
	}
	return weeks;
};

/**
 * Side-by-side lanes for blocks that overlap in time (a special-hours range
 * that starts before last night's carried-over window ends: only closures
 * cut that window, D5). Blocks are in start order; each gets `lane` and
 * `lanes`, the lane count of its group of overlapping blocks.
 *
 * @param {Object[]} blocks Blocks with `from` and `to` minutes, by `from`.
 * @return {Object[]} The same blocks with `lane` and `lanes`.
 */
const withLanes = (blocks) => {
	const out = [];
	let group = [];
	let groupEnd = -1;
	const flush = () => {
		const ends = [];
		group.forEach((block) => {
			let lane = ends.findIndex((end) => end <= block.from);
			if (lane < 0) {
				lane = ends.length;
			}
			ends[lane] = block.to;
			block.lane = lane;
		});
		group.forEach((block) => out.push({ ...block, lanes: ends.length }));
		group = [];
		groupEnd = -1;
	};
	blocks.forEach((block) => {
		if (group.length && block.from >= groupEnd) {
			flush();
		}
		group.push({ ...block });
		groupEnd = Math.max(groupEnd, block.to);
	});
	if (group.length) {
		flush();
	}
	return out;
};

/**
 * The blocks to draw in one day's column: last night's window still running
 * after midnight (`carriedOver`), then each window of the date. A window that
 * ends on the next day is drawn to midnight here, and the next column draws
 * its remainder from that date's own `carriedOver` (so a closure on the next
 * date, which the engine already cut at midnight, draws nothing there).
 *
 * Each block is `{ key, top, height, lane, lanes, rule, tab, times, why,
 * text, continues }`: `top` and `height` are percent of the day, `rule` is
 * the rule that made it (a carried-over window keeps the rule of the night
 * before), `times` and `why` are the visible lines, and `text` is the full
 * sentence a screen reader hears ("9:00 am – 5:00 pm, Weekly hours").
 *
 * @param {Object|null} day      A preview entry.
 * @param {Object|null} previous The entry of the date before, if known.
 * @return {Object[]} The blocks, in time order.
 */
export const daySegments = (day, previous = null) => {
	if (!day) {
		return [];
	}
	const blocks = [];

	const carried = day.carriedOver;
	const carriedEnd = carried ? toMinutes(carried.endTime) : null;
	if (carriedEnd !== null && carriedEnd > 0) {
		const before = reason(previous || {});
		blocks.push({
			key: 'carried',
			from: 0,
			to: carriedEnd,
			rule: previous?.rule || 'weekly',
			tab: before.tab,
			times: sprintf(
				/* translators: 1: opening time; 2: closing time. */
				__('%1$s – %2$s', 'hourgate'),
				formatSiteTime('00:00'),
				formatSiteTime(carried.endTime)
			),
			why: before.text,
			text: sprintf(
				/* translators: 1: closing time, e.g. "2:00 am"; 2: the weekday the opening started, e.g. "Friday"; 3: the rule, e.g. "Weekly hours". */
				__('Open until %1$s, continued from %2$s, %3$s', 'hourgate'),
				formatSiteTime(carried.endTime),
				formatSiteDate(carried.fromDate, 'l'),
				before.text
			),
			continues: false,
		});
	}

	const why = reason(day);
	(Array.isArray(day.windows) ? day.windows : []).forEach((w, index) => {
		const start = toMinutes(w?.startTime);
		if (start === null) {
			return;
		}
		const end = w.endsNextDay ? DAY_MINUTES : toMinutes(w.endTime);
		if (end === null || end <= start) {
			// A same-day window can't end before it starts; skip it rather
			// than draw a wrong block (the engine never sends one).
			return;
		}
		const times = windowText(w);
		blocks.push({
			key: `w${index}`,
			from: start,
			to: end,
			rule: day.rule || 'weekly',
			tab: why.tab,
			times,
			why: why.text,
			text: sprintf(
				/* translators: 1: opening hours, e.g. "9:00 am – 5:00 pm"; 2: the rule, e.g. "Weekly hours". */
				_x('%1$s, %2$s', 'opening hours and their rule', 'hourgate'),
				times,
				why.text
			),
			continues: Boolean(w.endsNextDay),
		});
	});

	blocks.sort((a, b) => a.from - b.from || a.to - b.to);
	return withLanes(blocks).map(({ from, to, ...block }) => ({
		...block,
		top: (from / DAY_MINUTES) * 100,
		height: ((to - from) / DAY_MINUTES) * 100,
	}));
};

/**
 * The axis labels: every hour in the site's time format.
 *
 * @return {{hour: number, text: string}[]} 24 labels, 00:00 to 23:00.
 */
export const hourLabels = () =>
	Array.from({ length: 24 }, (unused, hour) => ({
		hour,
		text: formatSiteTime(`${String(hour).padStart(2, '0')}:00`),
	}));
