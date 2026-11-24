const { TestEnvironment } = require( 'jest-environment-jsdom' );

/**
 * jsdom defines `window.location` and its members as non-configurable, so tests
 * cannot replace or redefine them. Exposing the JSDOM instance lets tests change
 * the page URL through `jsdom.reconfigure()` instead.
 */
module.exports = class extends TestEnvironment {
	async setup() {
		await super.setup();
		this.global.jsdom = this.dom;
	}
};
