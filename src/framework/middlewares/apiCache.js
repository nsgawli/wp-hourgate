import apiFetch from '@wordpress/api-fetch';

/**
 * In-memory cache for `apiFetch` GET requests that pass `useApiCache: true`.
 *
 * `wp.apiFetch` is one shared global, but every bundle that imports this
 * module (each Free screen, Pro) gets its own copy of it. The cache therefore
 * lives on `window`, so `apiCache.clear()` from any bundle clears the cache the
 * registered middleware reads, and `apiCache.register()` adds the middleware
 * once per page however many bundles call it.
 */
const STORE_KEY = '__hourGateApiCache';

const getStore = () => {
	if (!window[STORE_KEY]) {
		window[STORE_KEY] = {
			data: new Map(), // Stores the data of resolved API calls
			promises: new Map(), // Stores the promises of ongoing API calls
			registered: false,
		};
	}
	return window[STORE_KEY];
};

// Generate a unique key for each request based on the path and parameters
const generateCacheKey = (options) =>
	`${options.path}-${JSON.stringify(options.data || {})}`;

// Middleware function
const apiCache = (options, next) => {
	// Only GET requests that opt in are cached; writes always reach the server.
	const method = (options.method || 'GET').toUpperCase();
	if (!options.useApiCache || method !== 'GET') {
		return next(options);
	}

	const cache = getStore();

	// Generate a unique key for the request
	const key = generateCacheKey(options);

	// Check if the data is already cached and return it
	if (cache.data.has(key)) {
		return Promise.resolve(cache.data.get(key));
	}

	// If a request is in progress, return the existing promise
	if (cache.promises.has(key)) {
		return cache.promises.get(key);
	}

	// Proceed with the API call and cache the promise
	// Cache the result only if this request is still the current one: a
	// `clear()` while it was in flight (a save) means its answer may predate
	// the save, so it must not be stored (OW-021).
	const promise = next(options)
		.then((result) => {
			if (cache.promises.get(key) === promise) {
				cache.data.set(key, result);
				cache.promises.delete(key);
			}
			return result;
		})
		.catch((error) => {
			if (cache.promises.get(key) === promise) {
				cache.promises.delete(key);
			}
			throw error;
		});

	cache.promises.set(key, promise);

	return promise;
};

// Add a method to clear cache entries by pattern
apiCache.clear = (pattern) => {
	const cache = getStore();
	for (const key of cache.data.keys()) {
		if (key.includes(pattern)) {
			cache.data.delete(key);
		}
	}
	for (const key of cache.promises.keys()) {
		if (key.includes(pattern)) {
			cache.promises.delete(key);
		}
	}
};

// Register the middleware with apiFetch, once per page.
apiCache.register = () => {
	const cache = getStore();
	if (cache.registered) {
		return;
	}
	apiFetch.use(apiCache);
	cache.registered = true;
};

export default apiCache;
