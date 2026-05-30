@extends('layouts.app')
@section('title', 'Receive Payment')

@push('styles')
<style>
.success-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 17, 23, 0.85);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.success-card {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 3rem 2rem;
    width: 90%;
    max-width: 440px;
    text-align: center;
    box-shadow: var(--shadow-xl), var(--glow-success);
    animation: successCardIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}
@keyframes successCardIn {
    0% {
        opacity: 0;
        transform: scale(0.8) translateY(20px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
.ripple-wrapper {
    position: relative;
    width: 120px;
    height: 120px;
    margin: 0 auto 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ripple {
    position: absolute;
    width: 100px;
    height: 100px;
    border: 3px solid var(--success);
    border-radius: 50%;
    opacity: 0;
    transform: scale(0.8);
    animation: rippleEffect 2s cubic-bezier(0.1, 0.8, 0.3, 1) infinite;
}
.ripple:nth-child(2) {
    animation-delay: 0.6s;
}
.ripple:nth-child(3) {
    animation-delay: 1.2s;
}
@keyframes rippleEffect {
    0% {
        transform: scale(0.8);
        opacity: 0.8;
    }
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}
.checkmark-svg {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    display: block;
    stroke-width: 4;
    stroke: #fff;
    stroke-miterlimit: 10;
    z-index: 2;
}
.checkmark-circle {
    stroke-dasharray: 166;
    stroke-dashoffset: 166;
    stroke-width: 4;
    stroke-miterlimit: 10;
    stroke: var(--success);
    fill: var(--success);
    animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
}
.checkmark-check {
    transform-origin: 50% 50%;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
    animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
}
@keyframes stroke {
    100% {
        stroke-dashoffset: 0;
    }
}
.success-title {
    color: var(--success);
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    letter-spacing: -0.02em;
}
.success-amount {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 2rem;
    letter-spacing: -0.03em;
}
.success-details {
    background: var(--bg-hover);
    border-radius: var(--radius-md);
    padding: 1.25rem;
    border: 1px solid var(--border);
    text-align: left;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.detail-row:last-child {
    border-bottom: none;
}
.detail-label {
    color: var(--text-muted);
    font-size: 0.9rem;
}
.detail-value {
    color: var(--text-primary);
    font-size: 0.95rem;
    font-weight: 600;
}
.badge-success {
    background: var(--success-bg);
    color: var(--success);
    padding: 0.25rem 0.6rem;
    border-radius: var(--radius-sm);
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.redirect-notice {
    font-size: 0.85rem;
    margin-top: 1.5rem;
    color: var(--text-muted);
}
</style>
@endpush

@section('content')
<div x-data="paymentForm({{ $selectedCustomer ? json_encode(['id' => $selectedCustomer->id, 'name' => $selectedCustomer->name, 'phone' => $selectedCustomer->phone, 'current_balance' => (float)$selectedCustomer->current_balance]) : 'null' }})">
<div class="mb-3"><a href="{{ route('payments.index') }}" class="btn btn-outline"><i data-lucide="arrow-left"></i> Back</a></div>
<div class="card" style="max-width:600px; overflow: visible;">
    <div class="card-header"><h3>Record Payment</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('payments.store') }}" @submit.prevent="submitForm($event.target)">
            @csrf
            <div class="form-group mb-3" style="position:relative;">
                <label class="form-label">Customer *</label>
                <input type="text" class="form-control" placeholder="Search customer by name or phone..." x-model="query" @input="search()" @focus="showDrop = results.length > 0" @click.outside="showDrop = false">
                <input type="hidden" name="customer_id" :value="selected ? selected.id : ''">
                <div class="search-dropdown" x-show="showDrop" x-transition>
                    <template x-for="c in results" :key="c.id">
                        <div class="search-dropdown-item" @click="pick(c)">
                            <strong x-text="c.name"></strong> <span class="text-muted" x-text="c.phone || ''"></span>
                            <div class="text-muted" style="font-size:0.85rem;">Balance: ₹<span x-text="parseFloat(c.current_balance).toFixed(2)"></span></div>
                        </div>
                    </template>
                </div>
                <div x-show="selected" class="mt-2 p-2" style="background:var(--bg-hover); border-radius:8px;">
                    <strong x-text="selected?.name"></strong>
                    <div>Outstanding: <strong class="text-danger">₹<span x-text="selected ? parseFloat(selected.current_balance).toFixed(2) : '0.00'"></span></strong></div>
                </div>
                <template x-if="errors.customer_id">
                    <span class="form-error" x-text="errors.customer_id[0]" style="margin-top: 5px; display: block;"></span>
                </template>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Amount (₹) *</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
                @error('amount')<span class="form-error">{{ $message }}</span>@enderror
                <template x-if="errors.amount">
                    <span class="form-error" x-text="errors.amount[0]" style="margin-top: 5px; display: block;"></span>
                </template>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Payment Date *</label>
                <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                <template x-if="errors.payment_date">
                    <span class="form-error" x-text="errors.payment_date[0]" style="margin-top: 5px; display: block;"></span>
                </template>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Optional"></textarea>
            </div>
            <button type="submit" class="btn btn-primary" :disabled="!selected || isSubmitting">
                <i x-show="!isSubmitting" data-lucide="credit-card"></i>
                <span x-show="isSubmitting" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true" style="display:inline-block; width: 1rem; height: 1rem; border: 0.2em solid currentColor; border-right-color: transparent; border-radius: 50%; vertical-align: text-bottom; margin-right: 0.25rem; animation: spin 0.75s linear infinite;"></span>
                <span x-text="isSubmitting ? 'Recording...' : 'Record Payment'"></span>
            </button>
        </form>
    </div>
</div>

<!-- SUCCESS OVERLAY FOR GPAY/PAYTM STYLE ANIMATION -->
<div x-show="showSuccess" class="success-overlay" style="display: none;" x-transition>
    <div class="success-card">
        <div class="ripple-wrapper">
            <div class="ripple"></div>
            <div class="ripple"></div>
            <div class="ripple"></div>
            <svg class="checkmark-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        
        <h2 class="success-title">Payment Received!</h2>
        <div class="success-amount">₹<span x-text="formattedAmount"></span></div>
        
        <div class="success-details">
            <div class="detail-row">
                <span class="detail-label">Customer</span>
                <strong class="detail-value" x-text="selected ? selected.name : ''"></strong>
            </div>
            <div class="detail-row" x-show="notes.trim().length > 0">
                <span class="detail-label">Notes</span>
                <span class="detail-value text-muted" x-text="notes"></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="badge badge-success"><i data-lucide="check-circle-2" style="width: 14px; height: 14px;"></i> Completed</span>
            </div>
        </div>
        
        <p class="redirect-notice">
            Redirecting to payments list...
        </p>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
function paymentForm(initialCustomer = null) {
    return {
        query: initialCustomer ? initialCustomer.name : '',
        results: [],
        showDrop: false,
        selected: initialCustomer,
        timeout: null,
        
        // Success feedback and AJAX states
        showSuccess: false,
        isSubmitting: false,
        errors: {},
        formattedAmount: '0.00',
        notes: '',
        
        search() {
            this.selected = null;
            clearTimeout(this.timeout);
            if (this.query.length < 2) { this.results = []; this.showDrop = false; return; }
            this.timeout = setTimeout(() => {
                fetch('/api/customers/search?q=' + encodeURIComponent(this.query))
                    .then(r => r.json()).then(d => { this.results = d; this.showDrop = true; });
            }, 300);
        },
        pick(c) { this.selected = c; this.query = c.name; this.showDrop = false; },
        
        submitForm(form) {
            if (this.isSubmitting) return;
            this.isSubmitting = true;
            this.errors = {};
            this.showSuccess = false;
            
            try {
                const formData = new FormData(form);
                
                // Safe formatting of amount
                const amt = parseFloat(formData.get('amount'));
                this.formattedAmount = isNaN(amt) ? '0.00' : amt.toFixed(2);
                this.notes = formData.get('notes') || '';
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                
                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken || ''
                    },
                    body: formData
                })
                .then(async r => {
                    try {
                        const contentType = r.headers.get('content-type');
                        const isJson = contentType && contentType.includes('application/json');
                        let data = null;
                        
                        if (isJson) {
                            data = await r.json();
                        }
                        
                        if (!r.ok) {
                            if (r.status === 422 && data && data.errors) {
                                this.errors = data.errors;
                            } else {
                                alert((data && data.message) ? data.message : 'An error occurred (Status ' + r.status + ').');
                            }
                            this.isSubmitting = false;
                        } else {
                            if (data && data.success) {
                                this.showSuccess = true;
                                if (window.lucide) {
                                    setTimeout(() => window.lucide.createIcons(), 50);
                                }
                                setTimeout(() => {
                                    window.location.href = data.redirect || '{{ route('payments.index') }}';
                                }, 3000);
                            } else if (!isJson && r.url && r.url.includes('/payments')) {
                                // Fallback: if server redirected us to payments index (HTML response)
                                this.showSuccess = true;
                                if (window.lucide) {
                                    setTimeout(() => window.lucide.createIcons(), 50);
                                }
                                setTimeout(() => {
                                    window.location.href = '{{ route('payments.index') }}';
                                }, 3000);
                            } else {
                                alert((data && data.message) ? data.message : 'Something went wrong. Please try again.');
                                this.isSubmitting = false;
                            }
                        }
                    } catch (innerErr) {
                        console.error('Error in response processing:', innerErr);
                        alert('An unexpected error occurred while processing response.');
                        this.isSubmitting = false;
                    }
                })
                .catch(err => {
                    console.error('Fetch network error:', err);
                    alert('A network error occurred. Please try again.');
                    this.isSubmitting = false;
                });
            } catch (err) {
                console.error('Synchronous error in submitForm:', err);
                alert('An error occurred before sending the request.');
                this.isSubmitting = false;
            }
        }
    }
}
</script>
@endpush
