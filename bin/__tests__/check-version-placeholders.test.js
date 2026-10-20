const { check, isChecked, isReplaced, extractAddedLines } = require('../check-version-placeholders');

const REPLACE_PATHS = ['includes', 'templates', 'woocommerce-gateway-stripe.php', 'uninstall.php'];

// A `git diff --unified=0` for lines added to one file, starting at `start`.
const diffFor = (file, lines, start = 10) =>
    [
        `diff --git a/${file} b/${file}`,
        `--- a/${file}`,
        `+++ b/${file}`,
        `@@ -0,0 +${start},${lines.length} @@`,
        ...lines.map((line) => `+${line}`),
    ].join('\n');

const levels = (diff) => check(diff, REPLACE_PATHS).map(({ level }) => level);

describe('check-version-placeholders', () => {
    it.each([
        ['@since in includes/', 'includes/class-foo.php', '	 * @since x.x.x'],
        ['@version in a listed file', 'uninstall.php', ' * @version  x.x.x'],
        ['@since in templates/', 'templates/emails/foo.php', ' * @since x.x.x'],
        ['a concrete @deprecated version', 'includes/class-foo.php', '	 * @deprecated 11.1.0'],
        ['a concrete @since in JS, which woorelease does not replace', 'client/foo.js', ' * @since 11.1.0'],
    ])('accepts %s', (description, file, line) => {
        expect(levels(diffFor(file, [line]))).toEqual([]);
    });

    it.each([
        ['@deprecated x.x.x', 'includes/class-foo.php', '	 * @deprecated x.x.x'],
        ['a deprecation function argument', 'includes/class-foo.php', "		_deprecated_function( __METHOD__, 'x.x.x' );"],
        ['a tab between the tag and x.x.x', 'includes/class-foo.php', '	 * @since	x.x.x'],
        ['JS, which woorelease does not scan', 'client/foo.js', ' * @since x.x.x'],
        ['a PHP file outside the replace paths', 'bootstrap.php', ' * @since x.x.x'],
        ['a concrete @since in a replaced file', 'includes/class-foo.php', '	 * @since 10.9.0'],
    ])('errors on %s', (description, file, line) => {
        expect(levels(diffFor(file, [line]))).toEqual(['error']);
    });

    it('errors when one of two placeholders on a line would not be replaced', () => {
        const line = "	 * @since x.x.x Replaces wc_foo(), deprecated in x.x.x.";

        expect(levels(diffFor('includes/class-foo.php', [line]))).toEqual(['error']);
    });

    it('errors on a concrete version in a replaced file and suggests x.x.x', () => {
        const findings = check(diffFor('includes/class-foo.php', ['	 * @since 11.1.0'], 42), REPLACE_PATHS);

        expect(findings).toEqual([
            expect.objectContaining({ level: 'error', file: 'includes/class-foo.php', line: 42 }),
        ]);
        expect(findings[0].message).toContain('@since x.x.x');
    });

    it('ignores files that do not ship', () => {
        const diff = [
            diffFor('tests/phpunit/test-foo.php', [' * @since x.x.x', ' * @since 99.0.0']),
            diffFor('bin/some-script.js', ["const placeholder = 'x.x.x';"]),
            diffFor('docs/notes.md', ['Use x.x.x']),
        ].join('\n');

        expect(levels(diff)).toEqual([]);
    });

    it('reports new line numbers across hunks', () => {
        const diff = [
            '--- a/includes/class-foo.php',
            '+++ b/includes/class-foo.php',
            '@@ -3,0 +4,1 @@',
            '+ * @deprecated x.x.x',
            '@@ -20,2 +21,1 @@',
            '- * old line',
            '- * other old line',
            '+ * @deprecated x.x.x',
        ].join('\n');

        expect(check(diff, REPLACE_PATHS).map(({ line }) => line)).toEqual([4, 21]);
    });

    describe('moved or re-indented docblocks', () => {
        // A `git diff --unified=0` hunk that removes and adds lines in one file.
        const changeFor = (file, removed, added) =>
            [
                `diff --git a/${file} b/${file}`,
                `--- a/${file}`,
                `+++ b/${file}`,
                `@@ -10,${removed.length} +10,${added.length} @@`,
                ...removed.map((line) => `-${line}`),
                ...added.map((line) => `+${line}`),
            ].join('\n');

        it('accepts a concrete version the same file also removes', () => {
            const diff = changeFor('includes/class-foo.php', ['	 * @since 10.8.0'], ['		 * @since 10.8.0']);

            expect(levels(diff)).toEqual([]);
        });

        it('errors when the version changes', () => {
            const diff = changeFor('includes/class-foo.php', ['	 * @since 10.8.0'], ['	 * @since 10.9.0']);

            expect(levels(diff)).toEqual(['error']);
        });

        it('errors on each added copy beyond the removed ones', () => {
            const diff = changeFor(
                'includes/class-foo.php',
                ['	 * @since 10.8.0'],
                ['		 * @since 10.8.0', '		 * @since 10.8.0']
            );

            expect(levels(diff)).toEqual(['error']);
        });

        it('errors when the tag was removed from another file', () => {
            const diff = [
                changeFor('includes/class-bar.php', ['	 * @since 10.8.0'], []),
                diffFor('includes/class-foo.php', ['	 * @since 10.8.0']),
            ].join('\n');

            expect(levels(diff)).toEqual(['error']);
        });
    });

    it('skips deleted files', () => {
        expect(extractAddedLines(['--- a/includes/gone.php', '+++ /dev/null', '@@ -1,1 +0,0 @@', '- * @since x.x.x'].join('\n'))).toEqual(
            []
        );
    });

    describe('isReplaced', () => {
        it.each([
            ['includes/class-foo.php', true],
            ['includes/deep/nested/CLASS-FOO.PHP', true],
            ['includes/foo.js', false],
            ['includes-other/foo.php', false],
            ['uninstall.php', true],
            ['woocommerce-gateway-stripe.php', true],
            ['other.php', false],
        ])('%s: %s', (file, expected) => {
            expect(isReplaced(file, REPLACE_PATHS)).toBe(expected);
        });

        it('accepts ./ prefixes and trailing slashes in the config', () => {
            expect(isReplaced('includes/class-foo.php', ['./includes/'])).toBe(true);
        });
    });

    it.each([
        ['includes/class-foo.php', true],
        ['client/blocks/foo.tsx', true],
        ['tests/phpunit/foo.php', false],
        ['.github/workflows/foo.yml', false],
        ['readme.txt', false],
        ['woocommerce-gateway-stripe.php', true],
        ['docker/bin/foo.php', false],
        ['release/woocommerce-gateway-stripe/includes/foo.php', false],
    ])('isChecked(%s) is %s', (file, expected) => {
        expect(isChecked(file)).toBe(expected);
    });
});
