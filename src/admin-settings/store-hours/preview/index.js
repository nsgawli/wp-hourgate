import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { Button, ScreenLoader } from '@framework/components';
import useElementWidth from '@framework/hooks/useElementWidth';
import { getPreview } from '../resource';
import Calendar from './Calendar';
import {
	localParts,
	statusText,
	windowText,
	carriedText,
	dateText,
	reason,
} from './model';
import './index.css';

const DAY_CHOICES = [7, 14, 30, 60];

// One load covers both views: the calendar pages through up to 60 days in
// the browser, and the list shows the first 7–60 of them.
const LOAD_DAYS = 60;

// Content width (px) from which seven day columns stay readable (OW-060).
export const CALENDAR_MIN_WIDTH = 720;

const VIEW_KEY = 'hourGatePreviewView';

/**
 * The view chosen on a wide screen, remembered per browser. A convenience
 * only: with storage blocked it is Calendar.
 *
 * @return {string} `calendar` or `list`.
 */
const storedView = () => {
	try {
		return window.localStorage.getItem(VIEW_KEY) === 'list'
			? 'list'
			: 'calendar';
	} catch (e) {
		return 'calendar';
	}
};

const storeView = (view) => {
	try {
		window.localStorage.setItem(VIEW_KEY, view);
	} catch (e) {
		// Storage blocked: the choice lasts until the page reloads.
	}
};

/**
 * The next days as the schedule engine sees them (`GET /schedule/preview`):
 * the open/closed state now, then each date's windows and the rule that
 * produced them. It's never cached, and it loads again whenever the sub-tab
 * opens, so it always reflects the latest save.
 *
 * Wide containers show it as a week calendar with a Calendar | List switch;
 * narrow ones show the list only (OW-059, OW-060). Both views draw the same
 * response, and swapping them never loads again.
 */
const Preview = () => {
	const [days, setDays] = useState(14);
	const [data, setData] = useState(null);
	const [error, setError] = useState('');
	const [loading, setLoading] = useState(true);
	const [view, setView] = useState(storedView);
	const [measureRef, width] = useElementWidth();

	const load = useCallback(() => {
		setLoading(true);
		setError('');
		getPreview(LOAD_DAYS)
			.then((preview) => setData(preview))
			.catch((e) =>
				setError(e?.message || __('Unknown error.', 'hourgate'))
			)
			.finally(() => setLoading(false));
	}, []);

	useEffect(() => load(), [load]);

	if (error) {
		return (
			<div className="hourgate-preview__error" role="alert">
				<p>
					{sprintf(
						/* translators: %s: error message. */
						__('The preview couldn’t be loaded: %s', 'hourgate'),
						error
					)}
				</p>
				<Button onClick={() => load()}>
					{__('Try again', 'hourgate')}
				</Button>
			</div>
		);
	}
	if (!data) {
		return <ScreenLoader />;
	}

	const today = localParts(data.now?.time)?.date || '';
	const status = statusText(data.now);
	const allDays = Array.isArray(data.days) ? data.days : [];
	const listDays = allDays.slice(0, days);
	const wide = width >= CALENDAR_MIN_WIDTH;
	const shown = wide ? view : 'list';

	return (
		<div
			className="hourgate-preview"
			aria-busy={loading}
			ref={measureRef}
		>
			<p className="hourgate-preview__intro">
				{__(
					'How your saved weekly hours, closures and special hours combine over the coming days, and which rule decides each date.',
					'hourgate'
				)}
			</p>

			{data.fallback && (
				<div className="hourgate-store-hours-notice is-warning">
					{__(
						'The saved store hours or closures are invalid, so HourGate treats the store as always open until they are fixed and saved.',
						'hourgate'
					)}{' '}
					<Link to="/store-hours/weekly">
						{__('Review the weekly hours', 'hourgate')}
					</Link>
				</div>
			)}

			<div
				className={`hourgate-preview__status ${status.open ? 'is-open' : 'is-closed'}`}
				role="status"
			>
				<span className="hourgate-preview__status-label">
					{__('Schedule', 'hourgate')}
				</span>
				<strong>{status.text}</strong>
			</div>

			<div className="hourgate-preview__toolbar">
				{wide && (
					<div
						className="hourgate-preview__views"
						role="radiogroup"
						aria-label={__('View', 'hourgate')}
					>
						{[
							['calendar', __('Calendar', 'hourgate')],
							['list', __('List', 'hourgate')],
						].map(([key, label]) => (
							<label
								key={key}
								htmlFor={`hourgate-preview-view-${key}`}
								className="hourgate-preview__view"
							>
								<input
									id={`hourgate-preview-view-${key}`}
									type="radio"
									name="hourgate-preview-view"
									value={key}
									checked={shown === key}
									onChange={() => {
										setView(key);
										storeView(key);
									}}
								/>
								{label}
							</label>
						))}
					</div>
				)}
				{shown === 'list' && (
					<>
						<label htmlFor="hourgate-preview-days">
							{__('Show', 'hourgate')}
						</label>
						<select
							id="hourgate-preview-days"
							value={days}
							onChange={(event) =>
								setDays(Number(event.target.value))
							}
						>
							{DAY_CHOICES.map((count) => (
								<option key={count} value={count}>
									{sprintf(
										/* translators: %d: number of days. */
										__('%d days', 'hourgate'),
										count
									)}
								</option>
							))}
						</select>
					</>
				)}
				<Button size="small" onClick={() => load()} loading={loading}>
					{__('Refresh', 'hourgate')}
				</Button>
			</div>

			{width > 0 && shown === 'calendar' && (
				<Calendar days={allDays} today={today} />
			)}
			{width > 0 && shown === 'list' && (
				<table className="hourgate-preview__table">
					<caption className="screen-reader-text">
						{sprintf(
							/* translators: %d: number of days. */
							__(
								'Opening hours for the next %d days',
								'hourgate'
							),
							listDays.length
						)}
					</caption>
					<thead>
						<tr>
							<th scope="col">{__('Date', 'hourgate')}</th>
							<th scope="col">{__('Hours', 'hourgate')}</th>
							<th scope="col">{__('Why', 'hourgate')}</th>
						</tr>
					</thead>
					<tbody>
						{listDays.map((day) => {
							const why = reason(day);
							const windows = Array.isArray(day.windows)
								? day.windows
								: [];
							const parts = [
								...(day.carriedOver
									? [carriedText(day.carriedOver)]
									: []),
								...windows.map((w) => windowText(w)),
							];
							return (
								<tr
									key={day.date}
									data-date={day.date}
									className={parts.length ? '' : 'is-closed'}
								>
									<th scope="row">
										{dateText(day.date, today)}
									</th>
									<td>
										{parts.length
											? parts.join(', ')
											: __('Closed', 'hourgate')}
									</td>
									<td>
										<Link to={`/store-hours/${why.tab}`}>
											{why.text}
										</Link>
									</td>
								</tr>
							);
						})}
					</tbody>
				</table>
			)}
		</div>
	);
};

export default Preview;
