import React from 'react';
import './index.css';

const Badge = ({
	type = 'default',
	children,
	className = '',
}) => {
	return (
		<span className={`hourgate-badge hourgate-badge-${type} ${className}`}>
			{children}
		</span>
	);
};

export default Badge;