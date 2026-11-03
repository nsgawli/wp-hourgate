import React, { useRef } from 'react';
import './index.css';

/**
 * Horizontal tab strip for in-page sub-navigation, following the WAI-ARIA
 * tabs pattern (https://www.w3.org/WAI/ARIA/apg/patterns/tabs/):
 * - one tab stop: only the active tab is in the Tab order (roving tabindex);
 * - Left/Right arrows (mirrored in RTL), Home and End move focus and activate;
 * - each tab names its panel through `aria-controls`; give the panel
 *   `tabPanelProps( idPrefix, activeKey )`.
 *
 * Shared by Free and Pro (Pro builds against `@framework`).
 *
 * @param {Object}   props
 * @param {Array}    props.items     `{ key, label }` in display order.
 * @param {string}   props.active    The active item's `key`.
 * @param {Function} props.onChange  Called with the chosen item's `key`.
 * @param {string}   props.idPrefix  Unique prefix for tab and panel ids.
 * @param {string}   props.ariaLabel Accessible name for the tab list.
 */
const Tabs = ({ items = [], active, onChange, idPrefix, ariaLabel }) => {
	const listRef = useRef(null);

	const focusTab = (index) => {
		const item = items[(index + items.length) % items.length];
		const button = listRef.current?.querySelector(
			`#${tabId(idPrefix, item.key)}`
		);
		button?.focus();
		if (item.key !== active) {
			onChange(item.key);
		}
	};

	const onKeyDown = (event, index) => {
		const rtl =
			listRef.current &&
			window.getComputedStyle(listRef.current).direction === 'rtl';
		const next = rtl ? 'ArrowLeft' : 'ArrowRight';
		const previous = rtl ? 'ArrowRight' : 'ArrowLeft';
		const moves = {
			[next]: index + 1,
			[previous]: index - 1,
			Home: 0,
			End: items.length - 1,
		};
		if (event.key in moves) {
			event.preventDefault();
			focusTab(moves[event.key]);
		}
	};

	return (
		<div
			className="hourgate-tabs"
			role="tablist"
			aria-label={ariaLabel}
			ref={listRef}
		>
			{items.map((item, index) => {
				const selected = item.key === active;
				return (
					<button
						key={item.key}
						id={tabId(idPrefix, item.key)}
						type="button"
						role="tab"
						aria-selected={selected}
						aria-controls={panelId(idPrefix, item.key)}
						tabIndex={selected ? 0 : -1}
						className={`hourgate-tabs__item${selected ? ' is-active' : ''}`}
						onClick={() => !selected && onChange(item.key)}
						onKeyDown={(event) => onKeyDown(event, index)}
					>
						{item.label}
					</button>
				);
			})}
		</div>
	);
};

const tabId = (prefix, key) => `${prefix}-tab-${key}`;
const panelId = (prefix, key) => `${prefix}-panel-${key}`;

/**
 * Props for the active tab's panel element.
 *
 * @param {string} idPrefix The `idPrefix` given to `Tabs`.
 * @param {string} key      The active tab's key.
 * @return {Object} `id`, `role`, `aria-labelledby` and `tabIndex` for the panel.
 */
export const tabPanelProps = (idPrefix, key) => ({
	id: panelId(idPrefix, key),
	role: 'tabpanel',
	'aria-labelledby': tabId(idPrefix, key),
	tabIndex: 0,
});

export default Tabs;
