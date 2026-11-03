import React from 'react';
import ReactDOM from 'react-dom';
import { ScreenSizeProvider } from '@framework/hooks/useScreenSize';
import { AlertsProvider } from '@framework/hooks/useAlerts';
import App from './App';
import { UnsavedChangesProvider } from './unsaved-changes';
import { HashRouter as Router } from 'react-router-dom';
import { apiCache } from '@framework/middlewares';

// Serve `useApiCache: true` GET requests from memory for the rest of the page view.
apiCache.register();

document.addEventListener('DOMContentLoaded', () => {
	const element = document.getElementById('hourgate-admin-settings');
	ReactDOM.createRoot(element).render(
		<Router>
			<ScreenSizeProvider element={element}>
				<AlertsProvider>
					{/* Inside the router, so its Back/Forward guard runs before react-router's. */}
					<UnsavedChangesProvider guardedPath="/store-hours">
						<App />
					</UnsavedChangesProvider>
				</AlertsProvider>
			</ScreenSizeProvider>
		</Router>
	);
});
