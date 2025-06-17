const inquirer = require('inquirer');
const fs = require('fs').promises;
const path = require('path');

const CHANGE_TYPES = {
    'Fix': 'Fixes an existing bug',
    'Add': 'Adds functionality',
    'Update': 'Update existing functionality',
    'Dev': 'Development related task',
    'Tweak': 'A minor adjustment to the codebase'
};

async function findUpcomingVersionSection(content) {
    const lines = content.split('\n');
    const upcomingVersionPattern = /=\s*\d+\.\d+\.\d+\s*-\s*xxxx-xx-xx\s*=/;
    
    for (let i = 0; i < lines.length; i++) {
        if (upcomingVersionPattern.test(lines[i])) {
            // Find the next version section or end of file
            for (let j = i + 1; j < lines.length; j++) {
                if (lines[j].startsWith('=')) {
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

async function main() {
    try {
        // Prepare type choices for inquirer
        const typeChoices = Object.entries(CHANGE_TYPES).map(([type, description]) => ({
            name: `${type} - ${description}`,
            value: type
        }));

        // Get user input
        const answers = await inquirer.prompt([
            {
                type: 'list',
                name: 'changeType',
                message: 'Select the type of change:',
                choices: typeChoices
            },
            {
                type: 'input',
                name: 'message',
                message: 'Enter the changelog message:',
                validate: input => input.trim().length > 0 || 'Message cannot be empty'
            }
        ]);

        // Remove trailing . from changelog message. See https://wp.me/pc4etw-1FS.
        const message = answers.message.trim().replace( /\.$/, '' );
        const entry = `* ${answers.changeType} - ${message}`;
        
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