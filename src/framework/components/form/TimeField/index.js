import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { isHhmm } from '@framework/utils/siteTime';
import '../index.css'; // form styles
import './index.css';

/**
 * A wall-clock time input: a native `<input type="time">`, so keyboard and
 * screen-reader support come from the browser, with no dependency.
 *
 * Controlled: `value` is `HH:MM` (24-hour) or `''`, and `onChange` gets the
 * same. With react-hook-form, wrap it in a `Controller`.
 * - `24:00` (end of day) can't be shown by a time input, so it shows as
 *   `00:00`; callers treat an end of `00:00` as midnight.
 * - A value it can't read (`'9:00'`, `'garbage'`, a number, null) shows as
 *   empty with an error naming the value. It never throws.
 * - The browser shows its own 12- or 24-hour format, not WordPress's; lists
 *   and previews use WordPress's format (`formatSiteTime()`).
 *
 * @param {Object}   props
 * @param {string}   props.id          Input id (required for the label).
 * @param {string}   props.label       Accessible name.
 * @param {boolean}  props.hideLabel   Keep the label for screen readers only.
 * @param {*}        props.value       `HH:MM`, `24:00` or `''`.
 * @param {Function} props.onChange    Called with `HH:MM` or `''`.
 * @param {string}   props.error       Error message to show and announce.
 * @param {string}   props.describedBy Extra ids for `aria-describedby`.
 * @param {boolean}  props.disabled
 * @param {string}   props.className   Extra class names.
 */
const TimeField = ({
	id,
	label,
	hideLabel = false,
	value,
	onChange,
	error = '',
	describedBy = '',
	disabled = false,
	className = '',
}) => {
	const readable = value === '' || value === undefined || isHhmm(value, true);
	let shown = readable && value ? value : '';
	if (shown === '24:00') {
		shown = '00:00';
	}
	const message =
		error ||
		(readable
			? ''
			: sprintf(
					/* translators: %s: the stored value that isn't a time, e.g. "9:00". */
					__(
						'Couldn’t read the saved time “%s”. Enter it again.',
						'hourgate'
					),
					String(value).slice(0, 20)
				));
	const errorId = `${id}-error`;
	const describedByIds = [describedBy, message ? errorId : '']
		.filter(Boolean)
		.join(' ');

	return (
		<div
			className={`hourgate-form-field hourgate-time-field ${className}`.trim()}
		>
			<label
				htmlFor={id}
				className={
					hideLabel
						? 'screen-reader-text'
						: 'hourgate-form-field-label'
				}
			>
				{label}
			</label>
			<input
				id={id}
				type="time"
				step="60"
				value={shown}
				disabled={disabled}
				aria-invalid={message ? true : undefined}
				aria-describedby={describedByIds || undefined}
				onChange={(event) =>
					onChange(
						event.target.value ? event.target.value.slice(0, 5) : ''
					)
				}
			/>
			{message && (
				<small
					id={errorId}
					className="hourgate-form-field-error-msg"
				>
					{message}
				</small>
			)}
		</div>
	);
};

export default TimeField;
