@extends('layouts.app')
@section('title', 'Order #' . $order->wc_order_id)

@section('content')

<div class="mb-4">
    <a href="{{ route('orders.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al listado</a>
</div>

{{-- Aviso de presencia: otros usuarios mirando esta misma orden (heartbeat) --}}
<div id="presence-banner"
     class="hidden mb-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1a4 4 0 100-8 4 4 0 000 8z" />
    </svg>
    <span id="presence-text"></span>
</div>

{{-- Modal de control exclusivo: aparece si otro usuario ya tiene la orden --}}
<div id="control-modal"
     class="fixed inset-0 z-[60] hidden items-center justify-center bg-zinc-900/50 p-4">
    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 0h10.5a2.25 2.25 0 0 1 2.25 2.25v6a2.25 2.25 0 0 1-2.25 2.25H6.75a2.25 2.25 0 0 1-2.25-2.25v-6a2.25 2.25 0 0 1 2.25-2.25Z" />
                </svg>
            </div>
            <div class="min-w-0">
                <h3 id="control-modal-title" class="text-base font-semibold text-zinc-900">Hay alguien más en esta orden</h3>
                <p id="control-modal-text" class="mt-1 text-sm text-zinc-600"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a id="control-modal-leave" href="{{ route('orders.index') }}"
               class="inline-flex items-center justify-center rounded-lg border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-100 hover:text-zinc-900">
                Ir al listado
            </a>
            <button type="button" id="control-modal-take"
                    class="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-zinc-700">
                Tomar el control
            </button>
        </div>
    </div>
</div>

{{-- Toast container para feedback de optimistic UI --}}
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

{{-- HEADER --}}
<div class="rounded-lg border border-zinc-200 bg-white p-5 mb-6">
    <div class="flex items-start justify-between gap-6">
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Order de WooCommerce</div>
            <div class="font-mono text-2xl font-semibold">#{{ $order->wc_order_id }}</div>
            <div class="text-xs text-zinc-500 font-mono mt-1">{{ $order->order_date->format('Y-m-d H:i') }}</div>
        </div>
        <div class="flex-1">
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Cliente</div>
            <div class="font-medium">{{ $order->customer_name }}</div>
            <div class="text-sm text-zinc-600 font-mono">{{ $order->customer_email }}</div>
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Total</div>
            <div class="font-mono text-lg font-semibold">
                @if ($order->total_amount)
                    {{ $order->currency }} {{ number_format($order->total_amount, 2) }}
                @else — @endif
            </div>
            @if ($order->total_raw)
                <div class="text-xs text-zinc-400 font-mono">{{ $order->total_raw }}</div>
            @endif
        </div>
        <div>
            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 mb-1">Status Woo</div>
            @php $color = $order->statusColor(); @endphp
            <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $color }}-50  px-2 py-0.5 text-xs font-medium text-{{ $color }}-700 ring-1 ring-inset ring-{{ $color }}-600/20">
                <span class="h-1.5 w-1.5 rounded-full bg-{{ $color }}-500"></span>
                {{ $order->wc_status }}
            </span>
        </div>
    </div>
</div>

@php
    $processingStatus = config('services.woo.processing_status', 'processing');
    $pendingAssignable = $order->items
        ->where('fulfillment_status', 'pending')
        ->filter(function ($it) use ($candidatesByItem) {
            $res = $candidatesByItem[$it->id] ?? null;
            return $res && ! $res->isEmpty() && $res->best();
        });
    $showAssignAll = $order->items->count() > 1
        && $order->wc_status === $processingStatus
        && $pendingAssignable->isNotEmpty();
@endphp

<div class="flex items-center justify-between mb-3">
    <h2 class="text-lg font-semibold">Items ({{ $order->items->count() }})</h2>

    <div class="flex gap-4">

        @if ($showAssignAll)
            <form method="POST" action="{{ route('orders.assign-all', $order) }}" data-assign-all-form>
                @csrf
                <button type="submit"
                        class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                    Enviar todos los productos ({{ $pendingAssignable->count() }})
                </button>
            </form>
        @endif
    
        @if ($order->items->where('fulfillment_status', 'pending')->isNotEmpty())
            <button type="button" onclick="openAddItem()"
                    class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">
                Agregar item / reemplazar
            </button>
        @endif
    </div>

    {{-- (c) include del modal, junto a los otros @include --}}
    @include('orders.partials._add-item-modal', ['order' => $order])
</div>

<div class="space-y-4" id="items-list">
    @foreach ($order->items as $item)
        @include('orders.partials._item-card', [
            'item'   => $item,
            'result' => $candidatesByItem[$item->id] ?? null,
            'order'  => $order,
            'resettableSuggestions' => $resetSuggestionsByItem[$item->id] ?? null,
            'packSuggestions' => $packSuggestionsByItem[$item->id] ?? null,
        ])
    @endforeach
</div>

{{-- NUEVO: sugerencias de reseteo --}}
@include('orders.partials._reset-suggestions', [
    'order'       => $order,
    'suggestions' => $resetSuggestionsByItem,
])

