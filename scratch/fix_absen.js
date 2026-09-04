const fs = require('fs');
let content = fs.readFileSync('e:/xampp/htdocs/eportal/guru/assets/js/guru.js', 'utf8');

// 1. Extract renderAbsen
const regexAbsen = /(\n\s*)async renderAbsen\(bulan = null\) \{[\s\S]*?\n        \}(?=\n    \};\n\n    \/\/ ===+?\n    \/\/ GLOBAL EXPOSED)/;
const match = content.match(regexAbsen);
if (!match) {
    console.error('Failed to match renderAbsen');
    process.exit(1);
}

const block = match[0];
content = content.replace(block, '');

// 2. Insert into Pages
const pagesEndRegex = /\n        \}\n    \};\n\n    \/\/ ===+?\n    \/\/ APP CONTROLLER/;
if (!pagesEndRegex.test(content)) {
    console.error('Failed to find Pages end');
    process.exit(1);
}

const cleanBlock = block.trim();
const insertion = '\n        },\n\n        ' + cleanBlock + '\n    };\n\n    // =============================================\n    // APP CONTROLLER';

content = content.replace(pagesEndRegex, insertion);

fs.writeFileSync('e:/xampp/htdocs/eportal/guru/assets/js/guru.js', content, 'utf8');
console.log('Success');
