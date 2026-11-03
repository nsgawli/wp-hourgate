import React from 'react';

// A cart with a clock in it (OW-063), white on the indigo circle. The same
// 20-unit drawing as the sidebar icon in AdminMenu::get_menu_icon(), scaled
// and centred. Decorative: the "HourGate" title next to it names it.
const ProductIcon = ({ className = '', style = {} }) => {
	return (
		<svg
			className={className}
			style={style}
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 512 512"
			aria-hidden="true"
			focusable="false"
		>
			<defs>
				<linearGradient
					id="hourgate-product-icon-bg"
					x1="0"
					y1="0"
					x2="1"
					y2="1"
				>
					<stop offset="0%" stopColor="#3730A3" />
					<stop offset="100%" stopColor="#4F46E5" />
				</linearGradient>
				<filter
					id="hourgate-product-icon-shadow"
					x="-20%"
					y="-20%"
					width="140%"
					height="140%"
				>
					<feDropShadow
						dx="0"
						dy="6"
						stdDeviation="8"
						floodOpacity=".18"
					/>
				</filter>
			</defs>

			<circle
				cx="256"
				cy="256"
				r="248"
				fill="url(#hourgate-product-icon-bg)"
				filter="url(#hourgate-product-icon-shadow)"
			/>

			<g transform="translate(110 111) scale(15)" fill="#fff">
				<path
					fillRule="evenodd"
					d="M12.5.4a5.6 5.6 0 1 1-.01 0zM12.5 1.9a4.1 4.1 0 1 0 .01 0zM11.8 3.2h1.4v2.9h2.1v1.4h-3.5z"
				/>
				<path d="M.5 3.2h3.3l2.6 10.3h10.4l.7-2.6h1.5l-1.1 4.1H5.3L2.7 4.7H.5z" />
				<circle cx="7.4" cy="17.3" r="1.7" />
				<circle cx="15.6" cy="17.3" r="1.7" />
			</g>
		</svg>
	);
};

export default ProductIcon;
