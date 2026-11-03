/**
 * What the current user may do, from the inline `window.hourGateSettings`
 * object (`Admin\SettingsPage::enqueue_scripts()`). Only shapes the UI; the
 * REST routes enforce every check themselves.
 */
export const canManageSite = () =>
	Boolean(window.hourGateSettings?.user?.canManageSite);
