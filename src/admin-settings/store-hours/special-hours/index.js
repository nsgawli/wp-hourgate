import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, ScreenLoader, ConfirmDialog } from '@framework/components';
import { storeToday } from '@framework/utils/siteTime';
import { useAlerts } from '@framework/hooks/useAlerts';
import { getClosures, saveClosures, validationErrorList } from '../resource';
import ExceptionList from '../ExceptionList';
import FallbackNotice from '../FallbackNotice';
import SpecialHoursDialog from './SpecialHoursDialog';
import {
	MAX_SPECIAL_HOURS,
	toRows,
	describeDate,
	convertToClosure,
} from './model';
import '../closures/index.css';

/**
 * Special hours: dates with different opening hours. Every change saves at
 * once (`PUT /settings/closures { specialHours }`; the server keeps the
 * closures). Converting a "closed" entry saves both lists in one PUT.
 */
const SpecialHours = () => {
	const [lists, setLists] = useState(null); // { closures, specialHours }
	const [loadError, setLoadError] = useState('');
	const [savedVersion, setSavedVersion] = useState(0);
	const [dialog, setDialog] = useState(null); // { index, entry } or null
	const [confirm, setConfirm] = useState(null);
	const [busy, setBusy] = useState(false);
	const { addAlert } = useAlerts();
	const navigate = useNavigate();
	const today = storeToday();

	const accept = (data, fallback) =>
		setLists({
			closures: Array.isArray(data?.closures)
				? data.closures
				: fallback.closures,
			specialHours: Array.isArray(data?.specialHours)
				? data.specialHours
				: fallback.specialHours,
		});

	const load = useCallback(() => {
		setLoadError('');
		setLists(null);
		getClosures()
			.then((data) => accept(data, { closures: [], specialHours: [] }))
			.catch((error) =>
				setLoadError(
					error?.message || __('Unknown error.', 'hourgate')
				)
			);
	}, []); // eslint-disable-line react-hooks/exhaustive-deps

	useEffect(load, [load]);

	/**
	 * Save; resolves on success, rejects with `{ errors, sent }` or the error.
	 *
	 * @param {Object} body        `{ specialHours }`, or both lists.
	 * @param {string} successText Alert text on success.
	 * @return {Promise<void>} Resolves when saved.
	 */
	const persist = async (body, successText) => {
		try {
			const data = await saveClosures(body);
			accept(data, { ...lists, ...body });
			setSavedVersion((v) => v + 1);
			addAlert({
				id: Date.now(),
				type: 'success',
				title: __('Saved', 'hourgate'),
				description: successText,
			});
		} catch (error) {
			const errors = validationErrorList(error);
			throw errors ? { errors, sent: body.specialHours } : error;
		}
	};

	if (loadError) {
		return (
			<div role="alert">
				<p>
					{sprintf(
						/* translators: %s: error message. */
						__(
							'The special hours couldn’t be loaded: %s',
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
	if (!lists) {
		return <ScreenLoader />;
	}

	const list = lists.specialHours;

	const askConvert = (index) => {
		const entry = list[index];
		setConfirm({
			title: __('Convert to a closure?', 'hourgate'),
			message: sprintf(
				/* translators: %s: the entry's date. */
				__(
					'%s becomes a one-day closure with the same label. Unlike empty special hours, a closure also ends the night before at midnight.',
					'hourgate'
				),
				describeDate(entry)
			),
			label: __('Convert', 'hourgate'),
			destructive: false,
			body: convertToClosure(lists.closures, list, index),
			success: __('Converted to a closure.', 'hourgate'),
		});
	};

	const rows = toRows(list, today, askConvert);
	const byKey = Object.fromEntries(rows.map((row) => [row.key, row]));
	const unreadableCount = rows.filter((row) => row.unreadable).length;

	const onSaveEntry = (entry, index) => {
		const next = [...list];
		next[index] = entry;
		return persist(
			{ specialHours: next },
			__('Special hours saved.', 'hourgate')
		).then(() => setDialog(null));
	};

	const askDelete = (key) => {
		const row = byKey[key];
		setConfirm({
			title: __('Delete these special hours?', 'hourgate'),
			message: row.label ? `${row.dates} — ${row.label}` : row.dates,
			label: __('Delete', 'hourgate'),
			destructive: true,
			body: { specialHours: list.filter((_, i) => i !== row.index) },
			success: __('Special hours deleted.', 'hourgate'),
		});
	};

	const askDeletePast = () => {
		const past = new Set(
			rows.filter((row) => row.past).map((row) => row.index)
		);
		setConfirm({
			title: __('Delete past special hours?', 'hourgate'),
			message: sprintf(
				/* translators: %d: number of entries. */
				_n(
					'%d one-off entry whose date has passed will be deleted. Yearly entries are kept.',
					'%d one-off entries whose dates have passed will be deleted. Yearly entries are kept.',
					past.size,
					'hourgate'
				),
				past.size
			),
			label: __('Delete past special hours', 'hourgate'),
			destructive: true,
			body: { specialHours: list.filter((_, i) => !past.has(i)) },
			success: __('Past special hours deleted.', 'hourgate'),
		});
	};

	return (
		<div className="hourgate-closures">
			<p className="hourgate-closures__intro">
				{__(
					'Special hours replace the weekly hours on one date, for example a late opening before a holiday. To close for the whole day, add a closure instead. Changes are saved as soon as you confirm them.',
					'hourgate'
				)}
			</p>
			<FallbackNotice refreshKey={savedVersion} />
			{unreadableCount > 0 && (
				<div className="hourgate-store-hours-notice is-warning">
					{sprintf(
						/* translators: %d: number of entries. */
						_n(
							'%d saved special-hours entry can’t be read. Edit or delete it; other changes can’t be saved until it’s fixed.',
							'%d saved special-hours entries can’t be read. Edit or delete them; other changes can’t be saved until they’re fixed.',
							unreadableCount,
							'hourgate'
						),
						unreadableCount
					)}
				</div>
			)}
			<ExceptionList
				items={rows}
				noun={__('special hours', 'hourgate')}
				addLabel={__('Add special hours', 'hourgate')}
				emptyText={__('No upcoming special hours.', 'hourgate')}
				limitText={sprintf(
					/* translators: %d: the most entries allowed. */
					__(
						'Up to %d special-hours entries. Delete past or unused ones to add more.',
						'hourgate'
					),
					MAX_SPECIAL_HOURS
				)}
				count={list.length}
				max={MAX_SPECIAL_HOURS}
				pastTitle={
					/* translators: %d: number of past entries. */
					__('Past special hours (%d)', 'hourgate')
				}
				deletePastLabel={__(
					'Delete past special hours',
					'hourgate'
				)}
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
				<SpecialHoursDialog
					entry={dialog.entry}
					index={dialog.index}
					today={today}
					onSave={onSaveEntry}
					onClose={() => setDialog(null)}
					onClosuresTab={() => navigate('/store-hours/closures')}
				/>
			)}
			<ConfirmDialog
				isOpen={confirm !== null}
				title={confirm?.title}
				message={confirm?.message}
				confirmLabel={confirm?.label}
				destructive={confirm?.destructive}
				busy={busy}
				onCancel={() => setConfirm(null)}
				onConfirm={async () => {
					setBusy(true);
					try {
						await persist(confirm.body, confirm.success);
					} catch (error) {
						addAlert({
							id: Date.now(),
							type: 'error',
							title: __('Not saved', 'hourgate'),
							description: error?.errors
								? error.errors.map((e) => e.message).join(' ')
								: error?.message ||
									__('Unknown error.', 'hourgate'),
						});
					} finally {
						setBusy(false);
						setConfirm(null);
					}
				}}
			/>
		</div>
	);
};

export default SpecialHours;
