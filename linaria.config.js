module.exports = {
	displayName: process.env.NODE_ENV !== 'production',
	classNameSlug: (hash, title) => `wcstripe-${title}__${hash}`,
};
