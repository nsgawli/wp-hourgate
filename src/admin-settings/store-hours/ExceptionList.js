import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { FiEdit2, FiTrash2, FiPlus } from 'react-icons/fi';
import { Button } from '@framework/components';
import './ExceptionList.css';

/**
 * A list of dated exceptions (closures, special hours) with add, edit and
 * delete. Shared by the Closures (OW-023) and Special hours (OW-024) tabs.
 *
 * - Entries that can't be read are listed first, flagged, with Edit and Delete.
 * - Past entries are collapsed in a native `<details>`, with a count and an
 *   optional "Delete past …" action.
 * - At `max`, Add is disabled and the limit is stated (no silent cap).
 *
 * @param {Object}   props
 * @param {Array}    props.items           `{ key, dates, label, notes[], unreadable, past, action? }`, already sorted;
 *                                         `action` is an extra row button, `{ label, ariaLabel, onClick }`.
 * @param {string}   props.noun            Accessible noun for row actions, e.g. "closure".
 * @param {string}   props.addLabel        Text of the Add button.
 * @param {string}   props.emptyText       Shown when there are no upcoming entries.
 * @param {string}   props.limitText       Shown when `count >= max`.
 * @param {number}   props.count           How many entries are stored.
 * @param {number}   props.max             Most entries allowed.
 * @param {string}   props.pastTitle       Summary of the past group; `%d` is the count.
 * @param {string}   props.deletePastLabel Text of the delete-past button (omit to hide it).
 * @param {Function} props.onAdd
 * @param {Function} props.onEdit          Called with an item's key.
 * @param {Function} props.onDelete        Called with an item's key.
 * @param {Function} props.onDeletePast
 * @param {boolean}  props.busy            Disables every action.
 */
const ExceptionList = ({
	items,
	noun,
	addLabel,
	emptyText,
	limitText,
	count,
	max,
	pastTitle,
	deletePastLabel,
	onAdd,
	onEdit,
	onDelete,
	onDeletePast,
	busy = false,
}) => {
	const unreadable = items.filter((item) => item.unreadable);
	const upcoming = items.filter((item) => !item.unreadable && !item.past);
	const past = items.filter((item) => !item.unreadable && item.past);
	const atMax = count >= max;

	const row = (item) => (
		<li
			key={item.key}
			className={`hourgate-exceptions__item${item.unreadable ? ' is-unreadable' : ''}`}
		>
			<div className="hourgate-exceptions__text">
				<span className="hourgate-exceptions__dates">
					{item.dates}
				</span>
				<span className="hourgate-exceptions__label">
					{item.label || __('No label', 'hourgate')}
				</span>
				{(item.notes || []).map((note) => (
					<small
						key={note}
						className="hourgate-exceptions__note"
					>
						{note}
					</small>
				))}
			</div>
			<div className="hourgate-exceptions__actions">
				{item.action && (
					<Button
						size="small"
						onClick={item.action.onClick}
						disabled={busy}
					>
						<span aria-hidden="true">{item.action.label}</span>
						<span className="screen-reader-text">
							{item.action.ariaLabel}
						</span>
					</Button>
				)}
				<button
					type="button"
					className="hourgate-exceptions__action"
					onClick={() => onEdit(item.key)}
					disabled={busy}
					aria-label={sprintf(
						/* translators: 1: "closure" or "special hours"; 2: the entry's dates. */
						__('Edit %1$s: %2$s', 'hourgate'),
						noun,
						item.dates
					)}
				>
					<FiEdit2 aria-hidden="true" focusable="false" />
				</button>
				<button
					type="button"
					className="hourgate-exceptions__action is-danger"
					onClick={() => onDelete(item.key)}
					disabled={busy}
					aria-label={sprintf(
						/* translators: 1: "closure" or "special hours"; 2: the entry's dates. */
						__('Delete %1$s: %2$s', 'hourgate'),
						noun,
						item.dates
					)}
				>
					<FiTrash2 aria-hidden="true" focusable="false" />
				</button>
			</div>
		</li>
	);

	return (
		<div className="hourgate-exceptions">
			<div className="hourgate-exceptions__toolbar">
				<Button
					type="primary"
					onClick={onAdd}
					disabled={busy || atMax}
					icon={<FiPlus aria-hidden="true" focusable="false" />}
				>
					{addLabel}
				</Button>
				{atMax && <small>{limitText}</small>}
			</div>

			{unreadable.length > 0 && (
				<ul
					className="hourgate-exceptions__list"
					aria-label={__(
						'Entries that can’t be read',
						'hourgate'
					)}
				>
					{unreadable.map(row)}
				</ul>
			)}

			{upcoming.length > 0 ? (
				<ul className="hourgate-exceptions__list">
					{upcoming.map(row)}
				</ul>
			) : (
				<p className="hourgate-exceptions__empty">{emptyText}</p>
			)}

			{past.length > 0 && (
				<details className="hourgate-exceptions__past">
					<summary>{sprintf(pastTitle, past.length)}</summary>
					<ul className="hourgate-exceptions__list">
						{past.map(row)}
					</ul>
					{deletePastLabel && (
						<Button onClick={onDeletePast} disabled={busy}>
							{deletePastLabel}
						</Button>
					)}
				</details>
			)}
		</div>
	);
};

export default ExceptionList;
