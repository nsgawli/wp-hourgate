import React from 'react';
import { Flex, Spinner } from '@framework/components';
import './index.css';

// Forwards its ref to the <button> (or the <a> with `href`), e.g. for initial focus in a dialog.
const Button = React.forwardRef(({
	type = 'default',
	size = 'medium',
	disabled = false,
	href = null,
	target = null,
	htmlType = 'button',
	icon = null,
	iconPosition = 'start',
	loading = false,
	onClick = null,
	className = '',
	style = {},
	children,
}, ref) => {
	let classNames = ['hourgate-button', size];
	if (type !== 'default') {
		classNames.push(type);
	}
	if (className !== '') {
		classNames = [...classNames, ...className.split(' ')];
	}

	const gap = size == 'small' ? 5 : 10;
	disabled = loading || disabled;

	return !href ? (
		<button
			ref={ref}
			type={htmlType}
			className={classNames.join(' ')}
			style={style}
			onClick={onClick}
			disabled={disabled}
		>
			{icon && !loading && (
				<Flex align="center" gap={gap}>
					{iconPosition === 'start' && icon}
					{children}
					{iconPosition === 'end' && icon}
				</Flex>
			)}
			{loading && (
				<Flex align="center" gap={gap}>
					{iconPosition === 'start' && <Spinner />}
					{children}
					{iconPosition === 'end' && <Spinner />}
				</Flex>
			)}
			{!icon && !loading && children}
		</button>
	) : (
		// One link styled as a button (OW-053): a <button> inside an <a> is
		// invalid and announced as two controls.
		<a
			ref={ref}
			href={href}
			target={target}
			rel={target === '_blank' ? 'noopener noreferrer' : undefined}
			className={classNames.join(' ')}
			style={style}
			onClick={onClick}
		>
			{icon && (
				<Flex align="center" gap={gap}>
					{iconPosition === 'start' && icon}
					{children}
					{iconPosition === 'end' && icon}
				</Flex>
			)}
			{!icon && children}
		</a>
	);
});

Button.displayName = 'Button';

export default Button;
