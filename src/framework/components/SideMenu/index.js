import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { useScreenSize } from '@framework/hooks/useScreenSize';
import { Flex, MenuItem, Tooltip } from '@framework/components';
import { BsChevronDoubleLeft, BsChevronDoubleRight } from 'react-icons/bs';
import './index.css';

const SideMenu = ({
	items = [],
	current = null,
	onClick = null,
	className = '',
	collapseKey = 'hourGateSideMenuCollapse',
	style = {},
}) => {
	const { screenSize } = useScreenSize();
	const isMobile = ['xs', 'sm'].includes(screenSize);

	// Storage is a convenience: blocked site data or a corrupt value must not
	// blank the screen (found in OW-060), so both fall back to expanded.
	const [isMenuCollapsed, setIsMenuCollapsed] = useState(() => {
		try {
			return (
				JSON.parse(window.localStorage.getItem(collapseKey)) === true
			);
		} catch (e) {
			return false;
		}
	});

	let classNames = ['hourgate-side-menu'];
	if (className !== '') {
		classNames = [...classNames, ...className.split(' ')];
	}

	const toggleMenuCollapse = () => {
		setIsMenuCollapsed((prev) => {
			const newState = !prev;
			try {
				window.localStorage.setItem(
					collapseKey,
					JSON.stringify(newState)
				);
			} catch (e) {
				// Storage blocked: the state lasts until the page reloads.
			}
			return newState;
		});
	};

	return (
		<Flex vertical className={classNames.join(' ')} gap={5} style={style}>
			{items.map((item) =>
				!isMobile && isMenuCollapsed ? (
					<Tooltip content={item.label} key={item.key}>
						<MenuItem
							{...item}
							current={item.key == current}
							onClick={() => onClick(item.key)}
							gap={10}
							parentType="side"
							collapsed={isMenuCollapsed}
						/>
					</Tooltip>
				) : (
					<MenuItem
						{...item}
						current={item.key == current}
						onClick={() => onClick(item.key)}
						gap={10}
						parentType="side"
						collapsed={isMenuCollapsed}
					/>
				)
			)}
			{!isMobile &&
				(isMenuCollapsed ? (
					<Tooltip content={__('Expand', 'hourgate')}>
						<MenuItem
							icon={<BsChevronDoubleRight />}
							label={__('Expand', 'hourgate')}
							onClick={toggleMenuCollapse}
							gap={10}
							parentType="side"
							collapsed={isMenuCollapsed}
						/>
					</Tooltip>
				) : (
					<MenuItem
						icon={<BsChevronDoubleLeft />}
						label={__('Collapse', 'hourgate')}
						onClick={toggleMenuCollapse}
						gap={10}
						parentType="side"
						collapsed={isMenuCollapsed}
					/>
				))}
		</Flex>
	);
};

export default SideMenu;
