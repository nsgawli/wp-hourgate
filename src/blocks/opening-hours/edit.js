import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';

// The editor shows the saved weekly hours as PHP read them at page load.
export default function Edit({ attributes, setAttributes }) {
	const settings = window.hourGateBlocks || {};
	const days = Array.isArray(settings.hours) ? settings.hours : [];
	const closures = Array.isArray(settings.upcoming) ? settings.upcoming : [];

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Opening hours', 'hourgate')}>
					<TextControl
						label={__('Title', 'hourgate')}
						help={__(
							'Optional. Leave empty for a hidden "Opening hours" caption that screen readers read.',
							'hourgate'
						)}
						maxLength={100}
						value={attributes.title}
						onChange={(value) => setAttributes({ title: value })}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<ToggleControl
						label={__(
							'List closures and special hours',
							'hourgate'
						)}
						help={__(
							'Closures and special hours in the next 30 days.',
							'hourgate'
						)}
						checked={!!attributes.showClosures}
						onChange={(value) =>
							setAttributes({ showClosures: value })
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
			<div {...useBlockProps({ className: 'hourgate-hours' })}>
				<table>
					<caption>
						{attributes.title ||
							__('Opening hours', 'hourgate')}
					</caption>
					<tbody>
						{days.map((day) => (
							<tr key={day.day}>
								<th scope="row">{day.name}</th>
								<td>{day.label}</td>
							</tr>
						))}
					</tbody>
				</table>
				{attributes.showClosures && (
					<ul>
						{closures.map((entry, i) => (
							<li key={i}>{entry.text}</li>
						))}
					</ul>
				)}
			</div>
		</>
	);
}
