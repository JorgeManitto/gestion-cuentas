@extends('layouts.app')
@section('title', 'Order #' . $order->wc_order_id)

@section('content')

<div class="mb-4">
    <a href="{{ route('orders.index') }}" class="text-sm text-zinc-500 hover:text-zinc-900">← Volver al listado</a>
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
            @php $wcColor = $order->wcStatusColor(); @endphp
            <span class="inline-flex items-center gap-1.5 rounded-full bg-{{ $wcColor }}-50 px-2 py-0.5 text-xs font-medium text-{{ $wcColor }}-700 ring-1 ring-inset ring-{{ $wcColor }}-600/20">
                <span class="h-1.5 w-1.5 rounded-full bg-{{ $wcColor }}-500"></span>
                {{ $order->wc_status }}
            </span>
        </div>
    </div>
</div>

<h2 class="text-lg font-semibold mb-3">Items ({{ $order->items->count() }})</h2>

<div class="space-y-4">
    @foreach ($order->items as $item)
        @include('orders.partials._item-card', [
            'item'   => $item,
            'result' => $candidatesByItem[$item->id] ?? null,
        ])
    @endforeach
</div>

<script>
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

/* ─────────── Binding ─────────── */
function bindFormHandlers(root = document) {
    root.querySelectorAll('form[data-assign-form]').forEach(f => {
        if (f.dataset.bound) return;
        f.dataset.bound = '1';
        f.addEventListener('submit', handleAssignSubmit);
    });
    root.querySelectorAll('form[data-po-form]').forEach(f => {
        if (f.dataset.bound) return;
        f.dataset.bound = '1';
        f.addEventListener('submit', handlePoSubmit);
    });
}

document.addEventListener('DOMContentLoaded', () => bindFormHandlers(document));
</script>

@endsection
