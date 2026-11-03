import React, { useEffect, useMemo, useState } from 'react';
import { Flex, Title } from '@framework/components';
import { useAlerts } from '@framework/hooks/useAlerts';
import { IoMdClose } from 'react-icons/io';
import './index.css';

const ALERT_HIDE_DURATION = 220;
const ALERT_AUTO_DISMISS = 5000;

const AlertContainer = () => {
	const { alerts, removeAlert } = useAlerts();
	const [leavingIds, setLeavingIds] = useState([]);

	const leavingIdSet = useMemo(
		() => new Set(leavingIds),
		[leavingIds]
	);

	const dismissAlert = (id) => {
		if (leavingIdSet.has(id)) {
			return;
		}

		setLeavingIds((prev) => [...prev, id]);

		setTimeout(() => {
			removeAlert(id);
			setLeavingIds((prev) => prev.filter((item) => item !== id));
		}, ALERT_HIDE_DURATION);
	};

	useEffect(() => {
		const nextAlert = alerts.find((alert) => !leavingIdSet.has(alert.id));

		if (!nextAlert) {
			return undefined;
		}

		const timeout = setTimeout(() => {
			dismissAlert(nextAlert.id);
		}, ALERT_AUTO_DISMISS);

		return () => clearTimeout(timeout);
	}, [alerts, leavingIdSet]);

	return (
		<Flex className="hourgate-alert-container" gap={10} vertical>
			{alerts.map((alert) => (
				<Flex
					key={alert.id}
					className={`hourgate-alert hourgate-alert-${alert.type}${
						leavingIdSet.has(alert.id)
							? ' hourgate-alert-leaving'
							: ''
					}`}
					gap={5}
					vertical
				>
					{alert.title && <Title level={4}>{alert.title}</Title>}
					{alert.description && <span>{alert.description}</span>}
					<IoMdClose onClick={() => dismissAlert(alert.id)} />
				</Flex>
			))}
		</Flex>
	);
};

export default AlertContainer;