{{-- ════════ MODAL RESET (compartido, se rellena por JS) ════════ --}}
<div id="reset-modal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-zinc-900/50" onclick="closeResetModal()"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" onclick="event.stopPropagation()">
            <form method="POST" id="reset-form" action="">
                @csrf
                <input type="hidden" name="key_id" id="reset-key-id" value="">

                <div class="px-6 py-4 border-b border-zinc-200">
                    <h3 class="text-lg font-semibold">Resetear cuenta en la plataforma</h3>
                    <p class="text-xs text-zinc-500 font-mono mt-1" id="reset-account-email"></p>
                </div>

                <div class="px-6 py-4 space-y-4 text-sm">
                    <p class="text-zinc-500">
                        Ingresá a la plataforma con estos datos y realizá el reset manualmente.
                        La llave que se muestra se consumirá al confirmar.
                    </p>

                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-xs space-y-1">
                        <div><span class="text-zinc-400">Email:</span> <span id="reset-email"></span></div>
                        <div><span class="text-zinc-400">Password:</span> <span id="reset-password"></span></div>
                        <div><span class="text-zinc-400">Llave:</span> <span id="reset-key"></span></div>
                    </div>

                    <p class="font-medium">¿Ya realizaste el reseteo en la plataforma?</p>
                </div>

                <div class="px-6 py-3 border-t border-zinc-200 flex justify-end gap-2">
                    <button type="button" onclick="closeResetModal()"
                            class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="rounded-md bg-amber-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                        Sí, ya reseteé
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

function openResetModal(data) {
    const form = document.getElementById('reset-form');
    form.action = data.resetUrl;

    document.getElementById('reset-key-id').value      = data.keyId || '';
    document.getElementById('reset-account-email').textContent = data.email || '';
    document.getElementById('reset-email').textContent    = data.email || '';
    document.getElementById('reset-password').textContent = data.password || '—';

    const keyEl = document.getElementById('reset-key');
    if (data.keyId) {
        keyEl.textContent = `${data.keyValue} #${data.keyPosition}`;
        keyEl.className = '';
    } else {
        keyEl.textContent = 'sin llave disponible';
        keyEl.className = 'text-amber-600';
    }

    document.getElementById('reset-modal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeResetModal() {
    document.getElementById('reset-modal').classList.add('hidden');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !document.getElementById('reset-modal').classList.contains('hidden')) {
        closeResetModal();
    }
});

/* ═════════════════════════════════════════════════════════════
   OPTIMISTIC UI para asignaciones + generación de OC
   ═════════════════════════════════════════════════════════════ */

/** Toast simple. Tipos: 'success', 'error', 'info'. */
function showToast(message, type = 'success') {
    const colors = {
        success: 'bg-emerald-600',
        error:   'bg-red-600',
        info:    'bg-blue-600',
    };
    const toast = document.createElement('div');
    toast.className = `${colors[type]} text-white text-sm px-4 py-2 rounded-md shadow-lg animate-slide-down`;
    toast.textContent = message;
    document.getElementById('toast-container').appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/** Setea el botón en estado loading o normal. */
function setButtonLoading(button, isLoading) {
    button.disabled = isLoading;
    const label   = button.querySelector('[data-btn-label]');
    const loading = button.querySelector('[data-btn-loading]');
    if (label && loading) {
        label.classList.toggle('hidden', isLoading);
        loading.classList.toggle('hidden', !isLoading);
    }
}

/**
 * Aplica una "vista optimista" al item card mientras se hace la request:
 * lo difumina y deshabilita interacción visualmente.
 */
function applyOptimisticVisual(itemCard, on) {
    if (on) {
        itemCard.style.transition = 'opacity 0.15s';
        itemCard.style.opacity = '0.5';
        itemCard.style.pointerEvents = 'none';
    } else {
        itemCard.style.opacity = '';
        itemCard.style.pointerEvents = '';
    }
}

/**
 * Reemplaza un item card por nuevo HTML, manteniendo el espacio sin saltos.
 */
function replaceItemCard(oldCard, newHtml) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = newHtml.trim();
    const newCard = wrapper.firstElementChild;
    oldCard.replaceWith(newCard);
    // Re-bindear listeners para el card nuevo
    bindFormHandlers(newCard);
    return newCard;
}

/* ─────────── Asignación de cuenta ─────────── */
async function handleAssignSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;

    const confirmMsg = form.dataset.assignConfirm;
    if (confirmMsg && !confirm(confirmMsg)) return;

    const itemCard = form.closest('[data-item-card]');
    const button   = form.querySelector('button[type="submit"]');

    setButtonLoading(button, true);
    applyOptimisticVisual(itemCard, true);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || `Error ${response.status}`);
        }

        showToast(data.message || 'Cuenta asignada', 'success');
        replaceItemCard(itemCard, data.item_html);

    } catch (err) {
        applyOptimisticVisual(itemCard, false);
        setButtonLoading(button, false);
        showToast(err.message || 'Error al asignar', 'error');
    }
}

/* ─────────── Generar OC ─────────── */
async function handlePoSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    if (!confirm('¿Generar Orden de Compra para este ítem?')) return;

    const itemCard = form.closest('[data-item-card]');
    const button   = form.querySelector('button[type="submit"]');

    button.disabled = true;
    const oldLabel = button.textContent;
    button.textContent = 'Generando…';
    applyOptimisticVisual(itemCard, true);

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'No se pudo generar la OC');
        }

        showToast(`OC #${data.purchase_order_id} generada`, 'success');
        replaceItemCard(itemCard, data.item_html);

    } catch (err) {
        applyOptimisticVisual(itemCard, false);
        button.disabled = false;
        button.textContent = oldLabel;
        showToast(err.message || 'Error', 'error');
    }
}


