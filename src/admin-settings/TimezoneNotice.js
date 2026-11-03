import React from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { getSiteSettings, storeZoneLabel } from '@framework/utils/siteTime';
import { canManageSite } from './user';

/**
 * Warns when the site uses a fixed UTC offset instead of a named zone
 * (`timezone_string` is empty): opening hours then ignore daylight saving
 * changes (OW-044). Shown on the Store hours tab, under the time-zone line and above
 * its four sub-tabs, never dismissible, because it is a real defect. A named zone, including
 * `UTC`, shows nothing. Only people who can open Settings → General get the
 * link; others are told whom to ask.
 */
const TimezoneNotice = () => {
	const zone = getSiteSettings().timezone || {};
	if (zone.string) {
		return null;
	}
	return (
		<div className="hourgate-timezone-notice" role="note">
			{sprintf(
				/* translators: %s: the fixed offset, e.g. "UTC+5:30". */
				__(
					'Your site uses a fixed UTC offset (%s), so opening hours won’t follow daylight saving changes.',
					'hourgate'
				),
				storeZoneLabel()
			)}{' '}
			{canManageSite() ? (
				<a href="options-general.php#timezone_string">
					{__(
						'Choose a city in Settings → General.',
						'hourgate'
					)}
				</a>
			) : (
				__(
					'Ask an administrator to choose a city in Settings → General.',
					'hourgate'
				)
			)}
		</div>
	);
};

export default TimezoneNotice;
