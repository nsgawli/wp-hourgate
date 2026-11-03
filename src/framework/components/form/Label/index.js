import React from 'react';
import Flex from '@framework/components/Flex';
import Tooltip from '@framework/components/Tooltip';
import { BsQuestionCircle } from 'react-icons/bs';
import './../index.css'; // form styles
import './index.css'; // component styles

/**
 * A field's label (OW-053).
 *
 * - `htmlFor`: the field's id. Renders a `<label for>`, so clicking it
 *   focuses the field and the field's accessible name is `text`.
 * - `id` without `htmlFor`: a group's label (radios), referenced by the
 *   group's `aria-labelledby`; rendered as a `<span>`.
 * - `hidden` (with `htmlFor`): the label is for screen readers only.
 * - The `info` tooltip and the required mark sit outside the `<label>`, so
 *   neither becomes part of the name. The mark is hidden from screen readers;
 *   fields set `aria-required` instead.
 */
const Label = ({
	text,
	required,
	info = '',
	htmlFor = '',
	id = '',
	hidden = false,
}) => {
	// Screen-reader-only: the field keeps its name where a visible label would break the layout.
	if (hidden && htmlFor) {
		return (
			<label htmlFor={htmlFor} id={id || undefined} className="screen-reader-text">
				{text}
			</label>
		);
	}

	return (
		<Flex className="hourgate-form-field-label" gap={3}>
			{htmlFor ? (
				<label htmlFor={htmlFor} id={id || undefined}>
					{text}
				</label>
			) : (
				<span id={id || undefined}>{text}</span>
			)}
			{info && (
				<Tooltip content={info}>
					<span
						className="hourgate-form-field-info"
						tabIndex={0}
						role="img"
						aria-label={info}
					>
						<BsQuestionCircle aria-hidden="true" />
					</span>
				</Tooltip>
			)}
			{required && (
				<span className="hourgate-form-field-required" aria-hidden="true">
					*
				</span>
			)}
		</Flex>
	);
};

export default Label;