/* ─────────── Enviar todos ─────────── */
async function handleAssignAllSubmit(e) {
    e.preventDefault();
    const form = e.currentTarget;
    if (!confirm('¿Enviar todos los productos pendientes de esta orden?')) return;

    const button = form.querySelector('button[type="submit"]');
    const oldLabel = button.textContent;
    button.disabled = true;
    button.textContent = 'Enviando…';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'No se pudieron enviar los productos');

        (data.cards || []).forEach(c => {
            const old = document.querySelector(`[data-item-card-id="${c.item_id}"]`);
            if (old) replaceItemCard(old, c.item_html);
        });

        showToast(data.message || 'Productos enviados', 'success');
        if (data.order_completed) showToast('¡Orden completada en Woo!', 'info');

        if (!data.failed || data.failed.length === 0) {
            form.remove(); // ya no quedan pendientes asignables
        } else {
            button.disabled = false;
            button.textContent = oldLabel;
        }
    } catch (err) {
        button.disabled = false;
        button.textContent = oldLabel;
        showToast(err.message || 'Error al enviar', 'error');
    }
}

/* ─────────── Binding ─────────── */
function bindFormHandlers(root = document) {
    root.querySelectorAll('form[data-assign-form]').forEach(f => {
        if (f.dataset.bound) return;
        f.dataset.bound = '1';
        f.addEventListener('submit', handleAssignSubmit);
    });
    root.querySelectorAll('form[data-assign-all-form]').forEach(f => {
        if (f.dataset.bound) return;
        f.dataset.bound = '1';
        f.addEventListener('submit', handleAssignAllSubmit);
    });
    root.querySelectorAll('form[data-po-form]').forEach(f => {
        if (f.dataset.bound) return;
        f.dataset.bound = '1';
        f.addEventListener('submit', handlePoSubmit);
    });
}

document.addEventListener('DOMContentLoaded', () => bindFormHandlers(document));

