import React, { useEffect } from 'react';
import './index.css';

const Drawer = ({ isOpen, onClose, children, direction = 'right' }) => {
	const drawerDirectionClass = `drawer-${direction}`;
	const backdropClass = `hourgate-drawer-backdrop ${drawerDirectionClass}`;
	const contentClass = `hourgate-drawer-content ${drawerDirectionClass}`;

	useEffect(() => {
		const handleEsc = (event) => {
			if (event.keyCode === 27) onClose(); // 27 is the ESC key
		};

		if (isOpen) {
			document.addEventListener('keydown', handleEsc);
		}

		return () => {
			document.removeEventListener('keydown', handleEsc);
		};
	}, [isOpen, onClose]); // Re-run the effect if isOpen or onClose changes

	if (!isOpen) return null;

	// Function to stop propagation for modal content clicks
	const stopPropagation = (e) => {
		e.stopPropagation();
	};

	return (
		<div className={backdropClass} onClick={onClose}>
			<div className={contentClass} onClick={stopPropagation}>
				{children}
			</div>
		</div>
	);
};

export default Drawer;
