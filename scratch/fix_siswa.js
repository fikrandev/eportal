const fs = require('fs');
let content = fs.readFileSync('e:/xampp/htdocs/eportal/siswa/assets/js/siswa.js', 'utf8');
content = content.replace(/\\`/g, '`').replace(/\\\$\{/g, '${');
fs.writeFileSync('e:/xampp/htdocs/eportal/siswa/assets/js/siswa.js', content);
console.log('Fixed siswa.js');
