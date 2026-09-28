const { resolve, UnresolvableError } = require('../resolve-changelog-conflicts');

const changelog = (...lines) => ['*** Changelog ***', '', ...lines, ''].join('\n');

describe('resolve-changelog-conflicts', () => {
    it('keeps both entries when both branches added to the upcoming section', () => {
        const mergeBase = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing');
        const pr = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing', '* Add - From the PR');
        const develop = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing', '* Dev - From develop');

        expect(resolve(mergeBase, pr, develop)).toBe(
            changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing', '* Dev - From develop', '* Add - From the PR')
        );
    });

    it('moves the entry to the new upcoming section when develop released the old one', () => {
        const mergeBase = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing');
        const pr = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing', '* Add - From the PR');
        const develop = changelog(
            'xxxx-xx-xx - version 11.2.0',
            '* Dev - After the release',
            '',
            '2026-10-01 - version 11.1.0',
            '* Fix - Existing'
        );

        expect(resolve(mergeBase, pr, develop)).toBe(
            changelog(
                'xxxx-xx-xx - version 11.2.0',
                '* Dev - After the release',
                '* Add - From the PR',
                '',
                '2026-10-01 - version 11.1.0',
                '* Fix - Existing'
            )
        );
    });

    it('inserts after the last readme.txt entry so the trailing link stays last', () => {
        const readme = (...lines) =>
            ['== Changelog ==', '', ...lines, '', '[See changelog for full details across versions](https://example.com).', ''].join('\n');
        const mergeBase = readme('= 11.1.0 - xxxx-xx-xx =', '* Fix - Existing');
        const pr = readme('= 11.1.0 - xxxx-xx-xx =', '* Fix - Existing', '* Add - From the PR');
        const develop = readme('= 11.1.0 - xxxx-xx-xx =', '* Fix - Existing', '* Dev - From develop');

        expect(resolve(mergeBase, pr, develop)).toBe(
            readme('= 11.1.0 - xxxx-xx-xx =', '* Fix - Existing', '* Dev - From develop', '* Add - From the PR')
        );
    });

    it('does not duplicate an entry that is already on develop', () => {
        const mergeBase = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing');
        const pr = changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing', '* Add - Cherry-picked');
        const develop = changelog('xxxx-xx-xx - version 11.1.0', '* Add - Cherry-picked', '* Fix - Existing');

        expect(resolve(mergeBase, pr, develop)).toBe(develop);
    });

    it.each([
        [
            'a readme metadata change',
            'Tested up to: 7.1\n\n= 11.1.0 - xxxx-xx-xx =\n* Fix - Existing\n',
            'Tested up to: 7.2\n\n= 11.1.0 - xxxx-xx-xx =\n* Fix - Existing\n',
        ],
        [
            'an edited entry',
            changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing'),
            changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing, reworded'),
        ],
        [
            'a changed version header',
            changelog('xxxx-xx-xx - version 11.1.0', '* Fix - Existing'),
            changelog('2026-10-01 - version 11.1.0', '* Fix - Existing'),
        ],
    ])('refuses %s in the pull request', (description, mergeBase, pr) => {
        expect(() => resolve(mergeBase, pr, mergeBase)).toThrow(UnresolvableError);
    });

    it('refuses when develop has no upcoming section', () => {
        const mergeBase = changelog('2026-10-01 - version 11.1.0', '* Fix - Existing');
        const pr = changelog('2026-10-01 - version 11.1.0', '* Fix - Existing', '* Add - From the PR');

        expect(() => resolve(mergeBase, pr, mergeBase)).toThrow(UnresolvableError);
    });
});
