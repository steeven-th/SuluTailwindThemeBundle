/*
 * Parse the bundle's admin JavaScript and report what fails.
 *
 * The admin components are compiled by the host application, never here, so a
 * syntax error in this bundle surfaces as a broken admin build in someone
 * else's project. This gives the same answer without a build: @babel/parser
 * reads the file and says where it breaks.
 *
 * Called by JsSyntaxTest, which locates the parser and skips when there is
 * none. Run by hand with:
 *
 *     node tests/js-syntax-check.js <path-to-@babel/parser> <file>...
 *
 * Prints one line per failing file and exits non-zero. Silence means every
 * file parsed.
 */
const fs = require('fs');

const [, , parserPath, ...files] = process.argv;
const parser = require(parserPath);

/*
 * The dialect the bundle actually writes: JSX, Flow annotations, the legacy
 * decorators MobX uses (@observer), class properties and object spread. A
 * plugin missing here reads as a syntax error in perfectly good code, so this
 * list is the contract with the host build rather than a convenience.
 */
const PLUGINS = ['jsx', 'flow', 'decorators-legacy', 'classProperties', 'objectRestSpread'];

let failed = false;

files.forEach((file) => {
    try {
        parser.parse(fs.readFileSync(file, 'utf8'), {sourceType: 'module', plugins: PLUGINS});
    } catch (error) {
        failed = true;
        console.log(file + ' :: ' + error.message);
    }
});

process.exitCode = failed ? 1 : 0;
