// Tạo bộ icon rút gọn: chỉ giữ các icon Bootstrap Icons thực sự dùng trong ứng dụng.
// Font đầy đủ ~130 KB (2.000+ icon, font-display: block) làm icon hiện chậm và giật khi tải trang;
// bản rút gọn chỉ vài KB nên tải gần như tức thì và được preload trong <head>.
//
// Chạy lại mỗi khi thêm icon mới:  npm run icons   (cũng chạy trong npm run assets)
import { readFileSync, writeFileSync, readdirSync, statSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import subsetFont from 'subset-font';

const SOURCES = ['resources', 'app', 'public/js/app.js'];
const ICONS = 'node_modules/bootstrap-icons/font';
const DEST = 'public/vendor/bootstrap-icons';
const FONT_FILE = 'fonts/bootstrap-icons-subset.woff2';

function walk(path, files = []) {
    if (statSync(path).isDirectory()) {
        for (const entry of readdirSync(path)) {
            walk(join(path, entry), files);
        }
    } else if (/\.(php|js)$/.test(path)) {
        files.push(path);
    }
    return files;
}

const codepoints = JSON.parse(readFileSync(`${ICONS}/bootstrap-icons.json`, 'utf8'));
const used = new Set();
for (const file of SOURCES.flatMap((source) => walk(source))) {
    for (const match of readFileSync(file, 'utf8').matchAll(/\bbi-([a-z0-9]+(?:-[a-z0-9]+)*)/g)) {
        if (codepoints[match[1]] !== undefined) {
            used.add(match[1]);
        }
    }
}
const names = [...used].sort();

const text = names.map((name) => String.fromCodePoint(codepoints[name])).join('');
const font = await subsetFont(readFileSync(`${ICONS}/fonts/bootstrap-icons.woff2`), text, { targetFormat: 'woff2' });
mkdirSync(`${DEST}/fonts`, { recursive: true });
writeFileSync(`${DEST}/${FONT_FILE}`, font);

// Phiên bản theo nội dung font: layout tính lại cùng giá trị (md5_file) cho thẻ preload, nên URL luôn khớp.
const version = createHash('md5').update(font).digest('hex').slice(0, 10);

let css = '/* Bootstrap Icons v1.13.1 (MIT) — bản rút gọn, sinh tự động bởi scripts/build-icons.mjs. Không sửa tay. */\n'
    + `@font-face{font-display:block;font-family:bootstrap-icons;src:url("${FONT_FILE}?v=${version}") format("woff2")}\n`
    // width 1em giữ chỗ cho icon ngay cả khi font chưa tải xong: không xô lệch chữ bên cạnh.
    + '.bi::before,[class*=" bi-"]::before,[class^=bi-]::before{display:inline-block;width:1em;text-align:center;'
    + 'font-family:bootstrap-icons!important;font-style:normal;font-weight:400!important;font-variant:normal;'
    + 'text-transform:none;line-height:1;vertical-align:-.125em;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}\n';
for (const name of names) {
    css += `.bi-${name}::before{content:"\\${codepoints[name].toString(16)}"}\n`;
}
writeFileSync(`${DEST}/bootstrap-icons.subset.css`, css);

console.log(`Đã tạo bộ icon rút gọn: ${names.length} icon, ${(font.length / 1024).toFixed(1)} KB (bản đầy đủ ${(statSync(`${ICONS}/fonts/bootstrap-icons.woff2`).size / 1024).toFixed(0)} KB).`);
