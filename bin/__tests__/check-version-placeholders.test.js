const { check, checkTree, isChecked, isReplaced, addedLines } = require('../check-version-placeholders');

const REPLACE_PATHS = ['includes', 'templates', 'woocommerce-gateway-stripe.php', 'uninstall.php'];
const CONFIG = { replacePaths: REPLACE_PATHS, replaceStrings: true };
const LAST_RELEASED = '11.0.0';

// A `git diff --unified=0` for lines added to one file, starting at `start`.
const diffFor = (file, lines, start = 10) =>
    [
        `diff --git a/${file} b/${file}`,
        `--- a/${file}`,
        `+++ b/${file}`,
        `@@ -0,0 +${start},${lines.length} @@`,
        ...lines.map((line) => `+${line}`),
    ].join('\n');

const levels = (diff, config = CONFIG) => check(diff, config, LAST_RELEASED).map(({ level }) => level);

describe('check-version-placeholders', () => {
    it.each([
        ['@since in includes/', 'includes/class-foo.php', '	 * @since x.x.x'],
        ['@version in a listed file', 'uninstall.php', ' * @version  x.x.x'],
        ['@since in templates/', 'templates/emails/foo.php', ' * @since x.x.x'],
        ['@deprecated x.x.x', 'includes/class-foo.php', '	 * @deprecated x.x.x Use bar() instead.'],
        ['a deprecation function argument', 'includes/class-foo.php', "		_deprecated_function( __METHOD__, 'x.x.x', 'bar' );"],
        ['a double-quoted argument', 'includes/class-foo.php', '		wc_deprecated_function( __METHOD__, "x.x.x" );'],
        ['@since and a quoted placeholder on one line', 'includes/class-foo.php', "	 * @since x.x.x Replaces wc_foo(), deprecated in 'x.x.x'."],
        ['moved code keeping a released version', 'includes/class-foo.php', '	 * @since 10.9.0'],
        ['a released deprecation version', 'includes/class-foo.php', "		_deprecated_function( __METHOD__, '10.9.0' );"],
    ])('accepts %s', (description, file, line) => {
        expect(levels(diffFor(file, [line]))).toEqual([]);
    });

    it.each([
        ['a tab between the tag and x.x.x', 'includes/class-foo.php', '	 * @since	x.x.x'],
        ['an unquoted x.x.x', 'includes/class-foo.php', '	 * Deprecated in x.x.x.'],
        ["a longer string like 'x.x.x.x'", 'includes/class-foo.php', "		$version = 'x.x.x.x';"],
        ['JS, which woorelease does not scan', 'client/foo.js', ' * @since x.x.x'],
        ['a PHP file outside the replace paths', 'lib/foo.php', ' * @since x.x.x'],
    ])('errors on %s', (description, file, line) => {
        expect(levels(diffFor(file, [line]))).toEqual(['error']);
    });

    it('errors on quoted placeholders when version_replace_strings is off', () => {
        const diff = diffFor('includes/class-foo.php', ["		_deprecated_function( __METHOD__, 'x.x.x' );", '	 * @deprecated x.x.x']);

        expect(levels(diff, { replacePaths: REPLACE_PATHS, replaceStrings: false })).toEqual(['error']);
    });

    it.each([
        ['@since', '	 * @since 11.1.0'],
        ['@deprecated', '	 * @deprecated 11.1.0 Use bar() instead.'],
        ['a deprecation function argument', "		_deprecated_function( __METHOD__, '11.1.0', 'bar' );"],
    ])('warns on an unreleased version in %s and suggests x.x.x', (description, line) => {
        const findings = check(diffFor('includes/class-foo.php', [line], 42), CONFIG, LAST_RELEASED);

        expect(findings).toEqual([expect.objectContaining({ level: 'warning', file: 'includes/class-foo.php', line: 42 })]);
        expect(findings[0].message).toContain('x.x.x');
    });

    it('finds the version argument of a multi-line deprecation call', () => {
        const diff = diffFor(
            'includes/class-foo.php',
            ['		$value = apply_filters_deprecated(', "			'wc_foo',", '			[ $value ],', "			'11.1.0',", "			'wc_bar'", '		);', "		$other = '11.2.0';"],
            100
        );

        expect(check(diff, CONFIG, LAST_RELEASED)).toEqual([expect.objectContaining({ level: 'warning', line: 103 })]);
    });

    it('does not treat other version strings as deprecation versions', () => {
        const line = "		if ( version_compare( WC_VERSION, '11.2.0', '>=' ) ) {";

        expect(levels(diffFor('includes/class-foo.php', [line]))).toEqual([]);
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
            '+ * Deprecated in x.x.x.',
            '@@ -20,2 +21,1 @@',
            '- * old line',
            '- * other old line',
            '+ * Deprecated in x.x.x.',
        ].join('\n');

        expect(check(diff, CONFIG, LAST_RELEASED).map(({ line }) => line)).toEqual([4, 21]);
    });

    it('skips deleted files', () => {
        expect(addedLines(['--- a/includes/gone.php', '+++ /dev/null', '@@ -1,1 +0,0 @@', '- * @since x.x.x'].join('\n'))).toEqual([]);
    });

    describe('checkTree', () => {
        it('reports every placeholder left in shipped code', () => {
            const lines = [
                { file: 'includes/class-foo.php', line: 7, text: '	 * @since x.x.x' },
                { file: 'uninstall.php', line: 5, text: ' * @version  x.x.x' },
                { file: 'tests/phpunit/test-foo.php', line: 3, text: ' * @since x.x.x' },
                { file: 'bin/check-version-placeholders.js', line: 1, text: "const PLACEHOLDER = 'x.x.x';" },
            ];

            expect(checkTree(lines, '11.1.0').map(({ file, line }) => `${file}:${line}`)).toEqual([
                'includes/class-foo.php:7',
                'uninstall.php:5',
            ]);
        });
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
    ])('isChecked(%s) is %s', (file, expected) => {
        expect(isChecked(file)).toBe(expected);
    });
});
