import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _x, sprintf } from '@wordpress/i18n';
import { Button } from '@framework/components';
import {
	getSiteSettings,
	formatSiteDate,
	withoutYear,
} from '@framework/utils/siteTime';
import { buildWeeks, daySegments, hourLabels } from './calendar-model';
import { dateText, reason } from './model';

/**
 * The week's date range: "October 26 – November 1, 2026".
 *
 * @param {Object[]} week Cells from `buildWeeks()`.
 * @return {string} The range.
 */
const rangeText = (week) => {
	const { dateFormat } = getSiteSettings();
	return sprintf(
		/* translators: 1: first date of the week, without the year; 2: last date of the week. */
		_x('%1$s – %2$s', 'week date range', 'hourgate'),
		formatSiteDate(week[0].date, withoutYear(dateFormat)),
		formatSiteDate(week[6].date)
	);
};

/**
 * The column header's rule line: the closure or special-hours entry that
 * decides the date, or "Closed" for a weekly day with nothing to draw (not
 * even last night's window). Weekly days with hours have none (the blocks
 * say it).
 *
 * @param {Object}  props        Props.
 * @param {Object}  props.day    The date's preview entry, or null.
 * @param {boolean} props.closed Whether the column has no blocks.
 * @return {JSX.Element|null} The line.
 */
const RuleLine = ({ day, closed }) => {
	if (!day) {
		return null;
	}
	if (day.rule === 'closure' || day.rule === 'special') {
		const why = reason(day);
		return (
			<Link
				to={`/store-hours/${why.tab}`}
				className={`hourgate-calendar__rule is-${day.rule}`}
				title={why.text}
			>
				{why.text}
			</Link>
		);
	}
	if (closed) {
		return (
			<span className="hourgate-calendar__rule">
				{__('Closed', 'hourgate')}
			</span>
		);
	}
	return null;
};

/**
 * One day's column: its header (weekday, date, rule line) and its blocks
 * on the 00:00–24:00 axis.
 *
 * @param {Object} props       Props.
 * @param {Object} props.cell  A cell from `buildWeeks()`.
 * @param {string} props.today The store's today.
 * @return {JSX.Element} The column.
 */
const DayColumn = ({ cell, today }) => {
	const { date, day, previous, past, beyond } = cell;
	const blocks = day ? daySegments(day, previous) : [];
	const classes = ['hourgate-calendar__day'];
	if (date === today) {
		classes.push('is-today');
	}
	if (past || beyond) {
		classes.push('is-out');
	}
	if (day?.rule === 'closure') {
		classes.push('is-closure');
	} else if (day && !blocks.length) {
		classes.push('is-closed');
	}

	return (
		<li
			className={classes.join(' ')}
			data-date={date}
			aria-hidden={!day || undefined}
		>
			<div className="hourgate-calendar__head">
				<span className="hourgate-calendar__date" aria-hidden="true">
					<span className="hourgate-calendar__weekday">
						{formatSiteDate(date, 'D')}
					</span>
					<span className="hourgate-calendar__number">
						{formatSiteDate(date, 'j')}
					</span>
				</span>
				{day && (
					<h4 className="screen-reader-text">
						{dateText(date, today)}
					</h4>
				)}
				<RuleLine day={day} closed={!blocks.length} />
			</div>
			<div className="hourgate-calendar__column">
				{day && !blocks.length && (
					<span className="screen-reader-text">
						{__('Closed', 'hourgate')}
					</span>
				)}
				{blocks.length > 0 && (
					<ul className="hourgate-calendar__blocks">
						{blocks.map((block) => (
							<li
								key={block.key}
								className={`hourgate-calendar__block is-${block.rule}${block.continues ? ' continues' : ''}${block.key === 'carried' ? ' carried' : ''}`}
								style={{
									top: `${block.top}%`,
									height: `${block.height}%`,
									insetInlineStart: `${(block.lane / block.lanes) * 100}%`,
									width: `${100 / block.lanes}%`,
								}}
							>
								<Link
									to={`/store-hours/${block.tab}`}
									title={block.text}
								>
									<span aria-hidden="true">
										<strong>{block.times}</strong>
										<span className="hourgate-calendar__why">
											{block.why}
										</span>
									</span>
									<span className="screen-reader-text">
										{block.text}
									</span>
								</Link>
							</li>
						))}
					</ul>
				)}
			</div>
		</li>
	);
};

