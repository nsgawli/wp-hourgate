import React, { useEffect, useState } from 'react';
import { Route, Routes, useNavigate, useLocation } from 'react-router-dom';
import {
	Flex,
	AdminPageHeader,
	SideMenu,
	AlertContainer,
} from '@framework/components';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

import ProductIcon from '@framework/icons/productIcon';

import { FiSettings, FiClock, FiShoppingCart } from 'react-icons/fi';

import './index.css';

// pages
import DataManagement from './data-management';
import StoreHours, { BASE_PATH as STORE_HOURS_PATH } from './store-hours';
import Ordering, { BASE_PATH as ORDERING_PATH } from './ordering';
import { useUnsavedChanges } from './unsaved-changes';
import { canManageSite } from './user';

// Store hours first: it's the default screen (user decision, 2026-10-17).
const defaultTabs = [
	{
		key: 'store-hours',
		path: STORE_HOURS_PATH,
		icon: <FiClock />,
		label: __('Store hours', 'hourgate'),
		element: <StoreHours />,
	},
	// Ordering second: the switch, closed mode, message and pause (sprint 4, decision 2).
	{
		key: 'ordering',
		path: ORDERING_PATH,
		icon: <FiShoppingCart />,
		label: __('Ordering', 'hourgate'),
		element: <Ordering />,
	},
	{
		key: 'data-management',
		path: '/data-management',
		icon: <FiSettings />,
		label: __('Data Management', 'hourgate'),
		element: <DataManagement />,
	},
];

function App() {
	const navigate = useNavigate();
	const location = useLocation();
	const [current, setCurrent] = useState('store-hours');
	const { requestLeave } = useUnsavedChanges();

	/**
	 * Extension point: add-ons (e.g. hourgate-pro) can inject additional
	 * settings tabs by filtering this list, each item shaped like
	 * defaultTabs. Evaluated at render time (not module load) so it picks
	 * up filters regardless of script load order.
	 */
	const menuItems = applyFilters(
		'hourgate.settingsTabs',
		// Data Management holds only the admin-only uninstall switch for now, so shop
		// managers don't get the tab (#/data-management falls back to the
		// default tab below). Show it to everyone again once it has a setting
		// they can change.
		defaultTabs.filter(
			(tab) => tab.key !== 'data-management' || canManageSite()
		)
	);

	useEffect(() => {
		if (location.pathname === '/') {
			navigate(STORE_HOURS_PATH, { replace: true });
			return;
		}
		const path = location.pathname.replace(/\/$/, '').split('/');
		if (path.length > 1) {
			const currentRoute = menuItems.find((item) => item.key === path[1]);
			// Unknown or hidden tab (e.g. #/license for a shop manager): show the default tab.
			if (!currentRoute) {
				navigate(STORE_HOURS_PATH, { replace: true });
				return;
			}
			setCurrent(currentRoute.key);
		}
	}, [location]);

	const onClickMenuItem = (key) => {
		if (key === current) {
			return;
		}
		const currentRoute = menuItems.find((item) => item.key === key);
		// Leaving a tab with unsaved edits asks first.
		requestLeave(currentRoute.path, () => {
			setCurrent(key);
			navigate(currentRoute.path);
		});
	};

	return (
		<Flex vertical>
			<AdminPageHeader
				icon={<ProductIcon className="product-icon" />}
				title={__('HourGate', 'hourgate')}
				showHelpButtons
			/>
			<Flex
				className="hourgate-settings-layout"
				style={{ padding: '1.5rem' }}
				gap={25}
			>
				<SideMenu
					items={menuItems}
					current={current}
					onClick={onClickMenuItem}
					collapseKey="hourGateToggleMenuCollapse"
				/>
				<Routes>
					<Route path="/" element={<span />} />
					{menuItems.map((item) => (
						<Route
							key={item.key}
							path={`${item.path}/*`}
							element={item.element}
						/>
					))}
				</Routes>
			</Flex>
			<AlertContainer />
		</Flex>
	);
}

export default App;
