<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#022c22">
    <link rel="manifest" href="/kasir-manifest.json">
    <title><?php echo e(__('ui.offline_cashier.title')); ?> — Lentera</title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css']); ?>
</head>
<body class="bg-[#f7f8f7] font-sans text-gray-800 antialiased">
    <header class="sticky top-0 z-10 bg-lentera-950 text-white shadow">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <div>
                <p class="text-sm font-semibold"><?php echo e(__('ui.offline_cashier.title')); ?></p>
                <p class="text-xs text-lentera-200" id="snapshotInfo"><?php echo e(__('ui.offline_cashier.no_snapshot')); ?></p>
            </div>
            <span id="netPill" class="rounded-full bg-gold/10 px-3 py-1 text-xs font-medium text-gold-light ring-1 ring-inset ring-gold/50">…</span>
        </div>
    </header>

    <main class="mx-auto max-w-5xl space-y-6 px-4 py-6">
        
        <section class="rounded-lg bg-white p-5 shadow-soft">
            <h2 class="font-semibold"><?php echo e(__('ui.offline_cashier.setup_title')); ?></h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <input id="deviceName" type="text" placeholder="KSR1" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                <input id="deviceToken" type="password" placeholder="<?php echo e(__('ui.offline_cashier.token_ph')); ?>" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500 sm:col-span-2">
            </div>
            <div class="mt-3 flex flex-wrap gap-2">
                <button id="saveSetup" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white hover:bg-lentera-800"><?php echo e(__('ui.offline_cashier.save')); ?></button>
                <button id="takeSnapshot" class="rounded-md border border-lentera-700 px-4 py-2 text-sm font-medium text-lentera-800 hover:bg-lentera-50"><?php echo e(__('ui.offline_cashier.take_snapshot')); ?></button>
            </div>
            <p class="mt-2 text-xs text-gray-400"><?php echo e(__('ui.offline_cashier.setup_hint')); ?></p>
        </section>

        
        <section class="rounded-lg bg-white p-5 shadow-soft">
            <h2 class="font-semibold"><?php echo e(__('ui.offline_cashier.new_sale')); ?></h2>
            <label class="mt-3 block text-xs font-medium text-gray-500"><?php echo e(__('ui.offline_cashier.customer')); ?></label>
            <select id="customer" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></select>

            <label class="mt-4 block text-xs font-medium text-gray-500"><?php echo e(__('ui.offline_cashier.add_product')); ?></label>
            <select id="productPick" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></select>

            <table class="mt-3 min-w-full text-sm">
                <thead><tr class="text-left text-gray-500">
                    <th class="py-2 font-medium"><?php echo e(__('ui.offline_cashier.col_product')); ?></th>
                    <th class="py-2 font-medium"><?php echo e(__('ui.offline_cashier.col_price')); ?></th>
                    <th class="py-2 font-medium"><?php echo e(__('ui.offline_cashier.col_qty')); ?></th>
                    <th class="py-2 font-medium"><?php echo e(__('ui.offline_cashier.col_total')); ?></th>
                    <th></th>
                </tr></thead>
                <tbody id="cartBody"></tbody>
            </table>
            <p id="stockWarn" class="mt-1 hidden text-sm font-medium text-red-600"></p>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <p class="text-sm text-gray-500"><?php echo e(__('ui.offline_cashier.cash_only')); ?></p>
                <p class="text-base font-semibold"><?php echo e(__('ui.offline_cashier.grand_total')); ?>: <span id="grandTotal" class="tnum">Rp0</span></p>
            </div>
            <p class="mt-1 text-xs text-gray-400"><?php echo e(__('ui.offline_cashier.final_total_note')); ?></p>
            <div class="mt-3 flex justify-end">
                <button id="queueSale" class="rounded-md bg-lentera-700 px-5 py-2 text-sm font-medium text-white hover:bg-lentera-800"><?php echo e(__('ui.offline_cashier.queue_sale')); ?></button>
            </div>
        </section>

        
        <section class="rounded-lg bg-white p-5 shadow-soft">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold"><?php echo e(__('ui.offline_cashier.queue_title')); ?></h2>
                <button id="syncNow" class="rounded-md bg-gold px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gold-light"><?php echo e(__('ui.offline_cashier.sync_now')); ?></button>
            </div>
            <div id="queueList" class="mt-3 space-y-2 text-sm"></div>
            <p id="queueEmpty" class="mt-3 text-sm text-gray-400"><?php echo e(__('ui.offline_cashier.queue_empty')); ?></p>
        </section>
    </main>

