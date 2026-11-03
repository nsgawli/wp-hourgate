import React, { useRef } from 'react';
import { __ } from '@wordpress/i18n';
import ModalPopup from '@framework/components/ModalPopup';
import Button from '@framework/components/Button';
import './index.css';

/**
 * "Are you sure?" built on ModalPopup. Initial focus is on Cancel, so Enter
 * never confirms by accident. The destructive variant colours the confirm
 * button and uses `role="alertdialog"`.
 *
 * @param {Object}   props
 * @param {boolean}  props.isOpen
 * @param {string}   props.title
 * @param {*}        props.message      Text or elements.
 * @param {string}   props.confirmLabel
 * @param {string}   props.cancelLabel
 * @param {Function} props.onConfirm
 * @param {Function} props.onCancel     Also used for Esc and the close button.
 * @param {boolean}  props.destructive
 * @param {boolean}  props.busy         Shows a spinner and blocks both buttons.
 */
const ConfirmDialog = ({
	isOpen,
	title,
	message,
	confirmLabel = __('Confirm', 'hourgate'),
	cancelLabel = __('Cancel', 'hourgate'),
	onConfirm,
	onCancel,
	destructive = false,
	busy = false,
}) => {
	const cancelRef = useRef(null);
	const close = () => !busy && onCancel();

	return (
		<ModalPopup
			isOpen={isOpen}
			onClose={close}
			title={title}
			size="small"
			closeOnBackdrop={!busy}
			initialFocusRef={cancelRef}
			role={destructive ? 'alertdialog' : 'dialog'}
		>
			<div className="hourgate-confirm-dialog__message">
				{message}
			</div>
			<div className="hourgate-confirm-dialog__actions">
				<Button ref={cancelRef} onClick={close} disabled={busy}>
					{cancelLabel}
				</Button>
				<Button
					type={destructive ? 'danger' : 'primary'}
					onClick={onConfirm}
					loading={busy}
				>
					{confirmLabel}
				</Button>
			</div>
		</ModalPopup>
	);
};

export default ConfirmDialog;
