import React, { useEffect, useRef } from 'react';
import { Flex, MenuItem, Button } from '@framework/components';
import { FaAngleDown } from 'react-icons/fa6';
import './index.css';

const Dropdown = ({
	items,
	isOpenMenu,
	setIsOpenMenu,
	onMenuItemClick,
	label = null,
	icon = null,
	size = 'medium',
	menuPosition = 'left',
	className = '',
}) => {
	const menuRef = useRef();
	const closeMenu = () => {
		setIsOpenMenu(false);
	};
	useEffect(() => {
		const handleOutsideClick = (event) => {
			if (menuRef.current && !menuRef.current.contains(event.target)) {
				closeMenu();
			}
		};
		const handleEscPress = (event) => {
			if (event.keyCode === 27) {
				closeMenu();
			}
		};
		document.addEventListener('mousedown', handleOutsideClick);
		document.addEventListener('keydown', handleEscPress);
		return () => {
			document.removeEventListener('mousedown', handleOutsideClick);
			document.removeEventListener('keydown', handleEscPress);
		};
	}, []);

	className = `hourgate-dropdown-conatiner ${className}`;
	const menuClassName = `hourgate-dropdown-menu ${menuPosition}`;

	return (
		<div ref={menuRef} className={className}>
			<Button
				onClick={() => setIsOpenMenu(!isOpenMenu)}
				icon={icon || <FaAngleDown />}
				iconPosition="end"
				size={size}
			>
				{label}
			</Button>
			{isOpenMenu && (
				<Flex className={menuClassName} vertical>
					{items.map((item) => (
						<MenuItem
							{...item}
							onClick={() => onMenuItemClick(item.key)}
							gap={10}
						/>
					))}
				</Flex>
			)}
		</div>
	);
};

export default Dropdown;
