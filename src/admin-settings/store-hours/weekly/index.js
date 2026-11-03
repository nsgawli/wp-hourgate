import React, { useCallback, useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	ScreenLoader,
	ConfirmDialog,
	TimeRangesEditor,
} from '@framework/components';
import { getSiteSettings } from '@framework/utils/siteTime';
import { useAlerts } from '@framework/hooks/useAlerts';
import { useDraft } from '../../unsaved-changes';
import {
	getSchedule,
	getScheduleDefaults,
	saveSchedule,
	validationErrors,
} from '../resource';
import {
	MAX_RANGES,
	orderedDays,
	weekdayIndex,
	fromStored,
	unknownKeys,
	toPayload,
	withMode,
	undecidedDays,
	validate,
	placeServerErrors,
	copyDay,
	sameModel,
} from './model';
import CopyDialog from './CopyDialog';
import FallbackNotice from '../FallbackNotice';
import './index.css';

const NO_SERVER_ERRORS = { placed: {}, unplaced: [] };

/**
 * Only row-level problems (same times, overlaps) show while typing; empty fields wait for Save.
 *
 * @param {Object} errors Errors by day, then by key.
 * @return {Object} Only the row-level ones.
 */
const rowLevelOnly = (errors) => {
	const out = {};
	Object.entries(errors).forEach(([day, map]) => {
		const rows = Object.fromEntries(
			Object.entries(map).filter(([key]) => !key.includes('.'))
		);
		if (Object.keys(rows).length) {
			out[day] = rows;
		}
	});
	return out;
};

