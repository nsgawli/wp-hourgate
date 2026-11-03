import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { isYmd } from '@framework/utils/siteTime';
import '../index.css'; // form styles
import '../TimeField/index.css';

/**
 * A calendar-date input: a native `<input type="date">`. Like `TimeField`,
 * keyboard and screen-reader support come from the browser, and the value is
 * a plain `Y-m-d` string, so no time zone can shift it (a `Date` object
 * could).
 *
 * Controlled: `value` is `Y-m-d` or `''`, and `onChange` gets the same.
 * A value it can't read (`'2027-02-29'`, `'24/12'`, a number) shows as empty
 * with an error naming it. It never throws.
 *
 * @param {Object}   props
 * @param {string}   props.id          Input id (required for the label).
 * @param {string}   props.label       Visible label and accessible name.
 * @param {*}        props.value       `Y-m-d` or `''`.
 * @param {Function} props.onChange    Called with `Y-m-d` or `''`.
 * @param {string}   props.error       Error message to show and announce.
 * @param {string}   props.describedBy Extra ids for `aria-describedby`.
 * @param {boolean}  props.disabled    Disable the input.
 * @param {boolean}  props.required    Mark as required.
 * @param {string}   props.className   Extra class names.
 */
const DateField = ({
	id,
	label,
	value,
	onChange,
	error = '',
	describedBy = '',
	disabled = false,
	required = false,
	className = '',
}) => {
	const readable = value === '' || value === undefined || isYmd(value);
	const message =
		error ||
		(readable
			? ''
			: sprintf(
					/* translators: %s: the stored value that isn't a date. */
					__(
						'Couldn’t read the saved date “%s”. Enter it again.',
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
			<label htmlFor={id} className="hourgate-form-field-label">
				{label}
			</label>
			<input
				id={id}
				type="date"
				value={readable && value ? value : ''}
				disabled={disabled}
				required={required}
				aria-invalid={message ? true : undefined}
				aria-describedby={describedByIds || undefined}
				onChange={(event) => onChange(event.target.value || '')}
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

export default DateField;