<script>
const STR = <?php echo json_encode($str, 15, 512) ?>;
const $ = (id) => document.getElementById(id);
const store = {
    get(k, d) { try { const v = localStorage.getItem('lk_' + k); return v ? JSON.parse(v) : d; } catch (e) { return d; } },
    set(k, v) { localStorage.setItem('lk_' + k, JSON.stringify(v)); },
};
const fmt = (n) => 'Rp' + Math.round(n).toLocaleString('id-ID');
let cart = [];

function netState() {
    $('netPill').textContent = navigator.onLine ? STR.online : STR.offline;
}
window.addEventListener('online', netState);
window.addEventListener('offline', netState);

function snapshot() { return store.get('snapshot', null); }
function refreshSnapshotInfo() {
    const s = snapshot();
    $('snapshotInfo').textContent = s ? (STR.snapshot_ok + ' ' + new Date(s.taken_at).toLocaleString('id-ID')) : '<?php echo e(__('ui.offline_cashier.no_snapshot')); ?>';
    const cust = $('customer'); cust.innerHTML = '';
    (s ? s.customers : []).forEach(c => { const o = document.createElement('option'); o.value = c.id; o.textContent = c.name + (c.phone ? ' — ' + c.phone : ''); cust.appendChild(o); });
    const pick = $('productPick'); pick.innerHTML = '';
    const ph = document.createElement('option'); ph.value = ''; ph.textContent = '—'; pick.appendChild(ph);
    (s ? s.products : []).forEach(p => { const o = document.createElement('option'); o.value = p.id; o.textContent = p.name + ' (' + p.sku + ') · ' + fmt(p.sale_price) + ' · ' + STR.stock_of + ' ' + p.stock; pick.appendChild(o); });
}
function renderCart() {
    const body = $('cartBody'); body.innerHTML = '';
    let total = 0, over = [];
    cart.forEach((line, idx) => {
        const p = (snapshot()?.products || []).find(x => x.id === line.product_id);
        const price = p ? Number(p.sale_price) : 0;
        total += price * line.qty;
        if (p && line.qty > p.stock && (p.type === 'frame' || p.type === 'accessory')) over.push(p.name);
        const tr = document.createElement('tr'); tr.className = 'border-t border-gray-50';
        tr.innerHTML = '<td class="py-2">' + (p ? p.name : line.product_id) + '</td>' +
            '<td class="py-2 tnum">' + fmt(price) + '</td>' +
            '<td class="py-2"><input type="number" min="1" value="' + line.qty + '" data-idx="' + idx + '" class="qtyInput w-20 rounded-md border-gray-300 text-sm"></td>' +
            '<td class="py-2 tnum">' + fmt(price * line.qty) + '</td>' +
            '<td class="py-2 text-right"><button data-del="' + idx + '" class="text-red-600">' + STR.remove + '</button></td>';
        body.appendChild(tr);
    });
    $('grandTotal').textContent = fmt(total);
    const warn = $('stockWarn');
    if (over.length) { warn.textContent = STR.over_stock + ' ' + over.join(', '); warn.classList.remove('hidden'); } else { warn.classList.add('hidden'); }
    body.querySelectorAll('.qtyInput').forEach(inp => inp.addEventListener('change', () => { cart[Number(inp.dataset.idx)].qty = Math.max(1, Number(inp.value) || 1); renderCart(); }));
    body.querySelectorAll('[data-del]').forEach(btn => btn.addEventListener('click', () => { cart.splice(Number(btn.dataset.del), 1); renderCart(); }));
    return total;
}
function renderQueue() {
    const q = store.get('queue', []);
    $('queueEmpty').style.display = q.length ? 'none' : '';
    const list = $('queueList'); list.innerHTML = '';
    q.forEach(it => {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between rounded-md border border-gray-100 px-3 py-2';
        const left = document.createElement('span');
        left.textContent = it.local_no + ' · ' + it.offline_at + ' · ' + it.items.reduce((a, i) => a + i.qty, 0) + ' item';
        const right = document.createElement('span');
        right.className = it.status === 'exception' ? 'font-medium text-red-600' : 'text-gray-500';
        right.textContent = it.status === 'exception' ? (STR.exception + (it.reason ? ' (' + it.reason + ')' : '')) : STR.queued;
        div.appendChild(left); div.appendChild(right); list.appendChild(div);
    });
}