const WeeklyHours = () => {
	const site = getSiteSettings();
	const days = orderedDays(site.startOfWeek);
	const names = Object.fromEntries(
		days.map((day) => [day, site.weekdays[weekdayIndex(day)]])
	);

	const [saved, setSaved] = useState(null);
	const [dropped, setDropped] = useState([]);
	// Bumped after each save, so FallbackNotice checks again.
	const [savedVersion, setSavedVersion] = useState(0);
	const [loadError, setLoadError] = useState('');
	const [draft, setDraft, clearDraft] = useDraft('weekly');
	const [attempted, setAttempted] = useState(false);
	const [serverErrors, setServerErrors] = useState(NO_SERVER_ERRORS);
	const [saveError, setSaveError] = useState('');
	const [saving, setSaving] = useState(false);
	const [confirmReset, setConfirmReset] = useState(false);
	const [resetting, setResetting] = useState(false);
	const [copyFrom, setCopyFrom] = useState(null);
	const summaryRef = useRef(null);
	const { addAlert } = useAlerts();

	const load = useCallback(() => {
		setLoadError('');
		setSaved(null);
		getSchedule()
			.then((data) => {
				setSaved(fromStored(data?.weekly));
				setDropped(unknownKeys(data?.weekly));
			})
			.catch((error) =>
				setLoadError(
					error?.message || __('Unknown error.', 'hourgate')
				)
			);
	}, []);

	useEffect(load, [load]);

	if (loadError) {
		return (
			<div className="hourgate-weekly__load-error" role="alert">
				<p>
					{sprintf(
						/* translators: %s: error message. */
						__(
							'The weekly hours couldn’t be loaded: %s',
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
	if (!saved) {
		return <ScreenLoader />;
	}

	const model = draft ?? saved;
	const allClientErrors = validate(model);
	const clientErrors = attempted
		? allClientErrors
		: rowLevelOnly(allClientErrors);
	const undecided = undecidedDays(model);
	const busy = saving || resetting;

	const update = (next) => {
		if (sameModel(next, saved)) {
			clearDraft();
		} else {
			setDraft(next);
		}
		setServerErrors(NO_SERVER_ERRORS);
		setSaveError('');
	};
	const updateDay = (day, dayModel) => update({ ...model, [day]: dayModel });

	const focusSummary = () =>
		window.setTimeout(() => summaryRef.current?.focus(), 0);

	const onSave = async () => {
		setAttempted(true);
		setSaveError('');
		setServerErrors(NO_SERVER_ERRORS);
		if (undecided.length || Object.keys(allClientErrors).length) {
			focusSummary();
			return;
		}
		setSaving(true);
		try {
			const data = await saveSchedule(toPayload(model));
			setSaved(fromStored(data?.weekly));
			setDropped([]);
			clearDraft();
			setAttempted(false);
			setSavedVersion((v) => v + 1);
			addAlert({
				id: Date.now(),
				type: 'success',
				title: __('Saved', 'hourgate'),
				description: __('Weekly hours saved.', 'hourgate'),
			});
		} catch (error) {
			const byPath = validationErrors(error);
			if (byPath) {
				setServerErrors(placeServerErrors(byPath, model));
			} else {
				setSaveError(
					error?.message || __('Unknown error.', 'hourgate')
				);
			}
			focusSummary();
		} finally {
			setSaving(false);
		}
	};

	const onReset = async () => {
		setResetting(true);
		setSaveError('');
		try {
			const defaults = await getScheduleDefaults();
			const data = await saveSchedule(defaults?.weekly);
			setSaved(fromStored(data?.weekly));
			setDropped([]);
			clearDraft();
			setAttempted(false);
			setServerErrors(NO_SERVER_ERRORS);
			setSavedVersion((v) => v + 1);
			addAlert({
				id: Date.now(),
				type: 'success',
				title: __('Saved', 'hourgate'),
				description: __(
					'Weekly hours reset: open 24 hours every day.',
					'hourgate'
				),
			});
		} catch (error) {
			setSaveError(
				error?.message || __('Unknown error.', 'hourgate')
			);
			focusSummary();
		} finally {
			setResetting(false);
			setConfirmReset(false);
		}
	};

	const errorsFor = (day) => ({
		...(clientErrors[day] || {}),
		...(serverErrors.placed[day] || {}),
	});
	const placedCount =
		Object.keys(clientErrors).length +
		Object.keys(serverErrors.placed).length;

	const summary = [];
	if (saveError) {
		summary.push(
			sprintf(
				/* translators: %s: error message. */
				__(
					'The weekly hours weren’t saved: %s Your changes are kept; try again.',
					'hourgate'
				),
				saveError
			)
		);
	}
	if (attempted && undecided.length) {
		summary.push(
			sprintf(
				/* translators: %s: list of day names. */
				__('Choose the hours for: %s.', 'hourgate'),
				undecided.map((day) => names[day]).join(', ')
			)
		);
	}
	if (attempted && placedCount) {
		summary.push(
			__(
				'The weekly hours weren’t saved. Fix the highlighted times.',
				'hourgate'
			)
		);
	}
	summary.push(...serverErrors.unplaced);

	return (
		<div className="hourgate-weekly">
			<p className="hourgate-weekly__intro">
				{__(
					'Set when the store is open on each day of the week. A closing time before the opening time runs past midnight, and a closing time of 00:00 means midnight.',
					'hourgate'
				)}
			</p>

			<FallbackNotice refreshKey={savedVersion} />
			{dropped.length > 0 && (
				<div className="hourgate-weekly__banner">
					{sprintf(
						/* translators: %s: list of unknown keys found in the saved hours. */
						__(
							'The saved hours contain entries HourGate doesn’t recognise (%s). They are removed when you save.',
							'hourgate'
						),
						dropped
							.map((key) => String(key).slice(0, 40))
							.join(', ')
					)}
				</div>
			)}

			<div
				ref={summaryRef}
				tabIndex={-1}
				aria-live="polite"
				className={`hourgate-weekly__summary${summary.length ? ' has-errors' : ''}`}
			>
				{summary.length > 0 && (
					<ul>
						{summary.map((message, index) => (
							<li key={index}>{message}</li>
						))}
					</ul>
				)}
			</div>

			<div className="hourgate-weekly__days">
				{days.map((day) => {
					const dayModel = model[day];
					const name = names[day];
					const modeName = `hourgate-weekly-mode-${day}`;
					const unreadableId = `hourgate-weekly-${day}-unreadable`;
					return (
						<fieldset
							key={day}
							className={`hourgate-weekly__day${dayModel.mode === null ? ' is-undecided' : ''}`}
						>
							<legend>{name}</legend>
							{dayModel.mode === null && (
								<p
									id={unreadableId}
									className="hourgate-form-field-error-msg"
								>
									{sprintf(
										/* translators: %s: day name. */
										__(
											'Couldn’t read the saved hours for %s. Choose its hours.',
											'hourgate'
										),
										name
									)}
								</p>
							)}
							<div
								className="hourgate-weekly__modes"
								role="radiogroup"
								aria-label={sprintf(
									/* translators: %s: day name. */
									__('%s hours', 'hourgate'),
									name
								)}
								aria-describedby={
									dayModel.mode === null
										? unreadableId
										: undefined
								}
							>
								{[
									['closed', __('Closed', 'hourgate')],
									[
										'open24',
										__('Open 24 hours', 'hourgate'),
									],
									[
										'custom',
										__('Set hours', 'hourgate'),
									],
								].map(([mode, label]) => (
									<label
										key={mode}
										htmlFor={`${modeName}-${mode}`}
										className="hourgate-weekly__mode"
									>
										<input
											id={`${modeName}-${mode}`}
											type="radio"
											name={modeName}
											value={mode}
											checked={dayModel.mode === mode}
											disabled={busy}
											onChange={() =>
												updateDay(
													day,
													withMode(dayModel, mode)
												)
											}
										/>
										{label}
									</label>
								))}
							</div>
							{dayModel.mode === 'custom' && (
								<TimeRangesEditor
									idPrefix={`hourgate-weekly-${day}`}
									context={name}
									value={dayModel.ranges}
									onChange={(ranges) =>
										updateDay(day, { ...dayModel, ranges })
									}
									errors={errorsFor(day)}
									max={MAX_RANGES}
									disabled={busy}
								/>
							)}
							<div className="hourgate-weekly__copy">
								<Button
									size="small"
									disabled={busy || dayModel.mode === null}
									onClick={() => setCopyFrom(day)}
								>
									<span aria-hidden="true">
										{__('Copy to…', 'hourgate')}
									</span>
									<span className="screen-reader-text">
										{sprintf(
											/* translators: %s: day name. */
											__(
												'Copy %s’s hours to other days',
												'hourgate'
											),
											name
										)}
									</span>
								</Button>
							</div>
						</fieldset>
					);
				})}
			</div>

			<div className="hourgate-weekly__actions">
				<Button
					type="primary"
					onClick={onSave}
					loading={saving}
					disabled={!draft || busy || undecided.length > 0}
				>
					{__('Save', 'hourgate')}
				</Button>
				<Button onClick={() => setConfirmReset(true)} disabled={busy}>
					{__('Reset to defaults', 'hourgate')}
				</Button>
				{undecided.length > 0 && (
					<small>
						{sprintf(
							/* translators: %s: list of day names. */
							__(
								'Choose the hours for %s to save.',
								'hourgate'
							),
							undecided.map((day) => names[day]).join(', ')
						)}
					</small>
				)}
			</div>

			{copyFrom !== null && (
				<CopyDialog
					from={copyFrom}
					days={days}
					names={names}
					onClose={() => setCopyFrom(null)}
					onCopy={(targets) => {
						update(copyDay(model, copyFrom, targets));
						setCopyFrom(null);
					}}
				/>
			)}
			<ConfirmDialog
				isOpen={confirmReset}
				title={__('Reset weekly hours?', 'hourgate')}
				message={__(
					'Every day becomes Open 24 hours, and this is saved now. Closures and special hours are not changed.',
					'hourgate'
				)}
				confirmLabel={__('Reset and save', 'hourgate')}
				destructive
				busy={resetting}
				onCancel={() => setConfirmReset(false)}
				onConfirm={onReset}
			/>
		</div>
	);
};

export default WeeklyHours;
