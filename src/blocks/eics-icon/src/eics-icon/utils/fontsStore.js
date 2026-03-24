import apiFetch from '@wordpress/api-fetch';

const STORE_KEY = "__EICS_FONTS_STORE__";

function getGlobalStore() {
	if (!globalThis[STORE_KEY]) {
		globalThis[STORE_KEY] = {
			cache: null,
			promise: null,
		};
	}
	return globalThis[STORE_KEY];
}

export function getFonts() {
	const store = getGlobalStore();

	if (store.cache) {
		return Promise.resolve(store.cache);
	}

	if (store.promise) {
		return store.promise;
	}

	store.promise = apiFetch({
		path: '/easysymbolsicons/v1/loaded-fonts',
	})
		.then((json) => {
			store.cache = json;
			return json;
		})
		.catch((error) => {
			store.promise = null;
			throw error;
		});

	return store.promise;
}