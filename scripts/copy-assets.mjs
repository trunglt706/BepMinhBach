// Sao chép Bootstrap, Bootstrap Icons và font Be Vietnam Pro vào public/vendor
// để chạy không cần bước build hay CDN (dùng được trong mạng nội bộ của bếp).
import { cpSync, mkdirSync, readFileSync, writeFileSync, readdirSync } from 'node:fs';

const copies = [
    ['node_modules/bootstrap/dist/css/bootstrap.min.css', 'public/vendor/bootstrap/bootstrap.min.css'],
    ['node_modules/bootstrap/dist/js/bootstrap.bundle.min.js', 'public/vendor/bootstrap/bootstrap.bundle.min.js'],
    ['node_modules/bootstrap-icons/font/bootstrap-icons.min.css', 'public/vendor/bootstrap-icons/bootstrap-icons.min.css'],
    ['node_modules/bootstrap-icons/font/fonts', 'public/vendor/bootstrap-icons/fonts'],
];

for (const [from, to] of copies) {
    mkdirSync(to.substring(0, to.lastIndexOf('/')), { recursive: true });
    cpSync(from, to, { recursive: true });
    console.log(`Đã sao chép ${from} -> ${to}`);
}

// Font: chỉ giữ woff2, trọng số 400–700, các subset vietnamese/latin/latin-ext.
const fontSrc = 'node_modules/@fontsource/be-vietnam-pro';
const fontDest = 'public/vendor/be-vietnam-pro';
const weights = [400, 500, 600, 700];
const subsets = ['vietnamese', 'latin-ext', 'latin'];

mkdirSync(`${fontDest}/files`, { recursive: true });
let css = '/* Be Vietnam Pro — SIL Open Font License 1.1 (sinh tự động bởi scripts/copy-assets.mjs) */\n';
for (const weight of weights) {
    css += readFileSync(`${fontSrc}/${weight}.css`, 'utf8')
        .replace(/,\s*url\([^)]+\.woff\)\s*format\('woff'\)/g, '');
}
writeFileSync(`${fontDest}/font.css`, css);

for (const file of readdirSync(`${fontSrc}/files`)) {
    const wanted = weights.some((w) => file.includes(`-${w}-normal.woff2`))
        && subsets.some((s) => file.includes(`-${s}-`));
    if (wanted) {
        cpSync(`${fontSrc}/files/${file}`, `${fontDest}/files/${file}`);
    }
}
cpSync(`${fontSrc}/LICENSE`, `${fontDest}/LICENSE`);
console.log(`Đã tạo ${fontDest}/font.css`);
