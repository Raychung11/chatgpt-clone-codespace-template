<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();
$user      = Auth::user();
$pageTitle = 'Invoice Generator';
require_once '../includes/header.php';
?>

<div class="container py-5">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/modules/" class="text-muted text-decoration-none"><i class="bi bi-arrow-left fs-5"></i></a>
        <div>
            <h3 class="text-white fw-bold mb-0"><i class="bi bi-receipt me-2 text-warning"></i>Invoice Generator</h3>
            <p class="text-muted small mb-0">Create professional invoices — fill in details and print or save as PDF</p>
        </div>
    </div>

    <div class="row g-4">

        <!-- Form Panel -->
        <div class="col-lg-5">
            <div class="glass-card rounded-4 p-4">
                <form id="invoiceForm">

                    <!-- Invoice Meta -->
                    <h6 class="text-white fw-semibold mb-3">Invoice Info</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Invoice #</label>
                            <input type="text" class="form-control" name="invoice_number" id="invNum" value="INV-<?= date('Ymd') ?>-001">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Currency</label>
                            <select class="form-select" name="currency">
                                <option value="USD">USD ($)</option>
                                <option value="EUR">EUR (€)</option>
                                <option value="GBP">GBP (£)</option>
                                <option value="MYR">MYR (RM)</option>
                                <option value="SGD">SGD (S$)</option>
                                <option value="AUD">AUD (A$)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label text-muted small">Invoice Date</label>
                            <input type="date" class="form-control" name="invoice_date" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small">Due Date</label>
                            <input type="date" class="form-control" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                    </div>

                    <!-- From / To -->
                    <h6 class="text-white fw-semibold mb-2 mt-3">From (Your Business)</h6>
                    <textarea class="form-control mb-3" name="from_company" rows="3" placeholder="Company Name&#10;Address&#10;Email / Phone" required></textarea>

                    <h6 class="text-white fw-semibold mb-2">Bill To (Client)</h6>
                    <textarea class="form-control mb-3" name="to_company" rows="3" placeholder="Client Company Name&#10;Address&#10;Email / Phone" required></textarea>

                    <!-- Line Items -->
                    <h6 class="text-white fw-semibold mb-2">Line Items</h6>
                    <div id="lineItems">
                        <div class="line-item row g-1 mb-2 align-items-center">
                            <div class="col-5"><input type="text" class="form-control form-control-sm item-desc" placeholder="Description"></div>
                            <div class="col-2"><input type="number" class="form-control form-control-sm item-qty" placeholder="Qty" value="1" min="0.01" step="0.01"></div>
                            <div class="col-3"><input type="number" class="form-control form-control-sm item-price" placeholder="Price" min="0" step="0.01"></div>
                            <div class="col-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item" onclick="removeItem(this)"><i class="bi bi-trash"></i></button></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary w-100 mb-3" onclick="addItem()">
                        <i class="bi bi-plus me-1"></i>Add Line Item
                    </button>

                    <!-- Totals -->
                    <div class="p-3 rounded-3 mb-3" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.07)">
                        <div class="d-flex justify-content-between text-muted small mb-1"><span>Subtotal</span><span id="subtotalDisplay">0.00</span></div>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="text-muted small">Tax (%)</span>
                            <input type="number" id="taxRate" class="form-control form-control-sm text-end" style="width:80px" value="0" min="0" max="100" step="0.5" oninput="calcTotals()">
                        </div>
                        <div class="d-flex justify-content-between text-white fw-semibold"><span>Total</span><span id="totalDisplay">0.00</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small">Notes / Payment Terms</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Payment due within 30 days. Thank you for your business."></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning w-100 text-dark fw-semibold" id="generateBtn">
                        <i class="bi bi-magic me-2"></i>Generate Invoice
                    </button>
                </form>
            </div>
        </div>

        <!-- Preview Panel -->
        <div class="col-lg-7">
            <div class="glass-card rounded-4 p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-white fw-semibold mb-0">Invoice Preview</h6>
                    <div id="outputActions" style="display:none">
                        <button class="btn btn-sm btn-warning text-dark fw-semibold" onclick="printInvoice()">
                            <i class="bi bi-printer me-1"></i>Print / Save PDF
                        </button>
                    </div>
                </div>

                <div id="outputPlaceholder" class="text-center py-5">
                    <i class="bi bi-receipt fs-1 text-muted d-block mb-3" style="opacity:0.3"></i>
                    <p class="text-muted small">Fill in the form and click <strong>Generate Invoice</strong></p>
                </div>
                <div id="outputLoading" class="text-center py-5 d-none">
                    <div class="spinner-border text-warning mb-3" role="status"></div>
                    <p class="text-muted small">Generating your invoice…</p>
                </div>
                <div id="outputError" class="alert alert-danger d-none"></div>
                <div id="invoicePreview" class="d-none rounded-3 p-3" style="background:#fff;color:#111;overflow:auto"></div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden print frame -->
