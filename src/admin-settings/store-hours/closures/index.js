import React, { useCallback, useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, ScreenLoader, ConfirmDialog } from '@framework/components';
import { storeToday } from '@framework/utils/siteTime';
import { useAlerts } from '@framework/hooks/useAlerts';
import { getClosures, saveClosures, validationErrors } from '../resource';
import ExceptionList from '../ExceptionList';
import FallbackNotice from '../FallbackNotice';
import ClosureDialog from './ClosureDialog';
import { MAX_CLOSURES, toRows } from './model';
import './index.css';

/**
 * Closures: dates the store is closed all day. Every change saves at once
 * (`PUT /settings/closures { closures }`; special hours are kept by the
 * server). The list is kept in stored order and sent back in that order, so
 * server errors point at the right entry.
 */
const Closures = () => {
	const [list, setList] = useState(null);
	const [loadError, setLoadError] = useState('');
	const [savedVersion, setSavedVersion] = useState(0);
	const [dialog, setDialog] = useState(null); // { index, entry } or null
	const [confirm, setConfirm] = useState(null); // { title, message, label, next } or null
	const [busy, setBusy] = useState(false);
	const { addAlert } = useAlerts();
	const today = storeToday();

	const load = useCallback(() => {
		setLoadError('');
		setList(null);
		getClosures()
			.then((data) =>
				setList(Array.isArray(data?.closures) ? data.closures : [])
			)
			.catch((error) =>
				setLoadError(
					error?.message || __('Unknown error.', 'hourgate')
				)
			);
	}, []);

	useEffect(load, [load]);

	/**
	 * Save the whole list; resolves on success, rejects with `{ byPath }` or the error.
	 *
	 * @param {Array}  next        The full list to store.
	 * @param {string} successText Alert text on success.
	 * @return {Promise<void>} Resolves when saved.
	 */
	const persist = async (next, successText) => {
		try {
			const data = await saveClosures({ closures: next });
			setList(Array.isArray(data?.closures) ? data.closures : next);
			setSavedVersion((v) => v + 1);
			addAlert({
				id: Date.now(),
				type: 'success',
				title: __('Saved', 'hourgate'),
				description: successText,
			});
		} catch (error) {
			const byPath = validationErrors(error);
			throw byPath ? { byPath } : error;
		}
	};

	const failed = (error) =>
		addAlert({
			id: Date.now(),
			type: 'error',
			title: __('Not saved', 'hourgate'),
			description: error?.byPath
				? Object.values(error.byPath).join(' ')
				: error?.message || __('Unknown error.', 'hourgate'),
		});

	if (loadError) {
		return (
			<div role="alert">
				<p>
					{sprintf(
						/* translators: %s: error message. */
						__(
							'The closures couldn’t be loaded: %s',
							'hourgate'
						),
						loadError
					)}
				</p>
				<Button onClick={load}>
					{__('Try again', 'hourgate')}
				</Button>
			</div>
		);
	}
	if (!list) {
		return <ScreenLoader />;
	}

	const rows = toRows(list, today);
	const byKey = Object.fromEntries(rows.map((row) => [row.key, row]));
	const unreadableCount = rows.filter((row) => row.unreadable).length;

	const onSaveEntry = (entry, index) => {
		const next = [...list];
		next[index] = entry;
		return persist(next, __('Closure saved.', 'hourgate')).then(() =>
			setDialog(null)
		);
	};

	const askDelete = (key) => {
		const row = byKey[key];
		setConfirm({
			title: __('Delete this closure?', 'hourgate'),
			message: row.label ? `${row.dates} — ${row.label}` : row.dates,
			label: __('Delete', 'hourgate'),
			next: list.filter((_, i) => i !== row.index),
			success: __('Closure deleted.', 'hourgate'),
		});
	};

	const askDeletePast = () => {
		const pastIndexes = new Set(
			rows.filter((row) => row.past).map((row) => row.index)
		);
		setConfirm({
			title: __('Delete past closures?', 'hourgate'),
			message: sprintf(
				/* translators: %d: number of closures. */
				_n(
					'%d one-off closure that has ended will be deleted. Yearly closures are kept.',
					'%d one-off closures that have ended will be deleted. Yearly closures are kept.',
					pastIndexes.size,
					'hourgate'
				),
				pastIndexes.size
			),
			label: __('Delete past closures', 'hourgate'),
			next: list.filter((_, i) => !pastIndexes.has(i)),
			success: __('Past closures deleted.', 'hourgate'),
		});
	};

	return (
		<div className="hourgate-closures">
			<p className="hourgate-closures__intro">
				{__(
					'To close for a whole day, add a closure. A closure also ends the night before at midnight (for example, a Friday 22:00–02:00 range stops at 00:00 when Saturday is closed). Changes are saved as soon as you confirm them.',
					'hourgate'
				)}
			</p>
			<FallbackNotice refreshKey={savedVersion} />
			{unreadableCount > 0 && (
				<div className="hourgate-store-hours-notice is-warning">
					{sprintf(
						/* translators: %d: number of closures. */
						_n(
							'%d saved closure can’t be read. Edit or delete it; other changes can’t be saved until it’s fixed.',
							'%d saved closures can’t be read. Edit or delete them; other changes can’t be saved until they’re fixed.',
							unreadableCount,
							'hourgate'
						),
						unreadableCount
					)}
				</div>
			)}
			<ExceptionList
				items={rows}
				noun={__('closure', 'hourgate')}
				addLabel={__('Add closure', 'hourgate')}
				emptyText={__('No upcoming closures.', 'hourgate')}
				limitText={sprintf(
					/* translators: %d: the most closures allowed. */
					__(
						'Up to %d closures. Delete past or unused ones to add more.',
						'hourgate'
					),
					MAX_CLOSURES
				)}
				count={list.length}
				max={MAX_CLOSURES}
				pastTitle={
					/* translators: %d: number of past closures. */
					__('Past closures (%d)', 'hourgate')
				}
				deletePastLabel={__('Delete past closures', 'hourgate')}
				busy={busy}
				onAdd={() => setDialog({ index: list.length, entry: null })}
				onEdit={(key) =>
					setDialog({
						index: byKey[key].index,
						entry: list[byKey[key].index],
					})
				}
				onDelete={askDelete}
				onDeletePast={askDeletePast}
			/>
			{dialog !== null && (
				<ClosureDialog
					isOpen
					entry={dialog.entry}
					index={dialog.index}
					today={today}
					onSave={onSaveEntry}
					onClose={() => setDialog(null)}
				/>
			)}
			<ConfirmDialog
				isOpen={confirm !== null}
				title={confirm?.title}
				message={confirm?.message}
				confirmLabel={confirm?.label}
				destructive
				busy={busy}
				onCancel={() => setConfirm(null)}
				onConfirm={async () => {
					setBusy(true);
					try {
						await persist(confirm.next, confirm.success);
					} catch (error) {
						failed(error);
					} finally {
						setBusy(false);
						setConfirm(null);
					}
				}}
			/>
		</div>
	);
};

export default Closures;
