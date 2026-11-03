import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ModalPopup, Button } from '@framework/components';

const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri'];

/**
 * "Copy Monday's hours to…": pick target days, with All days and Weekdays
 * shortcuts. Copying changes the draft only; nothing is saved until Save.
 *
 * @param {Object}   props
 * @param {string}   props.from    Source day key, or null when closed.
 * @param {string[]} props.days    Day keys in display order.
 * @param {Object}   props.names   Day key → translated name.
 * @param {Function} props.onCopy  Called with the chosen day keys.
 * @param {Function} props.onClose
 */
const CopyDialog = ({ from, days, names, onCopy, onClose }) => {
	// Mounted only while open (see WeeklyHours), so each opening starts empty.
	const [targets, setTargets] = useState([]);

	const others = days.filter((day) => day !== from);
	const toggle = (day) =>
		setTargets((current) =>
			current.includes(day)
				? current.filter((d) => d !== day)
				: [...current, day]
		);

	return (
		<ModalPopup
			isOpen={from !== null}
			onClose={onClose}
			closeOnBackdrop={false}
			size="small"
			title={
				from
					? sprintf(
							/* translators: %s: day name, e.g. "Monday". */
							__('Copy %s’s hours to…', 'hourgate'),
							names[from]
						)
					: ''
			}
		>
			<div className="hourgate-weekly-copy__shortcuts">
				<Button size="small" onClick={() => setTargets(others)}>
					{__('All days', 'hourgate')}
				</Button>
				<Button
					size="small"
					onClick={() =>
						setTargets(WEEKDAYS.filter((day) => day !== from))
					}
				>
					{__('Weekdays', 'hourgate')}
				</Button>
			</div>
			<fieldset className="hourgate-weekly-copy__days">
				<legend className="screen-reader-text">
					{__('Days to copy to', 'hourgate')}
				</legend>
				{others.map((day) => (
					<label
						key={day}
						htmlFor={`hourgate-weekly-copy-${day}`}
						className="hourgate-weekly-copy__day"
					>
						<input
							id={`hourgate-weekly-copy-${day}`}
							type="checkbox"
							checked={targets.includes(day)}
							onChange={() => toggle(day)}
						/>
						{names[day]}
					</label>
				))}
			</fieldset>
			<p className="hourgate-weekly-copy__note">
				{__(
					'The chosen days’ hours are replaced. Nothing is saved until you click Save.',
					'hourgate'
				)}
			</p>
			<div className="hourgate-weekly-copy__actions">
				<Button onClick={onClose}>
					{__('Cancel', 'hourgate')}
				</Button>
				<Button
					type="primary"
					disabled={targets.length === 0}
					onClick={() => onCopy(targets)}
				>
					{__('Copy', 'hourgate')}
				</Button>
			</div>
		</ModalPopup>
	);
};

export default CopyDialog;
