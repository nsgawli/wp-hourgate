import React, {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useLayoutEffect,
	useMemo,
	useRef,
	useState,
} from 'react';
import { useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { ConfirmDialog } from '@framework/components';

/**
 * Unsaved edits on the Settings screen, kept above the routes.
 *
 * - **Drafts survive navigation.** An editor keeps its edits here
 *   (`useDraft( key )`), not in its own state, so switching sub-tabs, or
 *   leaving Store hours and coming back, never loses them.
 * - **Each draft has a scope**, the path it survives within. The weekly
 *   draft lives here, so it survives anywhere under `/store-hours`; an open
 *   dialog's form lives in the dialog, so its scope is its own sub-tab
 *   (`useDraft( 'closure-dialog', '/store-hours/closures' )`).
 * - **A move that would leave a draft's scope asks first:** the side menu
 *   (`requestLeave()`), the browser's Back and Forward buttons, and reloading
 *   or closing the page (`beforeunload`, for any draft).
 *
 * The router is a `HashRouter`, which has no `useBlocker`, so Back and Forward
 * are caught here: a capture-phase `popstate` listener runs before
 * react-router's own and, when the move would leave a draft's scope, stops
 * it, undoes it with `history.go()`, and asks. react-router's history keeps
 * an index in `history.state.idx`, which gives the distance to undo.
 */

const UnsavedChangesContext = createContext(null);

const pathOfHash = (hash) =>
	(hash || '').replace(/^#/, '').split('?')[0] || '/';

export function UnsavedChangesProvider({ guardedPath, children }) {
	// { [key]: { value, scope } }; a value of undefined means no draft.
	const [drafts, setDrafts] = useState({});
	const [pending, setPending] = useState(null);
	const location = useLocation();
	const dirty = Object.values(drafts).some(
		(draft) => draft.value !== undefined
	);

	const draftsRef = useRef(drafts);
	draftsRef.current = drafts;
	const indexRef = useRef(null);
	const swallowNextPop = useRef(false);

	/**
	 * Whether moving to `path` would leave the scope of any draft.
	 *
	 * @param {string} path A route path.
	 * @return {boolean} True when some draft would be lost.
	 */
	const blocks = (path) =>
		Object.values(draftsRef.current).some(
			(draft) =>
				draft.value !== undefined &&
				path !== draft.scope &&
				!path.startsWith(`${draft.scope}/`)
		);

	// The history index of the location the app is showing.
	useEffect(() => {
		indexRef.current = window.history.state?.idx ?? null;
	}, [location]);

	// Registered in a layout effect of a child of the router, and in the
	// capture phase, so it runs before react-router's popstate listener.
	useLayoutEffect(() => {
		const onPopState = (event) => {
			if (swallowNextPop.current) {
				swallowNextPop.current = false;
				event.stopImmediatePropagation();
				return;
			}
			if (!blocks(pathOfHash(window.location.hash))) {
				return;
			}
			const nextIndex = window.history.state?.idx;
			const delta =
				Number.isInteger(nextIndex) &&
				Number.isInteger(indexRef.current)
					? nextIndex - indexRef.current
					: null;
			if (!delta) {
				// No index (a URL typed by hand): let it through, as there's
				// nothing to undo it with. beforeunload still covers leaving
				// the page.
				return;
			}
			event.stopImmediatePropagation();
			swallowNextPop.current = true;
			window.history.go(-delta);
			setPending({
				proceed: () => {
					swallowNextPop.current = false;
					window.history.go(delta);
				},
			});
		};
		window.addEventListener('popstate', onPopState, true);
		return () => window.removeEventListener('popstate', onPopState, true);
		// Once: the handler reads refs, and re-registering would put it
		// after react-router's listener.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, []);

	useEffect(() => {
		if (!dirty) {
			return undefined;
		}
		const onBeforeUnload = (event) => {
			event.preventDefault();
			// Older browsers need a return value; the text isn't shown.
			event.returnValue = '';
		};
		window.addEventListener('beforeunload', onBeforeUnload);
		return () => window.removeEventListener('beforeunload', onBeforeUnload);
	}, [dirty]);

	const setDraft = useCallback(
		(key, value, scope) =>
			setDrafts((all) => ({ ...all, [key]: { value, scope } })),
		[]
	);

	const requestLeave = useCallback(
		(targetPath, proceed) => {
			if (!blocks(targetPath)) {
				proceed();
				return;
			}
			setPending({ proceed });
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[]
	);

	const value = useMemo(
		() => ({ drafts, setDraft, dirty, requestLeave, guardedPath }),
		[drafts, setDraft, dirty, requestLeave, guardedPath]
	);

	return (
		<UnsavedChangesContext.Provider value={value}>
			{children}
			<ConfirmDialog
				isOpen={pending !== null}
				title={__('Leave without saving?', 'hourgate')}
				message={__(
					'You have unsaved changes on this screen. If you leave now, they are lost.',
					'hourgate'
				)}
				confirmLabel={__('Discard changes', 'hourgate')}
				cancelLabel={__('Stay', 'hourgate')}
				destructive
				onCancel={() => setPending(null)}
				onConfirm={() => {
					const { proceed } = pending;
					setPending(null);
					setDrafts({});
					// Before the next render, so the move being replayed isn't caught again.
					draftsRef.current = {};
					proceed();
				}}
			/>
		</UnsavedChangesContext.Provider>
	);
}

/** `{ dirty, requestLeave( targetPath, proceed ) }` for navigation code. */
export const useUnsavedChanges = () => useContext(UnsavedChangesContext);

/**
 * A draft kept above the routes. `undefined` means "no unsaved edits".
 *
 * @param {string} key   A name unique to the editor, e.g. `weekly`.
 * @param {string} scope The path the draft survives within; leaving it asks
 *                       first. Defaults to the provider's `guardedPath`. Use
 *                       the sub-tab's own path for state that unmounts with
 *                       the sub-tab (an open dialog).
 * @return {Array} `[ draft, setDraft( value ), clearDraft() ]`.
 */
export const useDraft = (key, scope) => {
	const { drafts, setDraft, guardedPath } = useContext(UnsavedChangesContext);
	const where = scope || guardedPath;
	return [
		drafts[key]?.value,
		(value) => setDraft(key, value, where),
		() => setDraft(key, undefined, where),
	];
};