async function deleteReplacementItem(itemId) {
    if (!confirm('¿Eliminar este item y devolver los reemplazados a pendiente?')) return;

    try {
        const res = await fetch(`/order-items/${itemId}`, {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo eliminar');

        showToast(data.message || 'Item eliminado', 'success');
        location.reload();
    } catch (err) {
        showToast(err.message || 'Error al eliminar', 'error');
    }
}
</script>

{{-- Modal: elegir cuenta a resetear --}}
<div id="reset-pick-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div id="reset-pick-backdrop" class="absolute inset-0 bg-black/40"></div>

    <div class="relative w-full max-w-lg rounded-lg bg-white shadow-xl">
        <div class="border-b px-5 py-3">
            <h2 class="text-base font-semibold">Elegí la cuenta a resetear</h2>
            <p id="reset-pick-subtitle" class="text-xs text-zinc-500 mt-0.5"></p>
        </div>

        <div id="reset-pick-list" class="max-h-80 overflow-y-auto px-5 py-4 space-y-2">
            {{-- se llena por JS --}}
        </div>

        <div class="flex justify-end gap-2 border-t px-5 py-3">
            <button type="button" id="reset-pick-cancel"
                    class="rounded border px-3 py-1.5 text-sm text-zinc-600 hover:bg-zinc-100">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal     = document.getElementById('reset-pick-modal');
    const backdrop  = document.getElementById('reset-pick-backdrop');
    const cancel    = document.getElementById('reset-pick-cancel');
    const list      = document.getElementById('reset-pick-list');
    const subtitle  = document.getElementById('reset-pick-subtitle');
    if (!modal) return;

    let currentBtn = null;
    let busy = false;

    const close = () => { modal.classList.add('hidden'); currentBtn = null; };

    function ageLabel(acc) {
        if (acc.ageDays === null || acc.ageDays === undefined) return 'sin referencia';
        const months = Math.floor(acc.ageDays / 30);
        const src = acc.source ? ` · ${acc.source}` : '';
        return `${months} m${src}`;
    }

    function render(accounts) {
        list.innerHTML = '';
        accounts.forEach((acc) => {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-3 rounded-md border border-zinc-200 p-3 hover:bg-zinc-50';
            row.innerHTML = `
                <div class="min-w-0">
                    <div class="font-mono text-sm font-medium truncate">${acc.email}</div>
                    <div class="text-xs text-zinc-500 mt-0.5">
                        ${acc.platform}${acc.isDual ? ' (dual)' : ''}${acc.region ? ' · ' + acc.region : ''}
                        · ${acc.slots} cupos post-reset · ${ageLabel(acc)}
                    </div>
                </div>`;
            const pick = document.createElement('button');
            pick.type = 'button';
            pick.className = 'shrink-0 rounded-md bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600 disabled:opacity-60';
            pick.textContent = 'Elegir';
            pick.addEventListener('click', () => choose(acc.id, pick));
            row.appendChild(pick);
            list.appendChild(row);
        });
    }

    async function choose(accountId, pickBtn) {
        if (busy || !currentBtn) return;
        busy = true;
        pickBtn.disabled = true;
        pickBtn.textContent = '…';
        try {
            const res = await fetch(currentBtn.dataset.url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ account_id: accountId }),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                const card = document.querySelector('[data-item-card-id="' + currentBtn.dataset.itemId + '"]');
                if (card && data.item_html) card.outerHTML = data.item_html;
                close();
            } else {
                alert(data.message || 'No se pudo enviar a resetear.');
                pickBtn.disabled = false;
                pickBtn.textContent = 'Elegir';
            }
        } catch (err) {
            alert('Error de red.');
            pickBtn.disabled = false;
            pickBtn.textContent = 'Elegir';
        } finally {
            busy = false;
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-send-to-reset]');
        if (!btn) return;
        currentBtn = btn;
        let accounts = [];
        try { accounts = JSON.parse(btn.dataset.accounts || '[]'); } catch (_) {}
        if (!accounts.length) { alert('No hay cuentas reseteables para este ítem.'); return; }
        subtitle.textContent = btn.dataset.itemTitle || '';
        render(accounts);
        modal.classList.remove('hidden');
    });

    cancel.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-notify-game-change]');
    if (!btn) return;
    if (!confirm('¿Enviar el correo de cambio de juego al cliente?')) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = 'Enviando…';
    try {
        const res = await fetch(btn.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const data = await res.json();
        alert(data.message || (res.ok ? 'Notificación enviada.' : 'No se pudo enviar.'));
    } catch (err) {
        alert('Error de red.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});
</script>
<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-resend-delivery]');
    if (!btn) return;
    if (!confirm('¿Reenviar el correo con las credenciales y la llave al cliente?')) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Reenviando…';
    try {
        const res = await fetch(btn.dataset.url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        });
        const data = await res.json();
        showToast(data.message || (res.ok ? 'Correo reenviado.' : 'No se pudo reenviar.'), res.ok ? 'success' : 'error');
    } catch (err) {
        showToast('Error de red.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
});
</script>

{{-- Modal: región para Orden de Compra --}}
<div id="po-region-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
    <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
        <h3 class="text-base font-semibold text-zinc-900">Generar Orden de Compra</h3>
        <p id="po-region-subtitle" class="mt-1 text-xs text-zinc-500">—</p>

        <label class="mt-4 block text-xs font-medium text-zinc-600">Región de la OC</label>
        <input id="po-region-input" type="text" list="po-region-options" maxlength="50"
               placeholder="ej: US, EU, MX…"
               class="mt-1 w-full rounded-lg border-zinc-300 bg-zinc-50 px-3 py-2 text-sm
                      focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
        <datalist id="po-region-options">
            @foreach (($ocRegions ?? collect()) as $r)
                <option value="{{ $r }}"></option>
            @endforeach
        </datalist>
        
        <label class="mt-4 block text-xs font-medium text-zinc-600">Notas de la región</label>
        <textarea id="po-notes-region-input" rows="3" maxlength="2000" placeholder="Notas internas de la región…" class="mt-1 w-full rounded-lg border-zinc-300 bg-zinc-50 px-3 py-2 text-sm focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900"></textarea>
        <p id="po-region-error" class="mt-1 hidden text-xs text-red-600"></p>

        <div class="mt-5 flex justify-end gap-2">
            <button type="button" id="po-region-cancel"
                    class="rounded-lg border border-zinc-200 px-3 py-2 text-sm text-zinc-600 hover:bg-zinc-100">
                Cancelar
            </button>
            <button type="button" id="po-region-confirm"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700
                           disabled:opacity-60 disabled:cursor-wait">
                <span data-confirm-label>Generar OC</span>
                <span data-confirm-loading class="hidden">⏳ Generando…</span>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF     = '{{ csrf_token() }}';
    const modal    = document.getElementById('po-region-modal');
    if (!modal) return;
    const input    = document.getElementById('po-region-input');
    const notesEl  = document.getElementById('po-notes-region-input');
    const subtitle = document.getElementById('po-region-subtitle');
    const errEl    = document.getElementById('po-region-error');
    const confirm  = document.getElementById('po-region-confirm');
    const cancel   = document.getElementById('po-region-cancel');

    let currentUrl = null;

    const setLoading = (on) => {
        confirm.disabled = on;
        confirm.querySelector('[data-confirm-label]').classList.toggle('hidden', on);
        confirm.querySelector('[data-confirm-loading]').classList.toggle('hidden', !on);
    };
    const showError = (msg) => { errEl.textContent = msg; errEl.classList.remove('hidden'); };

    function open(url, title) {
        currentUrl = url;
        subtitle.textContent = title || '';
        input.value = '';
        notesEl.value = '';
        errEl.classList.add('hidden');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        setTimeout(() => input.focus(), 50);
    }
    function close() {
        modal.classList.add('hidden'); modal.classList.remove('flex');
        currentUrl = null; setLoading(false);
    }

    // Abrir desde cualquier botón "Generar OC"
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-generate-po]');
        if (btn) open(btn.dataset.url, btn.dataset.itemTitle);
    });

    cancel.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') confirm.click(); });

    confirm.addEventListener('click', async () => {
        const region = input.value.trim();
        if (!region) { showError('Indicá una región.'); input.focus(); return; }
        if (!currentUrl) return;

        setLoading(true);
        errEl.classList.add('hidden');
        try {
            const res = await fetch(currentUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ region, notes_region: notesEl.value.trim() || null, }),
            });
            const data = await res.json();

            if (res.ok && data.success) {
                if (data.item_html) {
                    const tmp = document.createElement('div');
                    tmp.innerHTML = data.item_html.trim();
                    const fresh  = tmp.firstElementChild;
                    const old    = document.querySelector(`[data-item-card-id="${fresh?.dataset.itemCardId}"]`);
                    if (old && fresh) old.replaceWith(fresh);
                }
                close();
            } else {
                showError(data.message || 'No se pudo generar la OC.');
                setLoading(false);
            }
        } catch (err) {
            showError('Error de red. Reintentá.');
            setLoading(false);
        }
    });
})();
</script>

