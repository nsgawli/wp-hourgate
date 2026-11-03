import React, { useState } from 'react';
import './index.css';

const Tooltip = ({ children, content }) => {
	const [visible, setVisible] = useState(false);

	const showTooltip = () => setVisible(true);
	const hideTooltip = () => setVisible(false);

	return (
		<div
			className="hourgate-tooltip-container"
			onMouseEnter={showTooltip}
			onMouseLeave={hideTooltip}
			// Keyboard users get it too (OW-053); focus events bubble in React.
			onFocus={showTooltip}
			onBlur={hideTooltip}
		>
			{children}
			{visible && <div className="hourgate-tooltip-content">{content}</div>}
		</div>
	);
};

export default Tooltip;
