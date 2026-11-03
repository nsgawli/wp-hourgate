import React from 'react';
import { Flex } from '@framework/components';
import './index.css';

const EmptyState = ({
	title = 'No Data Found',
	description = '',
	icon = null,
	action = null,
}) => {
	return (
		<Flex
			className="hourgate-empty-state"
			vertical
			align="center"
			gap={15}
		>
			{icon && (
				<div className="hourgate-empty-state-icon">
					{icon}
				</div>
			)}

			<div className="hourgate-empty-state-title">
				{title}
			</div>

			{description && (
				<div className="hourgate-empty-state-description">
					{description}
				</div>
			)}

			{action}
		</Flex>
	);
};

export default EmptyState;