$('saveSetup').addEventListener('click', () => {
    store.set('device_name', $('deviceName').value.trim());
    store.set('token', $('deviceToken').value.trim());
    $('deviceToken').value = '';
    alert(STR.queued === '' ? '' : 'OK');
});
$('takeSnapshot').addEventListener('click', async () => {
    const token = store.get('token', '');
    if (!token) { alert(STR.need_setup); return; }
    try {
        const res = await fetch('/api/v1/offline/snapshot', { headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('http ' + res.status);
        store.set('snapshot', await res.json());
        refreshSnapshotInfo();
    } catch (e) { alert(STR.sync_failed + ' ' + e.message); }
});
$('productPick').addEventListener('change', () => {
    const id = Number($('productPick').value);
    if (!id) return;
    const found = cart.find(l => l.product_id === id);
    if (found) found.qty += 1; else cart.push({ product_id: id, qty: 1 });
    $('productPick').value = '';
    renderCart();
});
$('queueSale').addEventListener('click', () => {
    if (!snapshot()) { alert(STR.need_snapshot); return; }
    const customerId = Number($('customer').value);
    if (!customerId) { alert(STR.need_customer); return; }
    if (!cart.length) { alert(STR.need_items); return; }
    const total = renderCart();
    const counter = store.get('counter', 0) + 1;
    store.set('counter', counter);
    const prefix = store.get('device_name', 'KSR');
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const offlineAt = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    const intent = {
        client_uuid: (crypto.randomUUID ? crypto.randomUUID() : 'uuid-' + Date.now() + '-' + counter),
        local_no: prefix + '-' + String(counter).padStart(6, '0'),
        offline_at: offlineAt,
        customer_id: customerId,
        tax_mode: 'exclusive',
        items: cart.map(l => ({ product_id: l.product_id, qty: l.qty })),
        payments: [{ method: 'cash', amount: String(total) }],
        status: 'queued',
    };
    const q = store.get('queue', []); q.push(intent); store.set('queue', q);
    cart = []; renderCart(); renderQueue();
});
$('syncNow').addEventListener('click', async () => {
    const token = store.get('token', '');
    if (!token) { alert(STR.need_setup); return; }
    const q = store.get('queue', []);
    const pending = q.filter(i => i.status !== 'exception');
    if (!pending.length) { renderQueue(); return; }
    try {
        const res = await fetch('/api/v1/offline/sync', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ intents: pending.map(({ status, reason, ...rest }) => rest) }),
        });
        if (!res.ok) throw new Error('http ' + res.status);
        const data = await res.json();
        const byUuid = {}; (data.results || []).forEach(r => { byUuid[r.client_uuid] = r; });
        const kept = [];
        q.forEach(it => {
            const r = byUuid[it.client_uuid];
            if (!r) { kept.push(it); return; }
            if (r.status === 'synced' || r.status === 'duplicate') return; // selesai — keluarkan dari antrean
            it.status = 'exception'; it.reason = r.reason || r.status; kept.push(it);
        });
        store.set('queue', kept);
        renderQueue();
        $('takeSnapshot').click();
    } catch (e) { alert(STR.sync_failed + ' ' + e.message); }
});

if ('serviceWorker' in navigator) { navigator.serviceWorker.register('/kasir-sw.js').catch(() => {}); }
$('deviceName').value = store.get('device_name', '');
netState(); refreshSnapshotInfo(); renderCart(); renderQueue();
</script>
</body>
</html>
<?php /**PATH /home/hatch/workspace/lentera/resources/views/sales/offline-cashier.blade.php ENDPATH**/ ?>