const inquirer = require('inquirer');
const fs = require('fs').promises;
const path = require('path');
const { execFile, spawn } = require('child_process');
const { promisify } = require('util');
const readline = require('readline');

const execFileP = promisify(execFile);

const CHANGE_TYPES = {
    'Fix': 'Fixes an existing bug',
    'Add': 'Adds functionality',
    'Update': 'Update existing functionality',
    'Remove': 'Removes existing functionality',
    'Dev': 'Development related task',
    'Tweak': 'A minor adjustment to the codebase'
};

const CLAUDE_SUGGEST = '__claude_suggest__';
const BASE_BRANCH = 'develop';

async function findUpcomingVersionSection(content) {
    const lines = content.split('\n');
    // Match both formats:
    // - changelog.txt: xxxx-xx-xx - version X.Y.Z
    // - readme.txt:    = X.Y.Z - xxxx-xx-xx =
    const upcomingVersionPattern = /(?:xxxx-xx-xx\s*-\s*version\s+\d+\.\d+\.\d+|=\s*\d+\.\d+\.\d+\s*-\s*xxxx-xx-xx\s*=)/;

    for (let i = 0; i < lines.length; i++) {
        if (upcomingVersionPattern.test(lines[i])) {
            // Find the next version section or end of file.
            // Match both formats:
            // - changelog.txt: YYYY-MM-DD - version X.Y.Z
            // - readme.txt:    = X.Y.Z - YYYY-MM-DD =
            const nextVersionPattern = /^(?:=\s*\d+\.\d+|\d{4}-\d{2}-\d{2}\s*-\s*version\s+\d+\.\d+)/;
            for (let j = i + 1; j < lines.length; j++) {
                if (nextVersionPattern.test(lines[j])) {
                    return { startLine: i, endLine: j - 1 };
                }
            }
            return { startLine: i, endLine: lines.length - 1 };
        }
    }
    throw new Error('Could not find upcoming version section (xxxx-xx-xx)');
}

async function insertChangelogEntry(filePath, entry) {
    try {
        const content = await fs.readFile(filePath, 'utf8');
        const lines = content.split('\n');
        const { startLine, endLine } = await findUpcomingVersionSection(content);

        // Find the last entry in the current version section
        let insertPosition = startLine + 1;
        for (let i = startLine + 1; i <= endLine; i++) {
            if (lines[i].trim().startsWith('*')) {
                insertPosition = i + 1;
            }
        }

        // Insert the new entry after the last existing entry
        lines.splice(insertPosition, 0, entry);

        await fs.writeFile(filePath, lines.join('\n'));
    } catch (error) {
        throw new Error(`Failed to update ${filePath}: ${error.message}`);
    }
}

async function getBranchContext() {
    const [log, diffstat] = await Promise.all([
        execFileP('git', ['log', `${BASE_BRANCH}..HEAD`, '--format=%s%n%b']),
        execFileP('git', ['diff', `${BASE_BRANCH}...HEAD`, '--stat']),
    ]);
    return {
        log: log.stdout.trim(),
        diffstat: diffstat.stdout.trim(),
    };
}

function runClaude(prompt) {
    return new Promise((resolve, reject) => {
        const proc = spawn('claude', ['-p', '--model', 'haiku']);
        let stdout = '';
        let stderr = '';
        proc.stdout.on('data', (d) => { stdout += d; });
        proc.stderr.on('data', (d) => { stderr += d; });
        proc.on('error', (err) => {
            if (err.code === 'ENOENT') {
                reject(new Error('claude CLI not found in PATH'));
            } else {
                reject(err);
            }
        });
        proc.on('close', (code) => {
            if (code !== 0) {
                reject(new Error(stderr.trim() || `claude exited with code ${code}`));
            } else {
                resolve(stdout);
            }
        });
        proc.stdin.end(prompt);
    });
}

function extractJson(text) {
    const fenced = text.match(/```(?:json)?\s*([\s\S]*?)```/);
    const body = (fenced ? fenced[1] : text).trim();
    const start = body.indexOf('{');
    const end = body.lastIndexOf('}');
    if (start === -1 || end === -1) {
        throw new Error(`no JSON object in response: ${text.slice(0, 200)}`);
    }
    return JSON.parse(body.slice(start, end + 1));
}

function promptWithPrefill(question, prefill) {
    return new Promise((resolve) => {
        const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
        rl.question(question, (answer) => {
            rl.close();
            resolve(answer);
        });
        rl.write(prefill);
    });
}

