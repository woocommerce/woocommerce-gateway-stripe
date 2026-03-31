/**
 * In QIT, plugins declared in `requires.plugins` are always installed and activated.
 * This stub always returns true, matching the expected interface.
 */
export const isPluginInstalled = async () => {
	return true;
};
