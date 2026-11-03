import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	ButtonGroup,
	Button,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { statusText } from '../../storefront-status/status-text';
import {
	formatSiteDate,
	formatSiteTime,
	addDays,
	storeToday,
} from '@framework/utils/siteTime';

/**
 * Sample times for the preview (the editor never asks the store), shaped like
 * the real labels (`OrderingPolicy::format_when()`): a time today, then
 * tomorrow's short weekday and a time, in the site's locale and time format.
 *
 * @return {{closesAtLabel: string, nextOpenLabel: string}} The labels.
 */
const sample = () => ({
	closesAtLabel: formatSiteTime('18:00'),
	nextOpenLabel: `${formatSiteDate(addDays(storeToday(), 1), 'D')} ${formatSiteTime('09:00')}`,
});

export default function Edit({ attributes, setAttributes }) {
	const [previewing, setPreviewing] = useState('open');
	const settings = window.hourGateBlocks || {};
	const SAMPLE = sample();
	const defaultOpen =
		settings.defaultOpenText ||
		__('Open now · closes {closes_at}', 'hourgate');
	const closedMessage = (settings.closedMessage || '')
		.split('{next_open}')
		.join(SAMPLE.nextOpenLabel)
		.split('{closures_label}')
		.join('');

	const text = statusText(
		{
			state: previewing,
			message: closedMessage,
			...SAMPLE,
		},
		{
			open: attributes.openText || defaultOpen,
			closed: attributes.closedText,
			openFallback: __('Open now', 'hourgate'),
			later: __('later', 'hourgate'),
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Store status', 'hourgate')}>
					<TextControl
						label={__('Text while open', 'hourgate')}
						help={__(
							'Leave empty for the default. {closes_at} is when the store next closes; with no closing time the text is just "Open now".',
							'hourgate'
						)}
						placeholder={defaultOpen}
						maxLength={200}
						value={attributes.openText}
						onChange={(value) => setAttributes({ openText: value })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={__('Text while closed', 'hourgate')}
						help={__(
							'Leave empty to show the store’s closed message (Ordering tab). {next_open} is the next opening.',
							'hourgate'
						)}
						maxLength={200}
						value={attributes.closedText}
						onChange={(value) =>
							setAttributes({ closedText: value })
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps()}>
				<ButtonGroup
					aria-label={__('Preview state', 'hourgate')}
					style={{ marginBottom: 8 }}
				>
					<Button
						size="small"
						variant={
							previewing === 'open' ? 'primary' : 'secondary'
						}
						aria-pressed={previewing === 'open'}
						onClick={() => setPreviewing('open')}
					>
						{__('Preview: open', 'hourgate')}
					</Button>
					<Button
						size="small"
						variant={
							previewing === 'closed' ? 'primary' : 'secondary'
						}
						aria-pressed={previewing === 'closed'}
						onClick={() => setPreviewing('closed')}
					>
						{__('Preview: closed', 'hourgate')}
					</Button>
				</ButtonGroup>
				<div className={`hourgate-store-status is-${previewing}`}>
					{text}
				</div>
			</div>
		</>
	);
}
