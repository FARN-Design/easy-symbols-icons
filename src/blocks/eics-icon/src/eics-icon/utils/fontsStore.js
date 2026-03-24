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

	store.promise = (async () => {
		let response = await fetch("/wp-json/easysymbolsicons/v1/loaded-fonts");

		if (!response.ok) {
			response = await fetch("/?rest_route=/easysymbolsicons/v1/loaded-fonts");
		}

		if (!response.ok) {
			throw new Error(`HTTP error ${response.status}`);
		}

		const text = await response.text();

		let json;
		try {
			json = JSON.parse(text);
		} catch {
			json = [];
		}

		store.cache = json;
		return json;
	})();

	return store.promise;
}