import React, { useEffect, useRef } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { FiPlus, FiTrash2 } from 'react-icons/fi';
import TimeField from '@framework/components/form/TimeField';
import {
	toMinutes,
	fromMinutes,
	formatSiteTime,
} from '@framework/utils/siteTime';
import './index.css';

/**
 * A list of opening ranges (`{ start, end }`, wall-clock `HH:MM`) with add
 * and remove. Used by weekly hours and special hours, and later by Pro's
 * named schedules. It holds no validation rules: the server decides, and its
 * errors come back through `errors`.
 *
 * - An end at or before the start runs past midnight; the row says so
 *   ("ends 02:00 the next day"). An end of `00:00` or `24:00` is "until
 *   midnight".
 * - Each input is named for its context ("Monday, range 2, opens").
 * - At `max` rows, Add is disabled and the limit is stated (no silent cap).
 *
 * @param {Object}   props
 * @param {string}   props.idPrefix Unique prefix for input ids.
 * @param {string}   props.context  What the ranges belong to, e.g. "Monday".
 * @param {Array}    props.value    Ranges as stored.
 * @param {Function} props.onChange Called with the new list.
 * @param {Object}   props.errors   Messages keyed `"{index}"`, `"{index}.start"`, `"{index}.end"`.
 * @param {number}   props.max      Most rows allowed.
 * @param {boolean}  props.disabled
 */
const TimeRangesEditor = ({
	idPrefix,
	context,
	value = [],
	onChange,
	errors = {},
	max = 24,
	disabled = false,
}) => {
	const rows = Array.isArray(value) ? value : [];
	const focusAfterRender = useRef(null);
	const rootRef = useRef(null);

	useEffect(() => {
		if (focusAfterRender.current) {
			rootRef.current?.querySelector(focusAfterRender.current)?.focus();
			focusAfterRender.current = null;
		}
	});

	const update = (index, key, time) =>
		onChange(
			rows.map((row, i) =>
				i === index ? { ...asRange(row), [key]: time } : row
			)
		);

	const add = () => {
		const last = rows.length ? asRange(rows[rows.length - 1]) : null;
		const lastEnd = last ? toMinutes(last.end) : null;
		const next =
			lastEnd === null
				? { start: '09:00', end: '17:00' }
				: {
						start: fromMinutes(lastEnd),
						end: fromMinutes(lastEnd + 60),
					};
		focusAfterRender.current = `#${fieldId(idPrefix, rows.length, 'start')}`;
		onChange([...rows, next]);
	};

	const remove = (index) => {
		const remaining = rows.filter((_, i) => i !== index);
		focusAfterRender.current = remaining.length
			? `#${fieldId(idPrefix, Math.min(index, remaining.length - 1), 'start')}`
			: `#${idPrefix}-add`;
		onChange(remaining);
	};

	const atMax = rows.length >= max;

	return (
		<div className="hourgate-time-ranges" ref={rootRef}>
			{rows.length > 0 && (
				<div
					className="hourgate-time-ranges__head"
					aria-hidden="true"
				>
					<span>{__('Opens', 'hourgate')}</span>
					<span>{__('Closes', 'hourgate')}</span>
				</div>
			)}
			{rows.map((row, index) => {
				const range = asRange(row);
				const n = index + 1;
				const rowErrorId = `${idPrefix}-${index}-error`;
				const hintId = `${idPrefix}-${index}-hint`;
				const hint = rangeHint(range);
				const rowError = errors[String(index)];
				const describedBy = [
					hint ? hintId : '',
					rowError ? rowErrorId : '',
				]
					.filter(Boolean)
					.join(' ');
				return (
					<div
						className="hourgate-time-ranges__row"
						key={index}
					>
						<div className="hourgate-time-ranges__fields">
							<TimeField
								id={fieldId(idPrefix, index, 'start')}
								hideLabel
								label={sprintf(
									/* translators: 1: what the hours belong to, e.g. "Monday"; 2: range number. */
									__(
										'%1$s, range %2$d, opens',
										'hourgate'
									),
									context,
									n
								)}
								value={range.start}
								onChange={(time) =>
									update(index, 'start', time)
								}
								error={errors[`${index}.start`]}
								describedBy={describedBy}
								disabled={disabled}
							/>
							<span
								className="hourgate-time-ranges__dash"
								aria-hidden="true"
							>
								–
							</span>
							<TimeField
								id={fieldId(idPrefix, index, 'end')}
								hideLabel
								label={sprintf(
									/* translators: 1: what the hours belong to, e.g. "Monday"; 2: range number. */
									__(
										'%1$s, range %2$d, closes',
										'hourgate'
									),
									context,
									n
								)}
								value={range.end}
								onChange={(time) => update(index, 'end', time)}
								error={errors[`${index}.end`]}
								describedBy={describedBy}
								disabled={disabled}
							/>
							<button
								type="button"
								className="hourgate-time-ranges__remove"
								onClick={() => remove(index)}
								disabled={disabled}
								aria-label={sprintf(
									/* translators: 1: range number; 2: what the hours belong to, e.g. "Monday". */
									__(
										'Remove range %1$d (%2$s)',
										'hourgate'
									),
									n,
									context
								)}
							>
								<FiTrash2
									aria-hidden="true"
									focusable="false"
								/>
							</button>
						</div>
						{hint && (
							<small
								id={hintId}
								className="hourgate-time-ranges__hint"
							>
								{hint}
							</small>
						)}
						{rowError && (
							<small
								id={rowErrorId}
								className="hourgate-form-field-error-msg"
							>
								{rowError}
							</small>
						)}
					</div>
				);
			})}
			<div className="hourgate-time-ranges__add">
				<button
					type="button"
					id={`${idPrefix}-add`}
					className="hourgate-button small"
					onClick={add}
					disabled={disabled || atMax}
					aria-describedby={atMax ? `${idPrefix}-max` : undefined}
				>
					<FiPlus aria-hidden="true" focusable="false" />
					{__('Add time range', 'hourgate')}
				</button>
				{atMax && (
					<small id={`${idPrefix}-max`}>
						{sprintf(
							/* translators: %d: the most time ranges allowed. */
							_n(
								'Up to %d time range per day.',
								'Up to %d time ranges per day.',
								max,
								'hourgate'
							),
							max
						)}
					</small>
				)}
			</div>
		</div>
	);
};

const fieldId = (prefix, index, key) => `${prefix}-${index}-${key}`;

/**
 * A stored row as `{ start, end }`; a row that isn't an object passes through as unreadable.
 *
 * @param {*} row A stored row.
 * @return {{start: *, end: *}} The row's start and end, as stored.
 */
const asRange = (row) =>
	row && typeof row === 'object' && !Array.isArray(row)
		? { start: row.start, end: row.end }
		: { start: row, end: row };

/**
 * "until midnight", "ends 02:00 the next day", or '' for a same-day or unreadable range.
 *
 * @param {Object} range       The range.
 * @param {*}      range.start Opening time.
 * @param {*}      range.end   Closing time.
 * @return {string} The hint, or ''.
 */
export const rangeHint = ({ start, end }) => {
	const s = toMinutes(start);
	const e = toMinutes(end);
	if (s === null || e === null || s === e) {
		return '';
	}
	if (e === 0 || e === 1440) {
		return s === 0 ? '' : __('until midnight', 'hourgate');
	}
	if (e < s) {
		return sprintf(
			/* translators: %s: closing time, e.g. "2:00 am". */
			__('ends %s the next day', 'hourgate'),
			formatSiteTime(end)
		);
	}
	return '';
};

export default TimeRangesEditor;
