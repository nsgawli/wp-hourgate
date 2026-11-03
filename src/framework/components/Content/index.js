import React from 'react';
import './index.css';

const Content = React.memo(({ children, style = {} }) => {
	return (
		<div className="hourgate-content" style={style}>
			{children}
		</div>
	);
});

export default Content;
