/* POS front-end: grid, barcode scan (AJAX), cart, PAY -> api/sale.php */
(function ($) {
    'use strict';

    var CFG = window.POS;
    var products = CFG.products;
    var byId = {};
    var cart = [];            // [{id,name,price,qty}] newest first
    var method = 'cash';
    var activeCat = 'All';
    var paying = false;
    var audioCtx = null;

    products.forEach(function (p) { byId[p.id] = p; });

    /* ---------- helpers ---------- */
    var ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return ESC[c]; }); }
    function fmt(n) {
        return CFG.currency + ' ' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function num(v) { var n = parseFloat(v); return isFinite(n) && n > 0 ? n : 0; }
    function round2(n) { return Math.round(n * 100) / 100; }

    function beep(ok) {
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            var o = audioCtx.createOscillator(), g = audioCtx.createGain();
            o.type = 'square'; o.frequency.value = ok ? 1400 : 220;
            g.gain.value = 0.05;
            o.connect(g); g.connect(audioCtx.destination);
            o.start(); o.stop(audioCtx.currentTime + (ok ? 0.08 : 0.25));
        } catch (e) { /* audio not available */ }
    }

    function notify(msg, type) {
        var $t = $('<div class="toast align-items-center text-bg-' + (type || 'dark') + ' border-0" role="alert">' +
            '<div class="d-flex"><div class="toast-body">' + esc(msg) + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
        $('#toasts').append($t);
        new bootstrap.Toast($t[0], { delay: 2500 }).show();
        $t.on('hidden.bs.toast', function () { $t.remove(); });
    }

    function focusScan() { $('#barcode').trigger('focus'); }

    /* ---------- product grid ---------- */
    function stockClass(p) { return p.qty <= 0 ? 'out' : (p.qty <= p.low ? 'low' : 'ok'); }
    function stockLabel(p) { return p.qty <= 0 ? 'Out of stock' : p.qty + ' in stock'; }

    function cardHtml(p) {
        var img = p.image
            ? '<img src="' + esc(p.image) + '" alt="" loading="lazy">'
            : '<div class="ph">' + esc(p.name.charAt(0).toUpperCase()) + '</div>';
        var s = (p.name + ' ' + p.barcode + ' ' + p.category).toLowerCase();
        return '<div class="p-card' + (p.qty <= 0 ? ' out' : '') + '" data-id="' + p.id + '" data-cat="' + esc(p.category) + '" data-s="' + esc(s) + '">' +
            '<div class="p-img">' + img + '</div>' +
            '<div class="p-body"><div class="p-name">' + esc(p.name) + '</div>' +
            '<div class="p-price">' + fmt(p.price) + '</div>' +
            '<span class="stock ' + stockClass(p) + '">' + stockLabel(p) + '</span></div></div>';
    }

    function renderGrid() {
        $('#productGrid').html(products.map(cardHtml).join(''));
    }

    function renderCats() {
        var cats = ['All'];
        products.forEach(function (p) { if (cats.indexOf(p.category) < 0) cats.push(p.category); });
        $('#catBar').html(cats.map(function (c) {
            return '<button type="button" class="cat-chip' + (c === activeCat ? ' active' : '') + '" data-cat="' + esc(c) + '">' + esc(c) + '</button>';
        }).join(''));
    }

    function applyFilter() {
        var q = $.trim($('#search').val()).toLowerCase(), shown = 0;
        $('#productGrid .p-card').each(function () {
            var $c = $(this);
            var ok = (activeCat === 'All' || $c.attr('data-cat') === activeCat) && (!q || $c.attr('data-s').indexOf(q) > -1);
            $c.toggleClass('d-none', !ok);
            if (ok) shown++;
        });
        $('#noResults').toggleClass('d-none', shown > 0);
    }

    function refreshCard(p) {
        var $c = $('#productGrid .p-card[data-id="' + p.id + '"]');
        $c.toggleClass('out', p.qty <= 0);
        $c.find('.stock').attr('class', 'stock ' + stockClass(p)).text(stockLabel(p));
        $c.find('.p-price').text(fmt(p.price));
    }

    function refreshStock() {
        $.getJSON(CFG.productsUrl, { ajax: 'list' }).done(function (res) {
            if (!res.success) return;
            res.products.forEach(function (np) {
                if (byId[np.id]) { $.extend(byId[np.id], np); refreshCard(byId[np.id]); }
            });
        });
    }

    /* ---------- cart ---------- */
    function findLine(id) {
        for (var i = 0; i < cart.length; i++) if (cart[i].id === id) return cart[i];
        return null;
    }

    function addToCart(p, qty) {
        qty = qty || 1;
        var stock = byId[p.id] ? byId[p.id].qty : p.qty;
        var line = findLine(p.id);
        var inCart = line ? line.qty : 0;
        if (stock - inCart < qty) {
            notify('Out of stock: ' + p.name, 'danger'); beep(false); return;
        }
        if (line) { line.qty += qty; line.price = p.price; }
        else cart.unshift({ id: p.id, name: p.name, price: p.price, qty: qty });
        beep(true);
        renderCart();
    }

    function calc() {
        var sub = 0, items = 0;
        cart.forEach(function (i) { sub += i.price * i.qty; items += i.qty; });
        sub = round2(sub);
        var disc = Math.min(round2(num($('#discount').val())), sub);
        var total = round2(sub - disc);
        var paid = method === 'card' ? total : num($('#paid').val());
        return { sub: sub, items: items, discount: disc, total: total, paid: paid, change: Math.max(0, round2(paid - total)) };
    }

    function renderTotals() {
        var t = calc();
        $('#itemCount').text(t.items);
        $('#tSub').text(fmt(t.sub));
        $('#tTotal').text(fmt(t.total));
        $('#tChange').text(fmt(t.change));
        if (method === 'card') $('#paid').val(t.total ? t.total.toFixed(2) : '');
        var ready = !paying && cart.length > 0 && (method === 'card' || t.paid + 0.001 >= t.total);
        $('#btnPay').prop('disabled', !ready).html('PAY ' + (t.total ? fmt(t.total) : '') + ' <small>(F9)</small>');
    }

    function renderCart() {
        if (!cart.length) {
            $('#cartItems').html('<div class="empty-state"><i class="bi bi-upc-scan"></i><p>Scan or tap a product to begin</p></div>');
        } else {
            $('#cartItems').html(cart.map(function (i) {
                return '<div class="cart-row" data-id="' + i.id + '">' +
                    '<div class="cr-main"><div class="cr-name">' + esc(i.name) + '</div><div class="cr-price">' + fmt(i.price) + '</div></div>' +
                    '<div class="cr-qty"><button type="button" data-act="dec">&minus;</button><span>' + i.qty + '</span><button type="button" data-act="inc">+</button></div>' +
                    '<div class="cr-total">' + fmt(i.price * i.qty) + '</div>' +
                    '<button type="button" class="cr-del" data-act="del"><i class="bi bi-x-lg"></i></button></div>';
            }).join(''));
        }
        renderTotals();
    }

    function clearCart() {
        cart = [];
        $('#discount').val('');
        $('#paid').val('');
        renderCart();
    }

    /* ---------- events ---------- */
    // Barcode scanner: scanners type the code then press Enter
    $('#barcode').on('keypress', function (e) {
        if (e.which !== 13) return;
        e.preventDefault();
        var code = $.trim(this.value);
        this.value = '';
        if (!code) return;
        $.getJSON(CFG.productsUrl, { barcode: code }).done(function (res) {
            if (res.success) { byId[res.product.id] = $.extend(byId[res.product.id] || {}, res.product); refreshCard(byId[res.product.id]); addToCart(byId[res.product.id]); }
            else { notify('Barcode not found: ' + code, 'warning'); beep(false); }
        }).fail(function () { notify('Could not reach the server.', 'danger'); });
    });

    $('#search').on('input', applyFilter);
    $('#catBar').on('click', '.cat-chip', function () {
        activeCat = $(this).attr('data-cat');
        $('.cat-chip').removeClass('active'); $(this).addClass('active');
        applyFilter(); focusScan();
    });
    $('#productGrid').on('click', '.p-card', function () {
        var p = byId[$(this).data('id')];
        if (p) addToCart(p);
    });

    $('#cartItems').on('click', '[data-act]', function () {
        var id = $(this).closest('.cart-row').data('id');
        var line = findLine(id), act = $(this).data('act');
        if (!line) return;
        if (act === 'inc') {
            if (byId[id].qty - line.qty < 1) { notify('No more stock for ' + line.name, 'danger'); beep(false); return; }
            line.qty++;
        } else if (act === 'dec') {
            line.qty--;
            if (line.qty <= 0) cart = cart.filter(function (i) { return i.id !== id; });
        } else {
            cart = cart.filter(function (i) { return i.id !== id; });
        }
        renderCart();
    });

    $('#btnClear').on('click', function () { if (cart.length && confirm('Clear the current sale?')) { clearCart(); focusScan(); } });
    $('#discount, #paid').on('input', renderTotals);
    $('#paid').on('keypress', function (e) { if (e.which === 13) { e.preventDefault(); pay(); } });

    $('input[name="method"]').on('change', function () {
        method = this.value;
        $('#paid').prop('disabled', method === 'card');
        $('#quickCash').toggle(method === 'cash');
        if (method === 'cash') $('#paid').val('');
        renderTotals();
    });

    $('#quickCash').on('click', 'button', function () {
        var a = $(this).data('amt');
        var t = calc();
        $('#paid').val(a === 'exact' ? t.total.toFixed(2) : a);
        renderTotals();
    });

    $('#btnPay').on('click', pay);

    // Keep barcode input focused unless user is working in another field/modal
    $(document).on('click', function (e) {
        if (!$(e.target).closest('input, select, textarea, .modal, .toast').length) focusScan();
    });
    $('#successModal').on('hidden.bs.modal', focusScan);
    $(document).on('keydown', function (e) {
        if (e.key === 'F2') { e.preventDefault(); focusScan(); }
        else if (e.key === 'F4') { e.preventDefault(); $('#search').trigger('focus').select(); }
        else if (e.key === 'F9') { e.preventDefault(); pay(); }
    });

    /* ---------- PAY ---------- */
    function pay() {
        if (paying || !cart.length) return;
        var t = calc();
        if (method === 'cash' && t.paid + 0.001 < t.total) {
            notify('Amount paid is less than the total.', 'warning'); $('#paid').trigger('focus'); return;
        }
        paying = true;
        $('#btnPay').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

        $.ajax({
            url: CFG.saleUrl,
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: { 'X-CSRF-Token': CFG.csrf },
            data: JSON.stringify({
                items: cart.map(function (i) { return { id: i.id, qty: i.qty }; }),
                discount: t.discount,
                paid: t.paid,
                payment_method: method
            })
        }).done(function (res) {
            if (!res.success) { notify(res.message || 'Sale failed.', 'danger'); return; }
            cart.forEach(function (i) { byId[i.id].qty -= i.qty; refreshCard(byId[i.id]); });
            $('#sInvoice').text(res.invoice_no);
            $('#sTotal').text(fmt(res.total));
            $('#sPaid').text(fmt(res.paid));
            $('#sChange').text(fmt(res.change));
            $('#btnPrintBill').attr('href', CFG.billUrl + '?id=' + res.sale_id + '&print=1');
            clearCart();
            new bootstrap.Modal('#successModal').show();
            beep(true);
        }).fail(function (xhr) {
            var r = xhr.responseJSON || {};
            notify(r.message || 'Server error. Sale was not saved.', 'danger');
            beep(false);
            if (r.error === 'out_of_stock') refreshStock();   // transaction was rolled back; sync stock
        }).always(function () {
            paying = false;
            renderTotals();
        });
    }

    /* ---------- init ---------- */
    renderCats();
    renderGrid();
    renderCart();
    focusScan();

})(jQuery);
