@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<div class="card card-static mb-3" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>Shop & Invoice Settings</h3>
    </div>
    <div class="card-body">
        <form action="{{ route('settings.update') }}" method="POST">
            @csrf
            
            <!-- Section 1: Shop Identity -->
            <div style="margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
                <h4 style="margin-bottom: 14px; color: var(--accent-light); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="store" style="width: 18px; height: 18px;"></i>
                    Shop Identity & Contact Info
                </h4>
                
                <div class="form-group">
                    <label for="shop_name">Shop Name *</label>
                    <input type="text" id="shop_name" name="shop_name" class="form-control" value="{{ old('shop_name', config('settings.shop_name')) }}" required>
                </div>
                
                <div class="form-group">
                    <label for="shop_address">Shop Address</label>
                    <textarea id="shop_address" name="shop_address" class="form-control" rows="3">{{ old('shop_address', config('settings.shop_address')) }}</textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shop_phone">Phone Number</label>
                        <input type="text" id="shop_phone" name="shop_phone" class="form-control" value="{{ old('shop_phone', config('settings.shop_phone')) }}">
                    </div>
                    <div class="form-group">
                        <label for="shop_email">Email Address</label>
                        <input type="email" id="shop_email" name="shop_email" class="form-control" value="{{ old('shop_email', config('settings.shop_email')) }}">
                    </div>
                </div>
                
                <div class="form-group" style="max-width: 50%;">
                    <label for="shop_gstin">GSTIN (Business ID / Tax ID)</label>
                    <input type="text" id="shop_gstin" name="shop_gstin" class="form-control" value="{{ old('shop_gstin', config('settings.shop_gstin')) }}" placeholder="e.g. 32AAAAA1111A1Z1">
                </div>
            </div>

            <!-- Section 2: Invoice Configurations -->
            <div style="margin-bottom: 24px;">
                <h4 style="margin-bottom: 14px; color: var(--accent-light); display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="file-text" style="width: 18px; height: 18px;"></i>
                    Invoice Number Formats
                </h4>
                
                <div class="form-row" style="margin-bottom: 14px;">
                    <div class="form-group">
                        <label for="sale_invoice_prefix">Sales Invoice Prefix *</label>
                        <input type="text" id="sale_invoice_prefix" name="sale_invoice_prefix" class="form-control" value="{{ old('sale_invoice_prefix', config('settings.sale_invoice_prefix')) }}" required>
                        <small class="form-hint">E.g., SAL (generates SAL-YYYYMMDD-001)</small>
                    </div>
                    <div class="form-group">
                        <label for="sale_invoice_suffix">Sales Invoice Suffix</label>
                        <input type="text" id="sale_invoice_suffix" name="sale_invoice_suffix" class="form-control" value="{{ old('sale_invoice_suffix', config('settings.sale_invoice_suffix')) }}">
                        <small class="form-hint">Optional suffix at end (e.g., -LTD)</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="purchase_invoice_prefix">Purchases Invoice Prefix *</label>
                        <input type="text" id="purchase_invoice_prefix" name="purchase_invoice_prefix" class="form-control" value="{{ old('purchase_invoice_prefix', config('settings.purchase_invoice_prefix')) }}" required>
                        <small class="form-hint">E.g., PUR (generates PUR-YYYYMMDD-001)</small>
                    </div>
                    <div class="form-group">
                        <label for="purchase_invoice_suffix">Purchases Invoice Suffix</label>
                        <input type="text" id="purchase_invoice_suffix" name="purchase_invoice_suffix" class="form-control" value="{{ old('purchase_invoice_suffix', config('settings.purchase_invoice_suffix')) }}">
                        <small class="form-hint">Optional suffix at end (e.g., -LTD)</small>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i data-lucide="save"></i>
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card card-static mb-3" id="desktop-backup-card" style="max-width: 800px; margin: 24px auto 0 auto; display: none;">
    <div class="card-header">
        <h3 style="display: flex; align-items: center; gap: 8px;">
            <i data-lucide="database" style="width: 20px; height: 20px; color: var(--accent-light);"></i>
            Database Backup
        </h3>
    </div>
    <div class="card-body">
        <!-- Manual Backup Section -->
        <div style="margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
            <h4 style="margin-bottom: 8px; color: var(--text);">Manual Backup</h4>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 14px; line-height: 1.5;">
                Export a copy of the database (`database.sqlite`) right now to a selected folder or removable storage device.
            </p>
            <button type="button" id="btn-backup-db" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                <i data-lucide="folder-up" style="width: 16px; height: 16px;"></i>
                Create Manual Backup
            </button>
            <div id="backup-status" style="width: 100%; padding: 10px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; display: none; margin-top: 10px; border: 1px solid transparent; line-height: 1.4;"></div>
        </div>

        <!-- Automatic Backup Section -->
        <div>
            <h4 style="margin-bottom: 8px; color: var(--text);">Automatic Backup on Exit</h4>
            <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 14px; line-height: 1.5;">
                Configure a target folder (e.g., a flash drive or backup folder). The system will automatically save a timestamped copy of the database to this folder every time you exit the application.
            </p>
            <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-start; width: 100%;">
                <div style="display: flex; gap: 8px;">
                    <button type="button" id="btn-set-auto-backup" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i data-lucide="folder-plus" style="width: 16px; height: 16px;"></i>
                        Set Auto Backup Directory
                    </button>
                    <button type="button" id="btn-clear-auto-backup" class="btn btn-outline" style="display: none; align-items: center; gap: 8px; border-color: rgba(239, 68, 68, 0.4); color: #ef4444;">
                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                        Disable Auto Backup
                    </button>
                </div>
                <div id="auto-backup-path-container" style="width: 100%; padding: 10px 12px; border-radius: 6px; font-size: 13px; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border); line-height: 1.4;">
                    <span style="color: var(--text-muted);">Status:</span> <span id="auto-backup-status-text" style="color: #ef4444; font-weight: 500;">Not Configured (Auto Backup Disabled)</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (window.__TAURI__) {
            const backupCard = document.getElementById('desktop-backup-card');
            if (backupCard) {
                backupCard.style.display = 'block';
            }

            // 1. Manual Backup Logic
            const backupBtn = document.getElementById('btn-backup-db');
            const backupStatus = document.getElementById('backup-status');

            if (backupBtn && backupStatus) {
                backupBtn.addEventListener('click', async () => {
                    backupBtn.disabled = true;
                    backupStatus.style.display = 'block';
                    backupStatus.style.backgroundColor = 'rgba(255, 255, 255, 0.03)';
                    backupStatus.style.borderColor = 'var(--border)';
                    backupStatus.style.color = 'var(--text-muted)';
                    backupStatus.innerHTML = '<span style="display: inline-flex; align-items: center; gap: 8px;"><i data-lucide="loader" class="animate-spin" style="width: 16px; height: 16px;"></i> Opening folder selection...</span>';
                    
                    if (window.lucide) {
                        window.lucide.createIcons();
                        if (!document.getElementById('backup-spinner-style')) {
                            const styleEl = document.createElement('style');
                            styleEl.id = 'backup-spinner-style';
                            styleEl.innerHTML = `
                                @keyframes spin {
                                    from { transform: rotate(0deg); }
                                    to { transform: rotate(360deg); }
                                }
                                .animate-spin {
                                    animation: spin 1s linear infinite;
                                }
                            `;
                            document.head.appendChild(styleEl);
                        }
                    }

                    try {
                        const response = await window.__TAURI__.core.invoke('backup_database');
                        backupStatus.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
                        backupStatus.style.borderColor = 'rgba(16, 185, 129, 0.2)';
                        backupStatus.style.color = '#10b981';
                        backupStatus.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 8px;"><i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> ${response}</span>`;
                    } catch (err) {
                        backupStatus.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                        backupStatus.style.borderColor = 'rgba(239, 68, 68, 0.2)';
                        backupStatus.style.color = '#ef4444';
                        backupStatus.innerHTML = `<span style="display: inline-flex; align-items: center; gap: 8px;"><i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i> ${err}</span>`;
                    } finally {
                        backupBtn.disabled = false;
                        if (window.lucide) window.lucide.createIcons();
                    }
                });
            }

            // 2. Auto Backup Logic
            const setAutoBtn = document.getElementById('btn-set-auto-backup');
            const clearAutoBtn = document.getElementById('btn-clear-auto-backup');
            const autoStatusText = document.getElementById('auto-backup-status-text');

            async function updateAutoBackupUI() {
                try {
                    const currentPath = await window.__TAURI__.core.invoke('get_backup_directory');
                    if (currentPath && currentPath.trim() !== '') {
                        autoStatusText.innerHTML = `Active (Backs up to: <code style="background: rgba(255,255,255,0.08); padding: 2px 6px; border-radius: 4px; color: var(--accent-light, #a855f7); font-family: monospace;">${currentPath}</code>)`;
                        autoStatusText.style.color = '#10b981';
                        clearAutoBtn.style.display = 'inline-flex';
                    } else {
                        autoStatusText.innerHTML = 'Not Configured (Auto Backup Disabled)';
                        autoStatusText.style.color = '#ef4444';
                        clearAutoBtn.style.display = 'none';
                    }
                } catch (e) {
                    console.error('Failed to get auto backup path', e);
                }
                if (window.lucide) window.lucide.createIcons();
            }

            // Initial load check
            updateAutoBackupUI();

            if (setAutoBtn) {
                setAutoBtn.addEventListener('click', async () => {
                    setAutoBtn.disabled = true;
                    try {
                        const path = await window.__TAURI__.core.invoke('set_backup_directory');
                        updateAutoBackupUI();
                    } catch (err) {
                        // user cancelled or error
                        if (err !== 'No folder selected') {
                            alert('Error: ' + err);
                        }
                    } finally {
                        setAutoBtn.disabled = false;
                    }
                });
            }

            if (clearAutoBtn) {
                clearAutoBtn.addEventListener('click', async () => {
                    if (confirm('Are you sure you want to disable automatic backups on exit?')) {
                        clearAutoBtn.disabled = true;
                        try {
                            await window.__TAURI__.core.invoke('clear_backup_directory');
                            updateAutoBackupUI();
                        } catch (err) {
                            alert('Error: ' + err);
                        } finally {
                            clearAutoBtn.disabled = false;
                        }
                    }
                });
            }
        }
    });
</script>
@endpush
@endsection