{{-- Modal: elegir cuentas de PACK (cualquier cuenta con cupo secundario) --}}
<div id="pack-pick-modal" class="fixed inset-0 z-50 hidden">
    <div id="pack-pick-backdrop" class="absolute inset-0 bg-zinc-900/50 backdrop-blur-sm"></div>

    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col"
             onclick="event.stopPropagation()">

            <div class="px-6 py-4 border-b border-zinc-200 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold">Cuentas para el pack</h3>
                    <p id="pack-pick-subtitle" class="text-xs text-zinc-500 mt-0.5 truncate"></p>
                </div>
                <button type="button" id="pack-pick-x" class="text-zinc-400 hover:text-zinc-900 text-2xl leading-none">×</button>
            </div>

            <div class="px-6 py-3 border-b border-zinc-100">
                <input type="text" id="pack-pick-search" placeholder="Buscar por email, gamer tag, región o producto…" class="w-full rounded-lg border-zinc-300 bg-zinc-50 py-2 pl-2 pr-3 text-sm font-mono transition focus:border-zinc-900 focus:bg-white focus:ring-1 focus:ring-zinc-900">
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div id="pack-pick-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
                <div id="pack-pick-empty"   class="hidden text-center py-12 text-sm text-zinc-500">No se encontraron cuentas.</div>
                <div id="pack-pick-loading" class="text-center py-12 text-sm text-zinc-500">Buscando cuentas…</div>
            </div>

            {{-- Paginación --}}
            <div class="px-6 py-3 border-t border-zinc-200 flex items-center justify-between text-sm">
                <div id="pack-pick-count" class="text-zinc-500"></div>
                <div class="flex items-center gap-2">
                    <button type="button" id="pack-pick-prev"
                            class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">← Anterior</button>
                    <span id="pack-pick-page" class="font-mono text-xs text-zinc-600 min-w-[60px] text-center"></span>
                    <button type="button" id="pack-pick-next"
                            class="px-3 py-1 rounded border border-zinc-200 text-zinc-700 hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed">Siguiente →</button>
                </div>
            </div>

            {{-- Acciones --}}
            <div class="px-6 py-3 border-t border-zinc-200 flex items-center justify-between gap-4 bg-zinc-50/60">
                <div id="pack-pick-selected" class="text-sm text-zinc-500 italic min-w-0 truncate">Ninguna cuenta seleccionada</div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="pack-pick-cancel"
                            class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-inset ring-zinc-300 hover:bg-zinc-100">
                        Cancelar
                    </button>
                    <button type="button" id="pack-pick-confirm" disabled
                            class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed">
                        Asignar seleccionadas
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('pack-pick-modal');
    if (!modal) return;

    const grid       = document.getElementById('pack-pick-grid');
    const empty      = document.getElementById('pack-pick-empty');
    const loading    = document.getElementById('pack-pick-loading');
    const searchEl   = document.getElementById('pack-pick-search');
    const subtitle   = document.getElementById('pack-pick-subtitle');
    const selLabel   = document.getElementById('pack-pick-selected');
    const confirmBtn = document.getElementById('pack-pick-confirm');
    const cancel     = document.getElementById('pack-pick-cancel');
    const xBtn       = document.getElementById('pack-pick-x');
    const backdrop   = document.getElementById('pack-pick-backdrop');
    const countEl    = document.getElementById('pack-pick-count');
    const pageEl     = document.getElementById('pack-pick-page');
    const prevBtn    = document.getElementById('pack-pick-prev');
    const nextBtn    = document.getElementById('pack-pick-next');

    const csrf = () => document.querySelector('meta[name="csrf-token"]').content;
    const esc  = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    const state = {
        currentBtn: null,
        mode: 'multi',         // 'multi' (genérico) | 'slot' (single-select por juego)
        single: false,
        onPick: null,          // callback(acc) para modo slot (staging, sin POST)
        confirmLabel: 'Asignar seleccionadas',
        page: 1, lastPage: 1,
        search: '', debounce: null,
        selected: new Map(),   // id -> account (persiste entre páginas)
        busy: false,
    };

    function close() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        state.currentBtn = null;
        state.mode = 'multi';
        state.single = false;
        state.onPick = null;
        state.selected.clear();
        state.busy = false;
        confirmBtn.textContent = state.confirmLabel = 'Asignar seleccionadas';
    }

    function updateFooter() {
        const n = state.selected.size;
        confirmBtn.disabled = n === 0 || state.busy;
        if (n === 0) {
            selLabel.textContent = state.single ? 'Ninguna cuenta elegida' : 'Ninguna cuenta seleccionada';
            selLabel.classList.add('italic', 'text-zinc-500');
            selLabel.classList.remove('text-zinc-900', 'font-medium');
        } else {
            const names = Array.from(state.selected.values()).map(a => a.email).join(', ');
            selLabel.textContent = state.single ? names : `${n} seleccionada${n === 1 ? '' : 's'}: ${names}`;
            selLabel.classList.remove('italic', 'text-zinc-500');
            selLabel.classList.add('text-zinc-900', 'font-medium');
        }
    }

    function markCard(card, on) {
        card.classList.toggle('ring-2', on);
        card.classList.toggle('ring-indigo-500', on);
        card.classList.toggle('border-indigo-500', on);
        card.querySelector('[data-check]')?.classList.toggle('hidden', !on);
    }

    function toggle(acc, card) {
        if (acc.free <= 0) return;   // sin cupo → no seleccionable
        const wasOn = state.selected.has(acc.id);

        // En modo slot es selección única: limpiamos cualquier otra elegida.
        if (state.single && !wasOn) {
            state.selected.clear();
            grid.querySelectorAll('.pack-acc-card').forEach(c => markCard(c, false));
        }

        wasOn ? state.selected.delete(acc.id) : state.selected.set(acc.id, acc);
        markCard(card, state.selected.has(acc.id));
        updateFooter();
    }

    function render(list) {
        grid.innerHTML = '';
        if (!list.length) { empty.classList.remove('hidden'); return; }
        empty.classList.add('hidden');

        list.forEach(acc => {
            const on     = state.selected.has(acc.id);
            const noCupo = acc.free <= 0;
            const card = document.createElement('div');
            card.className = 'pack-acc-card relative rounded-md border border-zinc-200 p-3 transition '
                + (noCupo ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:border-zinc-400')
                + (on ? ' ring-2 ring-indigo-500 border-indigo-500' : '');

            const cover = acc.image_url
                ? `<img src="${esc(acc.image_url)}" alt="" class="w-12 h-16 object-cover rounded shrink-0 bg-zinc-100" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-12 h-16 rounded bg-zinc-100 shrink-0'}))">`
                : `<div class="w-12 h-16 rounded bg-zinc-100 shrink-0"></div>`;

            const slotBadges = (acc.slots || [])
                .map(s => `<span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600">${esc(s)}</span>`)
                .join(' ');

            const subtitle = acc.product || acc.game;

            card.innerHTML = `
                <div data-check class="${on ? '' : 'hidden'} absolute top-2 right-2 text-indigo-600 font-bold">✓</div>
                <div class="flex gap-3">
                    ${cover}
                    <div class="min-w-0 flex-1">
                        <div class="font-mono text-sm font-medium truncate pr-5">${esc(acc.email)}</div>
                        <div class="text-xs text-zinc-500 mt-0.5">
                            ${esc(acc.platform)}${acc.is_dual ? ' (dual)' : ''}${acc.region ? ' · ' + esc(acc.region) : ''}
                        </div>
                        ${subtitle ? `<div class="text-xs text-zinc-600 truncate mt-0.5" title="${esc(subtitle)}">${esc(subtitle)}</div>` : ''}
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            ${slotBadges || '<span class="text-[10px] text-amber-600">sin cupo secundario</span>'}
                        </div>
                    </div>
                </div>`;

            if (!noCupo) card.addEventListener('click', () => toggle(acc, card));
            grid.appendChild(card);
        });
    }

    async function loadPage() {
        grid.innerHTML = '';
        empty.classList.add('hidden');
        loading.classList.remove('hidden');

        const params = new URLSearchParams({ page: state.page, search: state.search });
        try {
            const res  = await fetch(`${state.candidatesUrl}?${params}`, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();
            loading.classList.add('hidden');
            state.lastPage = json.meta.last_page;

            render(json.data || []);

            countEl.textContent  = `${json.meta.total} cuentas`;
            pageEl.textContent   = `${json.meta.current_page} / ${json.meta.last_page}`;
            prevBtn.disabled     = state.page <= 1;
            nextBtn.disabled     = state.page >= state.lastPage;
        } catch (_) {
            loading.classList.add('hidden');
            empty.textContent = 'Error al cargar cuentas. Reintentá.';
            empty.classList.remove('hidden');
        }
    }

    function changePage(delta) {
        const p = state.page + delta;
        if (p < 1 || p > state.lastPage) return;
        state.page = p;
        loadPage();
    }

    function openCommon({ candidatesUrl, assignUrl, title, search, single, confirmLabel, onPick }) {
        state.page = 1;
        state.search = search || '';
        state.selected.clear();
        state.candidatesUrl = candidatesUrl;
        state.assignUrl = assignUrl || null;
        state.single = !!single;
        state.mode = single ? 'slot' : 'multi';
        state.onPick = onPick || null;
        state.confirmLabel = confirmLabel || (single ? 'Elegir esta cuenta' : 'Asignar seleccionadas');
        confirmBtn.textContent = state.confirmLabel;
        searchEl.value = state.search;
        subtitle.textContent = title || '';
        updateFooter();
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        loadPage();
        setTimeout(() => searchEl.focus(), 50);
    }

    // Modo genérico (packs sin desglose): multi-select + POST directo.
    function open(btn) {
        state.currentBtn = btn;
        openCommon({
            candidatesUrl: btn.dataset.candidatesUrl,
            assignUrl:     btn.dataset.assignUrl,
            title:         btn.dataset.itemTitle || '',
            single:        false,
        });
    }

    async function submit() {
        if (state.busy || state.selected.size === 0) return;

        // Modo slot: no hay POST, sólo devolvemos la cuenta elegida al llamador (staging).
        if (state.mode === 'slot') {
            const acc = Array.from(state.selected.values())[0];
            const cb = state.onPick;
            close();
            if (cb) cb(acc);
            return;
        }

        if (!state.currentBtn) return;
        state.busy = true; confirmBtn.disabled = true;
        const oldLabel = confirmBtn.textContent;
        confirmBtn.textContent = 'Asignando…';
        try {
            const res = await fetch(state.assignUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ account_ids: Array.from(state.selected.keys()) }),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                const card = document.querySelector('[data-item-card-id="' + state.currentBtn.dataset.itemId + '"]');
                if (card && data.item_html) replaceItemCard(card, data.item_html);
                showToast(data.message || 'Cuentas de pack asignadas', 'success');
                if (data.failed && data.failed.length) showToast(`${data.failed.length} cuenta(s) sin slot libre`, 'info');
                if (data.order_completed) showToast('¡Orden completada en Woo!', 'info');
                close();
            } else {
                showToast(data.message || 'No se pudo asignar.', 'error');
                state.busy = false; confirmBtn.disabled = false; confirmBtn.textContent = oldLabel;
            }
        } catch (_) {
            showToast('Error de red.', 'error');
            state.busy = false; confirmBtn.disabled = false; confirmBtn.textContent = oldLabel;
        }
    }

    searchEl.addEventListener('input', () => {
        state.search = searchEl.value.trim();
        state.page = 1;
        clearTimeout(state.debounce);
        state.debounce = setTimeout(loadPage, 250);
    });
    prevBtn.addEventListener('click', () => changePage(-1));
    nextBtn.addEventListener('click', () => changePage(1));
    confirmBtn.addEventListener('click', submit);
    cancel.addEventListener('click', close);
    xBtn.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-pack-assign]');
        if (btn) open(btn);
    });

    // API para el staging por juego (ver script de slots más abajo).
    window.PackPicker = {
        openForSlot(opts) {
            state.currentBtn = null;
            openCommon({
                candidatesUrl: opts.candidatesUrl,
                title:         opts.title || '',
                search:        opts.search || '',
                single:        true,
                confirmLabel:  'Elegir esta cuenta',
                onPick:        opts.onPick,
            });
        },
    };
})();
</script>

{{-- ════════ PACK: staging de cuentas por juego (isPack con packGames) ════════ --}}
<script>
(function () {
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // Render de la cuenta staged dentro del slot.
    function renderSlotAccount(slot) {
        const box = slot.querySelector('[data-slot-account]');
        const acc = slot._account;
        if (!acc) {
            box.innerHTML = '<span class="text-xs text-zinc-400 italic">sin cuenta</span>';
            return;
        }
        const meta = [acc.platform, acc.region, acc.product].filter(Boolean).map(esc).join(' · ');
        box.innerHTML = `
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="text-emerald-600 shrink-0">✓</span>
                <div class="min-w-0">
                    <div class="font-mono text-xs font-medium truncate">${esc(acc.email)}</div>
                    ${meta ? `<div class="text-[11px] text-zinc-500 truncate">${meta}</div>` : ''}
                </div>
            </div>`;
    }

    function updateCard(card) {
        const slots  = Array.from(card.querySelectorAll('[data-pack-slot]'));
        const staged = slots.filter(s => s._account).length;
        const total  = slots.length;

        const progress = card.querySelector('[data-pack-progress]');
        if (progress) progress.textContent = `${staged}/${total} juego${total === 1 ? '' : 's'} con cuenta`;

        const deliver = card.querySelector('[data-pack-deliver]');
        if (deliver) deliver.disabled = staged < total || total === 0;
    }

    function stageSlot(slot, acc) {
        const card = slot.closest('[data-pack-slots]');
        // Evitar la misma cuenta en dos juegos (consume un solo slot secundario).
        if (acc) {
            const dupe = Array.from(card.querySelectorAll('[data-pack-slot]'))
                .some(s => s !== slot && s._account && s._account.id === acc.id);
            if (dupe) {
                showToast('Esa cuenta ya está elegida para otro juego del pack.', 'error');
                return;
            }
        }
        slot._account = acc || null;
        renderSlotAccount(slot);
        updateCard(card);
    }

    // Inicializa los slots de una tarjeta desde data-suggestion (sugerencia del server).
    function initCard(card) {
        card.querySelectorAll('[data-pack-slot]').forEach(slot => {
            let sug = null;
            try { sug = JSON.parse(slot.dataset.suggestion || 'null'); } catch (_) {}
            // No usar stageSlot acá para no disparar el aviso de duplicados en la carga inicial.
            slot._account = sug || null;
            renderSlotAccount(slot);
        });
        updateCard(card);
    }

    async function deliver(card) {
        const btn = card.querySelector('[data-pack-deliver]');
        if (!btn || btn.disabled) return;
        const slots = Array.from(card.querySelectorAll('[data-pack-slot]'));

        const selections = slots
            .filter(s => s._account)
            .map(s => ({
                account_id:      s._account.id,
                pack_game_id:    s.dataset.packGameId ? Number(s.dataset.packGameId) : null,
                pack_game_title: s.dataset.packGameTitle || null,
            }));

        if (!selections.length) return;

        btn.disabled = true;
        btn.querySelector('[data-btn-label]')?.classList.add('hidden');
        btn.querySelector('[data-btn-loading]')?.classList.remove('hidden');

        try {
            const res = await fetch(card.dataset.assignUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ selections }),
            });
            const data = await res.json();
            if (res.ok && data.success) {
                const itemCard = document.querySelector('[data-item-card-id="' + card.dataset.itemId + '"]');
                if (itemCard && data.item_html) replaceItemCard(itemCard, data.item_html);
                showToast(data.message || 'Pack entregado', 'success');
                if (data.failed && data.failed.length) showToast(`${data.failed.length} cuenta(s) sin slot libre`, 'info');
                if (data.order_completed) showToast('¡Orden completada en Woo!', 'info');
            } else {
                showToast(data.message || 'No se pudo entregar el pack.', 'error');
                btn.disabled = false;
                btn.querySelector('[data-btn-label]')?.classList.remove('hidden');
                btn.querySelector('[data-btn-loading]')?.classList.add('hidden');
            }
        } catch (_) {
            showToast('Error de red.', 'error');
            btn.disabled = false;
            btn.querySelector('[data-btn-label]')?.classList.remove('hidden');
            btn.querySelector('[data-btn-loading]')?.classList.add('hidden');
        }
    }

    // Delegación: "Cambiar cuenta" abre el picker en modo slot (staging).
    document.addEventListener('click', (e) => {
        const change = e.target.closest('[data-pack-slot-change]');
        if (change) {
            const slot = change.closest('[data-pack-slot]');
            const card = slot.closest('[data-pack-slots]');
            window.PackPicker.openForSlot({
                candidatesUrl: card.dataset.candidatesUrl,
                title:         `Cuenta para: ${slot.dataset.packGameTitle || ''}`,
                search:        slot.dataset.packGameTitle || '',
                onPick:        (acc) => stageSlot(slot, acc),
            });
            return;
        }

        const deliverBtn = e.target.closest('[data-pack-deliver]');
        if (deliverBtn) deliver(deliverBtn.closest('[data-pack-slots]'));
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-pack-slots]').forEach(initCard);
    });
})();
</script>

