<?php
/**
 * rep2 - 最新のコミットを表示
 */

require_once __DIR__ . '/../init.php';

$_login->authorize();

$githubRepo = 'fukumen/p2-php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="Shift_JIS">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Commit Log</title>
    <style>
        :root {
            --bg-color: #f6f8fa;
            --container-bg: #ffffff;
            --text-color: #24292f;
            --text-muted: #57606a;
            --border-color: #d0d7de;
            --link-color: #0969da;
            --btn-bg: #ffffff;
            --btn-hover: #f3f4f6;
            --error-bg: #ffebe9;
            --error-border: #ff8182;
            --error-text: #cf222e;
            --border-subtle: rgba(27,31,36,0.15);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg-color: #010409;
                --container-bg: #0d1117;
                --text-color: #c9d1d9;
                --text-muted: #8b949e;
                --border-color: #30363d;
                --link-color: #58a6ff;
                --btn-bg: #21262d;
                --btn-hover: #30363d;
                --error-bg: rgba(248,81,73,0.1);
                --error-border: rgba(248,81,73,0.4);
                --error-text: #f85149;
                --border-subtle: rgba(240,246,252,0.1);
            }
        }

        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; margin: 0; padding: 10px; background: var(--bg-color); color: var(--text-color); transition: background 0.3s, color 0.3s; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        h1 { font-size: 1.2rem; margin: 0; padding-bottom: 5px; display: flex; align-items: baseline; }
        .github-link { font-size: 0.9rem; margin-left: 10px; color: var(--link-color); text-decoration: none; font-weight: normal; }
        .github-link:hover { text-decoration: underline; }
        .controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .pagination { display: flex; gap: 5px; }
        button, select { padding: 5px 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--btn-bg); color: var(--text-color); cursor: pointer; font-size: 14px; }
        button:hover:not(:disabled), select:hover { background: var(--btn-hover); }
        button:disabled { background: var(--btn-bg); color: var(--text-muted); cursor: not-allowed; opacity: 0.6; }
        .commit { background: var(--container-bg); border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; margin-bottom: 10px; }
        .commit.highlight { border-color: var(--link-color); border-width: 2px; }
        .commit-header { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px; border-bottom: 1px solid var(--border-subtle); padding-bottom: 5px; }
        .current-badge { background-color: var(--link-color); color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; margin-left: 8px; font-weight: bold; vertical-align: middle; }
        .commit-hash { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace; color: var(--link-color); text-decoration: none; font-weight: bold; }
        .commit-hash:hover { text-decoration: underline; }
        .commit-message { font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace; white-space: pre-wrap; font-size: 0.95rem; margin: 0; word-break: break-all; color: var(--text-color); }
        .loading { text-align: center; padding: 20px; color: var(--text-muted); }
        .error { color: var(--error-text); background: var(--error-bg); padding: 10px; border-radius: 6px; border: 1px solid var(--error-border); margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>rep2 Commits<a href="https://github.com/<?php echo $githubRepo; ?>/commits/"<?php echo $_conf['ext_win_target_at']; ?> class="github-link">githubで表示</a></h1>
        <div class="controls">
            <select id="perPage">
                <option value="10" selected>10件表示</option>
                <option value="50">50件表示</option>
                <option value="100">100件表示</option>
            </select>
            <div class="pagination">
                <button id="prevBtn" disabled>前へ</button>
                <span id="pageInfo" style="display: flex; align-items: center; font-size: 14px; margin: 0 5px;">Page 1</span>
                <button id="nextBtn" disabled>次へ</button>
            </div>
        </div>
    </div>
    <div id="error" class="error" style="display: none;"></div>
    <div id="commits"><div class="loading">読み込み中...</div></div>

    <script>
        const extWinAttr = '<?php echo $_conf['ext_win_target_at']; ?>';
        let currentPage = 1;
        let currentPerPage = 10;
        const repo = '<?php echo $githubRepo; ?>';

        const commitsContainer = document.getElementById('commits');
        const errorContainer = document.getElementById('error');
        const perPageSelect = document.getElementById('perPage');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const pageInfo = document.getElementById('pageInfo');

        function formatDate(isoString) {
            const d = new Date(isoString);
            return d.toLocaleString('ja-JP', { 
                timeZone: 'Asia/Tokyo',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        async function fetchCommits() {
            commitsContainer.innerHTML = '<div class="loading">読み込み中...</div>';
            errorContainer.style.display = 'none';
            prevBtn.disabled = true;
            nextBtn.disabled = true;
            perPageSelect.disabled = true;

            try {
                const res = await fetch(`https://api.github.com/repos/${repo}/commits?page=${currentPage}&per_page=${currentPerPage}`);
                
                if (!res.ok) {
                    if (res.status === 403) {
                        throw new Error('APIのレート制限に達しました。しばらく待ってから再試行してください。');
                    }
                    throw new Error(`API Error: ${res.status} ${res.statusText}`);
                }

                const commits = await res.json();
                
                const linkHeader = res.headers.get('Link');
                const hasNext = linkHeader && linkHeader.includes('rel="next"');

                renderCommits(commits);

                pageInfo.textContent = `Page ${currentPage}`;
                prevBtn.disabled = currentPage === 1;
                nextBtn.disabled = !hasNext;
            } catch (err) {
                errorContainer.textContent = '取得に失敗しました: ' + err.message;
                errorContainer.style.display = 'block';
                commitsContainer.innerHTML = '';
            } finally {
                perPageSelect.disabled = false;
            }
        }

        function escapeHtml(str) {
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function renderCommits(commits) {
            if (!Array.isArray(commits) || commits.length === 0) {
                commitsContainer.innerHTML = '<div class="loading">コミットがありません。</div>';
                return;
            }

            const urlParams = new URLSearchParams(window.location.search);
            const currentHash = urlParams.get('hash');

            let html = '';
            for (const item of commits) {
                const c = item.commit;
                const authorName = c.author ? c.author.name : 'Unknown';
                const dateStr = c.author ? formatDate(c.author.date) : '';
                const message = escapeHtml(c.message);
                const hash = item.sha.substring(0, 7);
                const url = item.html_url;

                const isCurrent = currentHash && hash === currentHash;
                const highlightClass = isCurrent ? ' highlight' : '';
                const badge = isCurrent ? '<span class="current-badge">現在使用中</span>' : '';

                html += `
                    <div class="commit${highlightClass}">
                        <div class="commit-header">
                            <span><a href="${url}"${extWinAttr} class="commit-hash">${hash}</a> by ${escapeHtml(authorName)}${badge}</span>
                            <span>${dateStr}</span>
                        </div>
                        <pre class="commit-message">${message}</pre>
                    </div>
                `;
            }
            commitsContainer.innerHTML = html;
        }

        perPageSelect.addEventListener('change', (e) => {
            currentPerPage = parseInt(e.target.value, 10);
            currentPage = 1;
            fetchCommits();
        });

        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                fetchCommits();
            }
        });

        nextBtn.addEventListener('click', () => {
            currentPage++;
            fetchCommits();
        });

        fetchCommits();
    </script>
</body>
</html>

<?php
/*
 * Local Variables:
 * mode: php
 * coding: cp932
 * tab-width: 4
 * c-basic-offset: 4
 * indent-tabs-mode: nil
 * End:
 */
// vim: set syn=php fenc=cp932 ai et ts=4 sw=4 sts=4 fdm=marker:
