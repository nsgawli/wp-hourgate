import React, { useEffect, useId, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { IoMdClose } from 'react-icons/io';
import './index.css';

const FOCUSABLE =
	'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * A modal dialog (WAI-ARIA dialog pattern):
 * - `role="dialog"`, `aria-modal`, named by its `title` (or `ariaLabel`);
 * - focus moves into the dialog on open (`initialFocusRef`, else the first
 *   focusable element), Tab and Shift+Tab stay inside, and focus returns to
 *   the element that opened it on close;
 * - Esc closes it, and so does a backdrop click unless `closeOnBackdrop` is
 *   false (use that for forms, so a stray click can't discard input).
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen
 * @param {Function} props.onClose
 * @param {string}   props.title           Visible heading and accessible name.
 * @param {string}   props.ariaLabel       Accessible name when there's no title.
 * @param {string}   props.size            'small' | 'medium' | 'large'.
 * @param {boolean}  props.closeIcon       Show the close button.
 * @param {boolean}  props.closeOnBackdrop
 * @param {Object}   props.initialFocusRef Element to focus on open.
 * @param {string}   props.role            'dialog' or 'alertdialog'.
 * @param {*}        props.children        Dialog content.
 */
const ModalPopup = ({
	isOpen,
	onClose,
	children,
	title = '',
	ariaLabel = '',
	size = 'medium',
	closeIcon = true,
	closeOnBackdrop = true,
	initialFocusRef = null,
	role = 'dialog',
}) => {
	const dialogRef = useRef(null);
	const onCloseRef = useRef(onClose);
	const titleId = useId();
	onCloseRef.current = onClose;

	useEffect(() => {
		if (!isOpen) {
			return undefined;
		}
		const dialog = dialogRef.current;
		const doc = dialog?.ownerDocument;
		const opener = doc?.activeElement;
		const first =
			initialFocusRef?.current ||
			dialog?.querySelector(FOCUSABLE) ||
			dialog;
		first?.focus();

		const onKeyDown = (event) => {
			if (event.key === 'Escape') {
				event.stopPropagation();
				onCloseRef.current?.();
				return;
			}
			if (event.key !== 'Tab' || !dialog) {
				return;
			}
			const focusable = [...dialog.querySelectorAll(FOCUSABLE)].filter(
				(el) => el.offsetParent !== null
			);
			if (!focusable.length) {
				event.preventDefault();
				dialog.focus();
				return;
			}
			const firstEl = focusable[0];
			const lastEl = focusable[focusable.length - 1];
			if (
				event.shiftKey &&
				(doc.activeElement === firstEl ||
					!dialog.contains(doc.activeElement))
			) {
				event.preventDefault();
				lastEl.focus();
			} else if (
				!event.shiftKey &&
				(doc.activeElement === lastEl ||
					!dialog.contains(doc.activeElement))
			) {
				event.preventDefault();
				firstEl.focus();
			}
		};
		document.addEventListener('keydown', onKeyDown, true);

		return () => {
			document.removeEventListener('keydown', onKeyDown, true);
			if (
				opener &&
				typeof opener.focus === 'function' &&
				document.contains(opener)
			) {
				opener.focus();
			}
		};
		// Only on open and close: re-running on every render would steal focus.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [isOpen]);

	if (!isOpen) {
		return null;
	}

	return (
		<div
			className="hourgate-modal-backdrop"
			// Mouse only: the keyboard equivalent is Esc.
			role="presentation"
			onMouseDown={(event) => {
				if (closeOnBackdrop && event.target === event.currentTarget) {
					onClose();
				}
			}}
		>
			<div
				ref={dialogRef}
				className={`hourgate-modal-content hourgate-modal-${size}`}
				role={role}
				aria-modal="true"
				aria-labelledby={title ? titleId : undefined}
				aria-label={!title && ariaLabel ? ariaLabel : undefined}
				tabIndex={-1}
			>
				{title && (
					<h2 id={titleId} className="hourgate-modal-title">
						{title}
					</h2>
				)}
				{children}
				{closeIcon && (
					<button
						type="button"
						className="hourgate-modal-close-icon"
						onClick={onClose}
						aria-label={__('Close', 'hourgate')}
					>
						<IoMdClose aria-hidden="true" focusable="false" />
					</button>
				)}
			</div>
		</div>
	);
};

export default ModalPopup;
