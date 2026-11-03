import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { useForm } from 'react-hook-form';
import {
	Flex,
	Content,
	Title,
	Button,
	ScreenLoader,
	Divider,
	ConfirmDialog,
} from '@framework/components';
import { Switch } from '@framework/components/form';

import {
	getGeneralSettings,
	updateGeneralSettings,
	resetGeneralSettings,
} from './resource';
import { useAlerts } from '@framework/hooks/useAlerts';
import { canManageSite } from '../user';

/**
 * Settings only site admins (`manage_options`) may change. The server
 * rejects changes to them from anyone else, so they're left out of what
 * other users save or reset.
 */
const SITE_ADMIN_FIELDS = ['removeDataOnUninstall'];

const withoutSiteAdminFields = (data) => {
	if (canManageSite()) {
		return data;
	}
	const allowed = { ...data };
	SITE_ADMIN_FIELDS.forEach((field) => delete allowed[field]);
	return allowed;
};

const DataManagement = () => {
	const [isLoading, setIsLoading] = useState(false);
	const [isResetting, setIsResetting] = useState(false);
	// Turning the switch on asks first; the switch stays off until confirmed.
	const [confirmRemove, setConfirmRemove] = useState(false);
	const { handleSubmit, control, formState, reset, setValue } = useForm();
	const { addAlert } = useAlerts();

	useEffect(() => {
		const fetchSettings = async () => {
			setIsLoading(true);
			try {
				const response = await getGeneralSettings();
				const generalSettings = response?.data ?? response;
				reset(generalSettings);
			} catch (error) {
				console.error('Error fetching settings:', error);
			} finally {
				setIsLoading(false);
			}
		};
		fetchSettings();
	}, []);

	const onSubmit = async (data) => {
		await updateGeneralSettings(withoutSiteAdminFields(data))
			.then(() => {
				addAlert({
					id: Date.now(),
					type: 'success',
					title: __('Success', 'hourgate'),
					description: __(
						'Data management settings saved successfully.',
						'hourgate'
					),
				});
			})
			.catch((error) => {
				console.error('Error updating settings:', error);
				addAlert({
					id: Date.now(),
					type: 'error',
					title: __('Error', 'hourgate'),
					description: __(
						'Unable to save data management settings.',
						'hourgate'
					),
				});
			});
	};

	const onReset = async () => {
		setIsResetting(true);
		await resetGeneralSettings()
		.then(async (response) => {
			const defaultSettings = response?.data ?? response;
			const saved = await updateGeneralSettings(
				withoutSiteAdminFields(defaultSettings)
			);
			reset(saved?.data ?? defaultSettings);
			addAlert({
				id: Date.now(),
				type: 'success',
				title: __('Success', 'hourgate'),
				description: __(
					'Data management settings reset successfully.',
					'hourgate'
				),
			});
		})
		.catch((error) => {
			console.error('Error resetting settings:', error);
			addAlert({
				id: Date.now(),
				type: 'error',
				title: __('Error', 'hourgate'),
				description: __(
					'Unable to reset data management settings.',
					'hourgate'
				),
			});
		})
		.finally(() => {
			setIsResetting(false);
		});
	};

	return (
		<Flex
			vertical
			gap={10}
			style={{ flexGrow: 1, minWidth: 0, maxWidth: '800px' }}
		>
			<Content>
				{isLoading && <ScreenLoader />}
				{!isLoading && (
					<Flex vertical gap={10}>
						<Flex vertical gap={5}>
							<Title level={2} size={3}>
								{__('Data Management', 'hourgate')}
							</Title>
							<span className="hourgate-setting-info">
								{__(
									'Manage what happens to HourGate’s settings and data when the plugin is deleted.',
									'hourgate'
								)}
							</span>
						</Flex>
						<Divider />
						<form onSubmit={handleSubmit(onSubmit)}>
							<Flex vertical gap={20}>
								<Switch
									name="removeDataOnUninstall"
									label={__(
										'Remove Data on Uninstall',
										'hourgate'
									)}
									control={control}
									onBeforeChange={(checked) => {
										if (!checked) {
											return true;
										}
										setConfirmRemove(true);
										return false;
									}}
									disabled={!canManageSite()}
									extaInfo={
										canManageSite()
											? __(
													'Delete all HourGate settings and data when the plugin is deleted.',
													'hourgate'
											  )
											: __(
													'Delete all HourGate settings and data when the plugin is deleted. Only administrators can change this.',
													'hourgate'
											  )
									}
								/>
								<Divider />
								<Flex gap={5}>
									<Button
										type="primary"
										htmlType="submit"
										loading={formState.isSubmitting}
										disabled={isResetting}
									>
										{__('Submit', 'hourgate')}
									</Button>
									<Button
										loading={isResetting}
										disabled={
											formState.isSubmitting ||
											isResetting
										}
										onClick={onReset}
									>
										{__('Reset', 'hourgate')}
									</Button>
								</Flex>
							</Flex>
						</form>
					</Flex>
				)}
				<ConfirmDialog
					isOpen={confirmRemove}
					title={__('Remove all data on uninstall?', 'hourgate')}
					message={__(
						'When you delete HourGate from the Plugins screen, its settings are permanently deleted: opening hours, closures, special hours and the ordering settings. This can’t be undone. Orders keep their scheduled time. You can turn this off again before deleting the plugin. Click Submit afterwards to save it.',
						'hourgate'
					)}
					confirmLabel={__('Turn on', 'hourgate')}
					destructive
					onCancel={() => setConfirmRemove(false)}
					onConfirm={() => {
						setValue('removeDataOnUninstall', true, {
							shouldDirty: true,
						});
						setConfirmRemove(false);
					}}
				/>
			</Content>
		</Flex>
	);
};

export default DataManagement;
