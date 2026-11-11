<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PromptLab API Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,700|jetbrains-mono:400" rel="stylesheet">
    <style>
        /* Design tokens from notes/DESIGN.md (NVIDIA majority, Huawei for gaps) */
        :root {
            --primary: #76b900; --primary-dark: #5a8d00; --on-primary: #000;
            --ink: #000; --body: #1a1a1a; --mute: #757575;
            --canvas: #fff; --surface-soft: #f7f7f7; --surface-dark: #000; --surface-elevated: #1a1a1a;
            --hairline: #ccc; --hairline-strong: #5e5e5e; --on-dark: #fff; --on-dark-mute: rgba(255,255,255,.7);
            --error: #e52020; --success-deep: #3f8500; --link: #0046a4;
            --overlay: rgba(0,0,0,.6); --input-fill: rgba(0,0,0,.05);
            --r-sm: 2px; --r-md: 4px;
            --font: Inter, Arial, Helvetica, sans-serif;
            --mono: "JetBrains Mono", ui-monospace, Menlo, monospace;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--canvas); color: var(--body); font: 400 16px/1.5 var(--font); }
        [hidden] { display: none !important; }
        :focus-visible { outline: 2px solid var(--primary-dark); outline-offset: 2px; }
        .wrap { max-width: 1280px; margin: 0 auto; padding: 0 24px; }

        /* Primary nav + breadcrumb (NVIDIA) */
        .nav { background: var(--surface-dark); color: var(--on-dark); height: 64px; }
        .nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 100%; gap: 16px; }
        .brand { font-weight: 700; font-size: 18px; display: flex; align-items: center; gap: 8px; }
        .brand::before { content: ""; width: 12px; height: 12px; background: var(--primary); }
        .nav-user { display: flex; align-items: center; gap: 12px; font-size: 14px; color: var(--on-dark-mute); min-width: 0; }
        .nav-user span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .crumb { background: var(--surface-soft); height: 48px; display: flex; align-items: center;
                 font: 700 14px/1.43 var(--font); text-transform: uppercase; color: var(--body); }
        .crumb .wrap { width: 100%; }
        .crumb .sep { color: var(--mute); margin: 0 8px; }

        /* Buttons (NVIDIA; hover darkens per Huawei rule) */
        .btn { font: 700 14px/1.25 var(--font); height: 44px; padding: 0 24px; border-radius: var(--r-md);
               border: 2px solid transparent; cursor: pointer; }
        .btn-primary { background: var(--primary); color: var(--on-primary); }
        .btn-primary:hover, .btn-primary:active { background: var(--primary-dark); }
        .btn-primary:disabled { background: var(--surface-soft); color: var(--mute); cursor: wait; }
        .btn-outline { background: transparent; color: var(--ink); border-color: var(--primary); }
        .btn-outline:hover { border-color: var(--primary-dark); }
        .btn-dark { background: transparent; color: var(--on-dark); border: 1px solid var(--on-dark); height: 36px; padding: 0 16px; }
        .btn-dark:hover { background: var(--surface-elevated); }

        /* Cards with corner-square (NVIDIA) */
        main { padding: 32px 0 64px; }
        .card { position: relative; border: 1px solid var(--hairline); border-radius: var(--r-sm); padding: 24px; background: var(--canvas); }
        .card::before { content: ""; position: absolute; top: -1px; left: -1px; width: 12px; height: 12px; background: var(--primary); }
        .card h2 { font: 700 20px/1.25 var(--font); color: var(--ink); margin: 0 0 16px; }
        .grid { display: grid; gap: 24px; grid-template-columns: 2fr 3fr; align-items: start; }
        @media (max-width: 1023px) { .grid { grid-template-columns: 1fr; } }
        .login { max-width: 440px; margin: 0 auto; }
        .stack > * + * { margin-top: 16px; }

        /* Form fields (NVIDIA input, Huawei label/helper/error + filled textarea) */
        label, .label { display: block; font: 700 14px/1.43 var(--font); color: var(--ink); margin-bottom: 4px; }
        .input { width: 100%; height: 44px; padding: 0 16px; font: 400 16px var(--font); color: var(--ink);
                 background: var(--canvas); border: 1px solid var(--hairline-strong); border-radius: var(--r-sm); }
        .input:focus, textarea:focus { outline: none; border: 2px solid var(--primary-dark); }
        textarea { width: 100%; min-height: 140px; resize: vertical; padding: 12px 16px; font: 400 16px/1.5 var(--font);
                   background: var(--input-fill); border: 1px solid transparent; border-radius: var(--r-sm); color: var(--ink); }
        .field-foot { display: flex; justify-content: space-between; gap: 8px; margin-top: 4px; font-size: 12px; }
        .helper { color: var(--mute); }
        .counter { font-family: var(--mono); color: var(--mute); }
        .counter.over, .err { color: var(--error); }
        .invalid { border-color: var(--error) !important; }

        /* Mode tabs (NVIDIA pill-tab) */
        .tabs { display: flex; gap: 8px; }
        .tab { font: 700 14px/1 var(--font); padding: 10px 18px; min-height: 44px; border: 1px solid var(--ink);
               border-radius: var(--r-sm); background: transparent; color: var(--ink); cursor: pointer; }
        .tab[aria-pressed="true"] { background: var(--ink); color: var(--on-dark); }

        /* Response panel, badges, alert (designed from NVIDIA tokens) */
        .panel { background: var(--surface-elevated); color: var(--on-dark); font: 400 14px/1.6 var(--mono);
                 border-radius: var(--r-sm); padding: 24px; min-height: 240px; white-space: pre-wrap; word-break: break-word; }
        .panel.empty { color: var(--on-dark-mute); }
        .panel.streaming::after { content: ""; display: inline-block; width: 8px; height: 16px; margin-left: 2px;
                                  vertical-align: text-bottom; background: var(--primary); animation: blink 1s steps(1) infinite; }
        @keyframes blink { 50% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) { .panel.streaming::after { animation: none; } }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .badge { background: var(--surface-soft); color: var(--body); font: 700 12px/1.43 var(--font); text-transform: uppercase;
                 border-radius: var(--r-sm); padding: 4px 10px; display: inline-flex; align-items: center; gap: 6px; }
        .badge .mono { font-family: var(--mono); font-weight: 400; text-transform: none; }
        .badge.success::before, .badge.failed::before { content: ""; width: 8px; height: 8px; background: var(--primary); }
        .badge.failed::before { background: var(--error); }
        .alert { background: var(--surface-soft); border-left: 4px solid var(--error); padding: 12px 16px; font-size: 15px; margin-bottom: 12px; }

        /* History table (NVIDIA) */
        .history { margin-top: 24px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 15px; }
        th { background: var(--surface-soft); font: 700 12px/1.43 var(--font); text-transform: uppercase; text-align: left; color: var(--body); }
        th, td { padding: 8px 12px; border-bottom: 1px solid var(--hairline); vertical-align: top; }
        td.mono { font-family: var(--mono); font-size: 13px; white-space: nowrap; }
        td.prompt { max-width: 420px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Token dialog (Huawei dialog pattern) */
        dialog { border: 0; border-radius: var(--r-sm); padding: 24px; max-width: 480px; width: calc(100% - 32px); }
        dialog::backdrop { background: var(--overlay); }
        dialog h2 { font: 700 20px/1.25 var(--font); margin: 0 0 8px; color: var(--ink); }
        dialog code { display: block; background: var(--surface-elevated); color: var(--on-dark); font: 400 13px/1.5 var(--mono);
                      padding: 12px; border-radius: var(--r-sm); word-break: break-all; margin: 12px 0; }
        .dialog-foot { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }

        footer { background: var(--surface-dark); color: var(--on-dark-mute); font-size: 12px; padding: 24px 0; }
        @media (max-width: 480px) { .wrap { padding: 0 16px; } .nav-user span { display: none; } }
    </style>
</head>
<body>
<nav class="nav">
    <div class="wrap">
        <div class="brand">PromptLab API Hub</div>
        <div class="nav-user" id="navUser" hidden>
            <span id="userEmail"></span>
            <button class="btn btn-dark" id="logoutBtn" type="button">Keluar</button>
        </div>
    </div>
</nav>
<div class="crumb"><div class="wrap">API Hub<span class="sep">/</span>Playground</div></div>

<main class="wrap">
    {{-- Login: exchanges email/password for a Sanctum Bearer token --}}
    <section class="card login" id="loginView">
        <h2>Masuk untuk mendapatkan token</h2>
        <form id="loginForm" class="stack" novalidate>
            <div>
                <label for="email">Email</label>
                <input class="input" id="email" type="email" autocomplete="username" value="demo@promptlab.test" required>
            </div>
            <div>
                <label for="password">Password</label>
                <input class="input" id="password" type="password" autocomplete="current-password" required>
                <div class="field-foot"><span class="helper">Akun demo dari seeder: password <code>password</code></span></div>
            </div>
            <p class="err" id="loginError" role="alert" hidden></p>
            <button class="btn btn-primary" type="submit">Masuk</button>
        </form>
    </section>

    {{-- Chat: prompt form (left) and AI answer (right) --}}
    <section id="chatView" hidden>
        <div class="grid">
            <form class="card stack" id="chatForm" novalidate>
                <h2>Kirim prompt</h2>
                <div>
                    <span class="label" id="modeLabel">Mode respons</span>
                    <div class="tabs" role="group" aria-labelledby="modeLabel">
                        <button type="button" class="tab" data-mode="normal" aria-pressed="false">Biasa</button>
                        <button type="button" class="tab" data-mode="stream" aria-pressed="true">Streaming</button>
                    </div>
                </div>
                <div>
                    <label for="theme">Tema agent (system prompt)</label>
                    <select class="input" id="theme">
                        @foreach ($themes as $theme)
                            <option value="{{ $theme }}">{{ $theme }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="prompt">Prompt</label>
                    <textarea id="prompt" maxlength="1200" placeholder="Contoh: Jelaskan middleware di Laravel!"></textarea>
                    <div class="field-foot">
                        <span class="err" id="promptError" role="alert"></span>
                        <span class="counter" id="counter">0/1000</span>
                    </div>
                </div>
                <button class="btn btn-primary" id="sendBtn" type="submit">Kirim</button>
            </form>

            <div class="card">
                <h2>Jawaban AI</h2>
                <div class="alert" id="chatError" role="alert" hidden></div>
                <div class="panel empty" id="answer" aria-live="polite">Jawaban AI akan muncul di sini.</div>
                <div class="meta" id="meta"></div>
            </div>
        </div>

        <div class="card history">
            <h2>Riwayat chat</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Waktu</th><th>Tema</th><th>Model</th><th>Token</th><th>Status</th><th>Prompt</th></tr></thead>
                    <tbody id="historyBody"></tbody>
                </table>
            </div>
        </div>
    </section>
</main>

<footer><div class="wrap">Secure LLM Chat API Hub · Tugas 11 PBKK · Kelompok 1</div></footer>

{{-- Shows the plain-text token once, like Sanctum does --}}
<dialog id="tokenDialog">
    <h2>Token Bearer Anda</h2>
    <p class="helper">Token hanya ditampilkan sekali. Salin untuk dipakai di Postman: <b>Authorization: Bearer &lt;token&gt;</b></p>
    <code id="tokenText"></code>
    <div class="dialog-foot">
        <button class="btn btn-outline" id="copyBtn" type="button">Salin</button>
        <button class="btn btn-primary" id="closeDialog" type="button">Tutup</button>
    </div>
</dialog>

<script>
    // Small helpers
    const $ = (id) => document.getElementById(id);
    let mode = 'stream';

    // Per-tab token storage; storage may be blocked, so every access is guarded.
    // Demo only: a token in sessionStorage is readable by any script on this page.
    const store = {
        get() { try { return sessionStorage.getItem('token'); } catch { return null; } },
        set(v) { try { v ? sessionStorage.setItem('token', v) : sessionStorage.removeItem('token'); } catch {} },
    };

    // fetch() wrapper that adds the Bearer token and JSON headers
    function api(path, options = {}) {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
        const token = store.get();
        if (token) headers.Authorization = 'Bearer ' + token;
        return fetch('/api' + path, { ...options, headers });
    }

    // Switch between the login view and the chat view
    function showChat(user) {
        $('loginView').hidden = !!user;
        $('chatView').hidden = !user;
        $('navUser').hidden = !user;
        $('userEmail').textContent = user ? user.email : '';
        if (user) loadHistory();
    }

    // Restore a session from a stored token
    async function boot() {
        if (!store.get()) return showChat(null);
        const res = await api('/user');
        if (!res.ok) { store.set(null); return showChat(null); }
        showChat(await res.json());
    }

    $('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        $('loginError').hidden = true;
        const res = await api('/login', { method: 'POST', body: JSON.stringify({
            email: $('email').value, password: $('password').value, device_name: 'playground' }) });
        const json = await res.json();
        if (!res.ok) {
            $('loginError').textContent = json.message || 'Login gagal.';
            $('loginError').hidden = false;
            return;
        }
        store.set(json.access_token);
        $('tokenText').textContent = json.access_token;
        $('tokenDialog').showModal();
        showChat(json.user);
    });

    $('logoutBtn').addEventListener('click', async () => {
        await api('/logout', { method: 'POST' });
        store.set(null);
        showChat(null);
    });

    $('copyBtn').addEventListener('click', () => navigator.clipboard?.writeText($('tokenText').textContent));
    $('closeDialog').addEventListener('click', () => $('tokenDialog').close());

    // Mode tabs: Biasa (POST /chat-ai) or Streaming (POST /chat-ai/stream)
    document.querySelectorAll('.tab').forEach((tab) => tab.addEventListener('click', () => {
        mode = tab.dataset.mode;
        document.querySelectorAll('.tab').forEach((t) => t.setAttribute('aria-pressed', String(t === tab)));
    }));

    // Live character counter, mirrors the server's max:1000 rule
    $('prompt').addEventListener('input', () => {
        const n = $('prompt').value.length;
        $('counter').textContent = n + '/1000';
        $('counter').classList.toggle('over', n > 1000);
    });

    // Render provider/model/token/status badges under the answer
    function renderMeta(data) {
        $('meta').replaceChildren(...[
            ['Provider', data.provider], ['Model', data.model_used], ['Token', data.tokens?.total ?? '-'],
        ].map(([k, v]) => {
            const b = document.createElement('span');
            b.className = 'badge';
            b.append(k + ' ');
            const m = document.createElement('span');
            m.className = 'mono';
            m.textContent = v ?? '-';
            b.append(m);
            return b;
        }), statusBadge(data.status));
    }

    function statusBadge(status) {
        const b = document.createElement('span');
        b.className = 'badge ' + status;
        b.textContent = status;
        return b;
    }

    // Show validation (422) or server errors
    function showError(res, json) {
        if (res.status === 422 && json.errors?.prompt) {
            $('promptError').textContent = json.errors.prompt[0];
            $('prompt').classList.add('invalid');
        } else if (res.status === 401) {
            store.set(null);
            showChat(null);
        } else {
            $('chatError').textContent = json.message || 'Terjadi kesalahan.';
            $('chatError').hidden = false;
        }
    }

    $('chatForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const answer = $('answer');
        $('promptError').textContent = '';
        $('prompt').classList.remove('invalid');
        $('chatError').hidden = true;
        $('meta').replaceChildren();
        answer.textContent = '';
        answer.classList.remove('empty');
        $('sendBtn').disabled = true;

        const body = JSON.stringify({ prompt: $('prompt').value, theme: $('theme').value });
        try {
            if (mode === 'normal') {
                answer.textContent = 'Menunggu jawaban lengkap...';
                const res = await api('/chat-ai', { method: 'POST', body });
                const json = await res.json();
                answer.textContent = '';
                if (!res.ok) return showError(res, json);
                answer.textContent = json.data.ai_response;
                renderMeta(json.data);
            } else {
                await streamAnswer(body, answer);
            }
        } catch (err) {
            $('chatError').textContent = 'Tidak dapat terhubung ke server.';
            $('chatError').hidden = false;
        } finally {
            answer.classList.remove('streaming');
            $('sendBtn').disabled = false;
            loadHistory();
        }
    });

    // Read the SSE stream with fetch (EventSource cannot POST or send headers)
    async function streamAnswer(body, answer) {
        const res = await api('/chat-ai/stream', { method: 'POST', body });
        if (!res.ok) return showError(res, await res.json());

        answer.classList.add('streaming');
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        for (;;) {
            const { value, done } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });
            // Events are separated by a blank line
            let cut;
            while ((cut = buffer.indexOf('\n\n')) !== -1) {
                const raw = buffer.slice(0, cut);
                buffer = buffer.slice(cut + 2);
                const event = raw.match(/^event: (.*)$/m)?.[1];
                const data = JSON.parse(raw.match(/^data: (.*)$/m)?.[1] || '{}');
                if (event === 'token') answer.textContent += data.delta;
                if (event === 'done') renderMeta(data.data);
                if (event === 'error') { $('chatError').textContent = data.message; $('chatError').hidden = false; }
            }
        }
    }

    // Fill the history table (textContent only: AI output is never parsed as HTML)
    async function loadHistory() {
        const res = await api('/chat-ai/history');
        if (!res.ok) return;
        const rows = (await res.json()).data;
        const tbody = $('historyBody');
        if (!rows.length) {
            const tr = tbody.insertRow();
            const td = tr.insertCell();
            td.colSpan = 7;
            td.className = 'helper';
            td.textContent = 'Belum ada riwayat.';
            return tbody.replaceChildren(tr);
        }
        tbody.replaceChildren(...rows.map((r) => {
            const tr = document.createElement('tr');
            [[r.session_id, 'mono'], [r.timestamp, 'mono'], [r.theme, 'mono'], [r.model_used ?? '-', 'mono'],
             [r.tokens.total ?? '-', 'mono'], [null, ''], [r.prompt, 'prompt']].forEach(([text, cls]) => {
                const td = tr.insertCell();
                td.className = cls;
                if (text === null) td.append(statusBadge(r.status)); else td.textContent = text;
                if (cls === 'prompt') td.title = r.prompt;
            });
            return tr;
        }));
    }

    boot();
</script>
</body>
</html>
