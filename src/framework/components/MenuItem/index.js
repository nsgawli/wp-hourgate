import React from 'react';
import { useScreenSize } from '@framework/hooks/useScreenSize';
import { Flex } from '@framework/components';
import './index.css';

const MenuItem = React.memo(
	({
		label,
		onClick,
		icon = null,
		gap = 5,
		current = false,
		parentType = 'side',
		collapsed = false,
	}) => {
		const { screenSize } = useScreenSize();
		const isMobile = ['xs', 'sm'].includes(screenSize);
		const className = 'hourgate-menu-item' + (current ? ' current' : '');
		const showIconOnly = collapsed || isMobile;
		const style = {};
		if (parentType == 'side' && !showIconOnly) {
			style.minWidth = '8.75rem';
		}
		return (
			<Flex
				align="center"
				gap={gap}
				className={className}
				style={style}
				onClick={onClick}
			>
				{icon && icon}
				{parentType === 'top' && label}
				{parentType === 'side' && !showIconOnly && label}
			</Flex>
		);
	}
);

export default MenuItem;
