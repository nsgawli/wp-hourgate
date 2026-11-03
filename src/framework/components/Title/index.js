import React from 'react';
import './index.css';

/**
 * A heading.
 *
 * @param {Object} props
 * @param {number} props.level    Heading level, 1–6 (the page outline).
 * @param {number} props.size     Visual size, 1–6; defaults to `level`. Lets
 *                                a heading keep its look at the level the
 *                                outline needs (OW-053: no skipped levels).
 * @param {Object} props.style    Inline styles.
 * @param {string} props.id       Optional id, e.g. for `aria-labelledby`.
 * @param {*}      props.children Content.
 */
const Title = React.memo(({ level, size, style = {}, id, children }) => {
	const TitleTag = `h${level}`;
	return (
		<TitleTag
			className={`hourgate-title is-size-${size || level}`}
			style={style}
			id={id}
		>
			{children}
		</TitleTag>
	);
});

export default Title;