{{-- ════════ HEARTBEAT: presencia + control exclusivo de esta orden ════════ --}}
<script>
(function () {
    const url      = '{{ route('orders.heartbeat', $order) }}';
    const listUrl  = '{{ route('orders.index') }}';
    const csrf     = document.querySelector('meta[name="csrf-token"]').content;
    const interval = {{ \App\Services\OrderPresence::INTERVAL }} * 1000;

    const banner   = document.getElementById('presence-banner');
    const textEl   = document.getElementById('presence-text');

    const modal      = document.getElementById('control-modal');
    const modalTitle = document.getElementById('control-modal-title');
    const modalText  = document.getElementById('control-modal-text');
    const takeBtn    = document.getElementById('control-modal-take');
    if (!banner || !modal) return;

    let timer      = null;
    let hadControl = false;   // ¿tuve el control en el latido anterior? (para distinguir "me lo quitaron")
    let takeNext   = false;   // pedir el control en el próximo latido

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => (
        {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // Banner: otros que están viendo la orden mientras YO tengo el control.
    function renderBanner(viewers) {
        if (!viewers || viewers.length === 0) {
            banner.classList.add('hidden');
            textEl.textContent = '';
            return;
        }
        const names = viewers.map(v => esc(v.name)).join(', ');
        const verb  = viewers.length === 1 ? 'está' : 'están';
        textEl.innerHTML = `<strong>${names}</strong> ${verb} viendo esta orden ahora mismo.`;
        banner.classList.remove('hidden');
    }

    function showModal(controller, bumped) {
        const name = esc(controller?.name || 'Otro usuario');
        modalTitle.textContent = bumped ? 'Perdiste el control de esta orden' : 'Hay alguien más en esta orden';
        modalText.innerHTML = bumped
            ? `<strong>${name}</strong> tomó el control de esta orden. Para seguir operando, recuperá el control.`
            : `<strong>${name}</strong> ya está trabajando en esta orden. Podés tomar el control (lo perderá) o volver al listado.`;
        takeBtn.textContent = bumped ? 'Recuperar el control' : 'Tomar el control';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function hideModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function apply(data) {
        if (data.has_control) {
            hideModal();
            renderBanner(data.viewers);
        } else {
            // Otro manda: si yo tenía el control, me lo quitaron ("bumped").
            banner.classList.add('hidden');
            showModal(data.controller, hadControl);
        }
        hadControl = !!data.has_control;
    }

    async function beat() {
        const take = takeNext;
        takeNext = false;
        try {
            const res = await fetch(url + (take ? '?take=1' : ''), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) return;
            apply(await res.json());
        } catch (_) { /* red caída: reintentamos en el próximo latido */ }
    }

    // "Tomar / Recuperar el control": arrebata en un latido inmediato.
    takeBtn.addEventListener('click', () => {
        takeNext = true;
        beat();
    });

    function leave() {
        // sendBeacon sobrevive al cierre de la pestaña; va por FormData con _token.
        const fd = new FormData();
        fd.append('_token', csrf);
        fd.append('leave', '1');
        navigator.sendBeacon(url, fd);
    }

    function start() {
        if (timer) return;
        beat();
        timer = setInterval(beat, interval);
    }
    function stop() {
        clearInterval(timer);
        timer = null;
    }

    start();

    // Pausamos al ocultar la pestaña; el server nos poda solo tras la ventana activa.
    document.addEventListener('visibilitychange', () => {
        document.hidden ? stop() : start();
    });

    window.addEventListener('pagehide', leave);
})();
</script>
@endsection
