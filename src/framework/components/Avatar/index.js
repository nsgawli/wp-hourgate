import React, { useState } from 'react';
import './index.css';

const INITIAL_COLORS = [
	'#4f46e5',
	'#0d9488',
	'#d97706',
	'#dc2626',
	'#7c3aed',
	'#059669',
	'#2563eb',
	'#db2777',
];

const getInitials = (name = '') => {
	const trimmed = name.trim();

	if (!trimmed) {
		return '?';
	}

	return trimmed
		.split(/\s+/)
		.slice(0, 2)
		.map((part) => part.charAt(0).toUpperCase())
		.join('');
};

const getColorForName = (name = '') => {
	let hash = 0;

	for (let i = 0; i < name.length; i++) {
		hash = (hash * 31 + name.charCodeAt(i)) % INITIAL_COLORS.length;
	}

	return INITIAL_COLORS[Math.abs(hash) % INITIAL_COLORS.length];
};

// Renders the user's avatar image when one loads successfully, falling
// back to a deterministic initials badge otherwise (no avatar_url, or the
// image fails to load, e.g. Gravatar being unreachable on a local site).
const Avatar = ({ src = '', name = '', size = 28, className = '' }) => {
	const [imageFailed, setImageFailed] = useState(false);

	const showImage = Boolean(src) && !imageFailed;

	const style = {
		width: size,
		height: size,
		fontSize: Math.max(10, Math.round(size * 0.4)),
	};

	if (!showImage) {
		style.backgroundColor = getColorForName(name);
	}

	return (
		<span className={`hourgate-avatar ${className}`} style={style}>
			{showImage ? (
				<img
					className="hourgate-avatar-image"
					src={src}
					alt=""
					onError={() => setImageFailed(true)}
				/>
			) : (
				<span className="hourgate-avatar-initials">
					{getInitials(name)}
				</span>
			)}
		</span>
	);
};

export default Avatar;
