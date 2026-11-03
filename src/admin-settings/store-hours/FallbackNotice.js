import React, { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { getPreview } from './resource';

/**
 * Warns when the saved schedule is invalid, so HourGate treats the store
 * as always open (`fallback` from the preview route; OW-016). Shown on every
 * editor tab, because either group (weekly hours or closures) can be the
 * broken one. Re-checks whenever `refreshKey` changes (after a save).
 *
 * @param {Object} props
 * @param {*}      props.refreshKey Change it to check again.
 */
const FallbackNotice = ({ refreshKey }) => {
	const [fallback, setFallback] = useState(false);

	useEffect(() => {
		let active = true;
		getPreview(1)
			.then(
				(preview) => active && setFallback(Boolean(preview?.fallback))
			)
			.catch(() => {});
		return () => {
			active = false;
		};
	}, [refreshKey]);

	if (!fallback) {
		return null;
	}
	return (
		<div className="hourgate-store-hours-notice is-warning hourgate-fallback-notice">
			{__(
				'The saved store hours or closures are invalid, so HourGate treats the store as always open until they are fixed and saved.',
				'hourgate'
			)}
		</div>
	);
};

export default FallbackNotice;
