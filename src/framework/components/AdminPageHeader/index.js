import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Flex, Button, Title, Dropdown } from '@framework/components';
import { useScreenSize } from '@framework/hooks/useScreenSize';
import { BsBook, BsChatDots, BsTicketPerforated } from 'react-icons/bs';
import './index.css';

// Empty until the docs site is live (OW-058): the Documentation button doesn't render.
// Planned address: https://docs.prefixwp.com/docs/hourgate/
const DOC_URL = '';
const FORUM_URL = 'https://wordpress.org/support/plugin/hourgate/';
const TICKET_URL = 'https://prefixwp.com/support';

const AdminPageHeader = ({
	icon,
	title,
	docLink = DOC_URL,
	helpLink = FORUM_URL,
	ticketLink = TICKET_URL,
	showHelpButtons = false,
}) => {
	const { screenSize } = useScreenSize();
	const [isSupportOpen, setIsSupportOpen] = useState(false);

	const supportItems = [
		helpLink && {
			key: 'forum',
			label: __('Support Forum', 'hourgate'),
			icon: <BsChatDots />,
			url: helpLink,
			parentType: 'top',
		},
		ticketLink && {
			key: 'ticket',
			label: __('Create a Ticket', 'hourgate'),
			icon: <BsTicketPerforated />,
			url: ticketLink,
			parentType: 'top',
		},
	].filter(Boolean);

	const handleSupportItemClick = (key) => {
		const item = supportItems.find(
			(supportItem) => supportItem.key === key
		);
		if (item) {
			window.open(item.url, '_blank', 'noopener,noreferrer');
		}
		setIsSupportOpen(false);
	};

	return (
		<Flex
			className="hourgate-admin-page-header"
			justify="space-between"
			align="center"
		>
			<Flex align="center" gap={10}>
				{icon}
				<Title level={1}>{title}</Title>
			</Flex>
			{showHelpButtons &&
				screenSize != 'xs' &&
				(docLink || supportItems.length > 0) && (
					<Flex gap={5}>
						{docLink && (
							<Button
								size="small"
								icon={<BsBook />}
								onClick={() => {
									window.open(docLink, '_blank');
								}}
							>
								{__('Documentation', 'hourgate')}
							</Button>
						)}
						{supportItems.length > 0 && (
							<Dropdown
								label={__('Support', 'hourgate')}
								size="small"
								items={supportItems}
								isOpenMenu={isSupportOpen}
								setIsOpenMenu={setIsSupportOpen}
								onMenuItemClick={handleSupportItemClick}
								menuPosition="right"
							/>
						)}
					</Flex>
				)}
		</Flex>
	);
};

export default AdminPageHeader;
