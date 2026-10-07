// リポジトリ同梱の jQuery を jsdom の window にロードする (バージョン固定)
import { readFileSync } from 'node:fs';

export const JQUERY_PATH = '/var/www/rep2/js/jquery-3.7.1.min.js';

// installFakeXHR は loadJquery の前に呼ぶこと (jQuery はロード時に cors 判定のため xhr() を一度呼ぶ)
export function loadJquery(win) {
  win.eval(readFileSync(JQUERY_PATH, 'utf8'));
}