<iframe id="printFrame" style="display:none"></iframe>

<script>
function addItem() {
    const row = document.createElement('div');
    row.className = 'line-item row g-1 mb-2 align-items-center';
    row.innerHTML = `
        <div class="col-5"><input type="text" class="form-control form-control-sm item-desc" placeholder="Description"></div>
        <div class="col-2"><input type="number" class="form-control form-control-sm item-qty" placeholder="Qty" value="1" min="0.01" step="0.01"></div>
        <div class="col-3"><input type="number" class="form-control form-control-sm item-price" placeholder="Price" min="0" step="0.01"></div>
        <div class="col-2 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item" onclick="removeItem(this)"><i class="bi bi-trash"></i></button></div>`;
    document.getElementById('lineItems').appendChild(row);
    row.querySelectorAll('input').forEach(i => i.addEventListener('input', calcTotals));
}
function removeItem(btn) {
    const items = document.querySelectorAll('.line-item');
    if (items.length > 1) { btn.closest('.line-item').remove(); calcTotals(); }
}
function calcTotals() {
    let sub = 0;
    document.querySelectorAll('.line-item').forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
        const price = parseFloat(row.querySelector('.item-price')?.value) || 0;
        sub += qty * price;
    });
    const tax = (parseFloat(document.getElementById('taxRate').value) || 0) / 100;
    const total = sub + sub * tax;
    const cur = document.querySelector('[name=currency]').value;
    document.getElementById('subtotalDisplay').textContent = sub.toFixed(2) + ' ' + cur;
    document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' ' + cur;
}
document.querySelectorAll('.item-qty,.item-price').forEach(i => i.addEventListener('input', calcTotals));
document.querySelector('[name=currency]').addEventListener('change', calcTotals);

// Build line items array
function getItems() {
    return [...document.querySelectorAll('.line-item')].map(row => ({
        desc:  row.querySelector('.item-desc')?.value || '',
        qty:   row.querySelector('.item-qty')?.value  || '1',
        price: row.querySelector('.item-price')?.value || '0',
    })).filter(i => i.desc || parseFloat(i.price) > 0);
}

const form = document.getElementById('invoiceForm');
const generateBtn = document.getElementById('generateBtn');

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const items = getItems();
    if (!items.length) { alert('Please add at least one line item.'); return; }

    showLoading();
    const data = new FormData(form);
    data.append('module', 'invoice');
    data.append('items', JSON.stringify(items));
    data.append('tax_rate', document.getElementById('taxRate').value);

    try {
        const res = await fetch('/api/ai-generate.php', { method: 'POST', body: data });
        const json = await res.json();
        if (json.ok) showPreview(json.text);
        else showError(json.error || 'Generation failed.');
    } catch(err) {
        showError('Network error. Please try again.');
    }
});

function showLoading() {
    document.getElementById('outputPlaceholder').classList.add('d-none');
    document.getElementById('outputLoading').classList.remove('d-none');
    document.getElementById('outputError').classList.add('d-none');
    document.getElementById('invoicePreview').classList.add('d-none');
    document.getElementById('outputActions').style.display = 'none';
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating…';
}
function showPreview(html) {
    document.getElementById('outputLoading').classList.add('d-none');
    document.getElementById('outputActions').style.removeProperty('display');
    const el = document.getElementById('invoicePreview');
    el.classList.remove('d-none');
    el.innerHTML = html;
    generateBtn.disabled = false;
    generateBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Generate Invoice';
}
function showError(msg) {
    document.getElementById('outputLoading').classList.add('d-none');
    document.getElementById('outputPlaceholder').classList.add('d-none');
    const el = document.getElementById('outputError');
    el.classList.remove('d-none');
    el.textContent = msg;
    generateBtn.disabled = false;
    generateBtn.innerHTML = '<i class="bi bi-magic me-2"></i>Generate Invoice';
}
function printInvoice() {
    const content = document.getElementById('invoicePreview').innerHTML;
    const w = window.open('', '_blank');
    w.document.write(`<!DOCTYPE html><html><head><title>Invoice</title>
        <style>body{font-family:Arial,sans-serif;margin:40px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}@media print{body{margin:20px}}</style>
        </head><body>${content}<script>window.onload=()=>window.print()<\/script></body></html>`);
    w.document.close();
}
</script>

<?php require_once '../includes/footer.php'; ?>