/**
 * The Preview as a week grid (OW-059): seven day columns on a fixed
 * 00:00–24:00 wall-clock axis, drawn from the same `GET /schedule/preview`
 * response as the list, so the two can't disagree. Weeks page in the
 * browser; no request per week.
 *
 * @param {Object}   props       Props.
 * @param {Object[]} props.days  The preview's `days`.
 * @param {string}   props.today The store's today, `Y-m-d`.
 * @return {JSX.Element} The calendar.
 */
const Calendar = ({ days, today }) => {
	const { startOfWeek } = getSiteSettings();
	const weeks = useMemo(
		() => buildWeeks(days, today, startOfWeek),
		[days, today, startOfWeek]
	);
	const [index, setIndex] = useState(0);
	const hours = useMemo(() => hourLabels(), []);
	const previousRef = useRef(null);
	const nextRef = useRef(null);
	// The button to focus after a week change, when the one pressed is about
	// to be disabled (Today, or Previous to the first week, or Next to the
	// last): a disabled button drops focus to the page (OW-061).
	const focusAfter = useRef(null);

	useEffect(() => {
		focusAfter.current?.current?.focus();
		focusAfter.current = null;
	}, [index]);

	if (!weeks.length) {
		return (
			<p className="hourgate-calendar__empty">
				{__('No dates to show.', 'hourgate')}
			</p>
		);
	}

	// Fewer weeks after a reload (the days shrank): stay in range.
	const current = Math.min(index, weeks.length - 1);
	const week = weeks[current];
	const range = rangeText(week);
	const rangeId = 'hourgate-calendar-range';
	const last = weeks.length - 1;

	/**
	 * Show week `to`. Pressing a button that this disables moves focus to the
	 * one that stays on: Next at the first week, Previous at the last.
	 *
	 * @param {number} to The week's index.
	 */
	const go = (to) => {
		if (0 === to && last > 0) {
			focusAfter.current = nextRef;
		} else if (last === to && to > 0) {
			focusAfter.current = previousRef;
		}
		setIndex(to);
	};

	return (
		<div className="hourgate-calendar">
			<div className="hourgate-calendar__nav">
				<div className="hourgate-calendar__buttons">
					<Button
						ref={previousRef}
						size="small"
						disabled={current === 0}
						onClick={() => go(current - 1)}
					>
						{__('Previous week', 'hourgate')}
					</Button>
					<Button
						size="small"
						disabled={current === 0}
						onClick={() => go(0)}
					>
						{__('Today', 'hourgate')}
					</Button>
					<Button
						ref={nextRef}
						size="small"
						disabled={current === last}
						onClick={() => go(current + 1)}
					>
						{__('Next week', 'hourgate')}
					</Button>
				</div>
				<h3
					id={rangeId}
					className="hourgate-calendar__range"
					aria-live="polite"
				>
					{range}
				</h3>
			</div>

			<div className="hourgate-calendar__grid">
				<div className="hourgate-calendar__axis" aria-hidden="true">
					<div className="hourgate-calendar__corner" />
					<div className="hourgate-calendar__hours">
						{hours.map(({ hour, text }) => (
							<span
								key={hour}
								style={{ top: `${(hour / 24) * 100}%` }}
							>
								{hour ? text : ''}
							</span>
						))}
					</div>
				</div>
				<ol
					className="hourgate-calendar__days"
					aria-labelledby={rangeId}
				>
					{week.map((cell) => (
						<DayColumn key={cell.date} cell={cell} today={today} />
					))}
				</ol>
			</div>
		</div>
	);
};

export default Calendar;