async function suggestWithClaude() {
    const { log, diffstat } = await getBranchContext();
    if (!log && !diffstat) {
        throw new Error(`no changes between ${BASE_BRANCH} and HEAD`);
    }

    const typeList = Object.entries(CHANGE_TYPES)
        .map(([t, d]) => `${t} (${d})`)
        .join('; ');

    const prompt = `Suggest a changelog entry for a WooCommerce Stripe plugin branch.

Respond with ONLY a JSON object on a single line, no prose, no code fences:
{"type": "<one of: ${Object.keys(CHANGE_TYPES).join('|')}>", "message": "<short imperative sentence>"}

Type meanings: ${typeList}.
Message rules: concise, imperative voice, no trailing period, describes user-visible impact when possible.

Branch commits (${BASE_BRANCH}..HEAD):
${log || '(no commits)'}

Diff stat (${BASE_BRANCH}...HEAD):
${diffstat || '(no diff)'}
`;

    const raw = await runClaude(prompt);
    const parsed = extractJson(raw);

    if (!CHANGE_TYPES[parsed.type]) {
        throw new Error(`invalid type "${parsed.type}" from Claude`);
    }
    if (typeof parsed.message !== 'string' || !parsed.message.trim()) {
        throw new Error('empty message from Claude');
    }

    return {
        type: parsed.type,
        message: parsed.message.trim().replace(/\.$/, ''),
    };
}

async function main() {
    try {
        const manualTypeChoices = Object.entries(CHANGE_TYPES).map(([type, description]) => ({
            name: `${type} - ${description}`,
            value: type,
        }));

        const typeChoices = [
            ...manualTypeChoices,
            { name: '✨ Let Claude suggest an entry', value: CLAUDE_SUGGEST },
        ];

        const first = await inquirer.prompt([{
            type: 'list',
            name: 'changeType',
            message: 'Select the type of change:',
            choices: typeChoices,
        }]);

        let changeType;
        let message;

        if (first.changeType === CLAUDE_SUGGEST) {
            let suggestion = null;
            try {
                console.log('Asking Claude...');
                suggestion = await suggestWithClaude();
                console.log(`Suggested: * ${suggestion.type} - ${suggestion.message}`);
            } catch (err) {
                console.warn(`Claude suggestion unavailable (${err.message}). Falling back to manual entry.`);
            }

            let action = 'edit';
            if (suggestion) {
                const { next } = await inquirer.prompt([{
                    type: 'list',
                    name: 'next',
                    message: 'What would you like to do?',
                    choices: [
                        { name: 'Use suggestion as-is', value: 'accept' },
                        { name: 'Edit before saving', value: 'edit' },
                    ],
                    default: 'accept',
                }]);
                action = next;
            }

            if (action === 'accept') {
                changeType = suggestion.type;
                message = suggestion.message;
            } else if (suggestion) {
                const typePattern = new RegExp(`^\\s*(${Object.keys(CHANGE_TYPES).join('|')})\\s*-\\s*(.+?)\\s*$`);
                while (true) {
                    const line = await promptWithPrefill(
                        '? Edit entry: ',
                        `${suggestion.type} - ${suggestion.message}`
                    );
                    const match = line.match(typePattern);
                    if (match) {
                        changeType = match[1];
                        message = match[2];
                        break;
                    }
                    console.warn(`Invalid format. Expected "Type - message" where Type is one of: ${Object.keys(CHANGE_TYPES).join(', ')}`);
                }
            } else {
                const answers = await inquirer.prompt([
                    {
                        type: 'list',
                        name: 'changeType',
                        message: 'Select the type of change:',
                        choices: manualTypeChoices,
                    },
                    {
                        type: 'input',
                        name: 'message',
                        message: 'Enter the changelog message:',
                        validate: (input) => input.trim().length > 0 || 'Message cannot be empty',
                    },
                ]);
                changeType = answers.changeType;
                message = answers.message;
            }
        } else {
            changeType = first.changeType;
            const answers = await inquirer.prompt([{
                type: 'input',
                name: 'message',
                message: 'Enter the changelog message:',
                validate: (input) => input.trim().length > 0 || 'Message cannot be empty',
            }]);
            message = answers.message;
        }

        // Remove trailing . from changelog message. See https://wp.me/pc4etw-1FS.
        message = message.trim().replace(/\.$/, '');
        const entry = `* ${changeType} - ${message}`;

        // Update both files
        const files = ['changelog.txt', 'readme.txt'];
        for (const file of files) {
            await insertChangelogEntry(file, entry);
        }

        console.log('✅ Changelog entries added successfully to changelog.txt and readme.txt');
    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    }
}

main();
