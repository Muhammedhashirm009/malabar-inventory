<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - {{ $sale->invoice_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 11px;
            color: #000;
            background: #fff;
            line-height: 1.4;
        }
        
        /* Container layout that fits exactly onto A4 */
        .invoice-container {
            width: 100%;
            min-height: 267mm; /* Total A4 height (297mm) minus top/bottom margins (30mm) */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2.5px solid #000;
        }
        .shop-name {
            font-size: 24px;
            font-weight: 800;
            color: #000;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .shop-details {
            font-size: 11px;
            color: #000;
            line-height: 1.4;
        }
        .invoice-title-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 16px;
        }
        .invoice-title {
            font-size: 15px;
            font-weight: 800;
            color: #000;
            letter-spacing: 0.5px;
        }
        .invoice-meta {
            text-align: right;
            font-size: 11px;
            color: #000;
        }
        .invoice-meta div {
            margin-bottom: 3px;
        }
        .invoice-meta strong {
            color: #000;
        }
        .billing-section {
            margin-bottom: 18px;
            font-size: 11px;
            color: #000;
        }
        .billing-title {
            font-weight: bold;
            color: #000;
            margin-bottom: 4px;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        .customer-name {
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        .customer-details {
            color: #000;
            margin-top: 2px;
        }

        /* Fully Bordered (Marin) Table - solid black lines for print visibility */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            border: 1.5px solid #000;
        }
        .items-table th {
            background-color: #f7fafc;
            border: 1px solid #000;
            padding: 9px 10px;
            font-weight: bold;
            text-align: left;
            color: #000;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.5px;
        }
        .items-table td {
            border: 1px solid #000;
            padding: 9px 10px;
            color: #000;
            vertical-align: middle;
        }
        .items-table tr:nth-child(even) td {
            background-color: #fcfcfc;
        }
        .items-table .num-col {
            text-align: right;
        }
        .items-table .center-col {
            text-align: center;
        }

        /* Bottom Wrapper containing Summary & Footer */
        .invoice-bottom-wrapper {
            margin-top: auto;
        }

        .summary-section {
            display: flex;
            justify-content: space-between;
            border-top: 2.5px solid #000;
            padding-top: 15px;
            margin-bottom: 25px;
            font-size: 11px;
        }
        .payment-info {
            width: 50%;
            color: #000;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .amount-words {
            margin-top: 10px;
            font-size: 11px;
            color: #000;
            font-weight: bold;
            line-height: 1.4;
        }
        .amount-words strong {
            color: #000;
        }
        .totals-table {
            width: 45%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 5px 0;
            color: #000;
        }
        .totals-table .val-col {
            text-align: right;
            color: #000;
            font-weight: 500;
        }
        .totals-table .discount-val {
            color: #000;
            font-weight: 600;
        }
        .totals-table .grand-total-row td {
            border-top: 1.5px solid #000;
            padding-top: 8px;
            font-size: 14px;
            font-weight: bold;
            color: #000;
        }
        .totals-table .grand-total-row .val-col {
            color: #000;
            font-size: 14px;
        }
        
        .footer-layout {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-top: 1.5px solid #000;
            padding-top: 15px;
        }
        .footer-note {
            font-size: 11px;
            color: #000;
            font-weight: bold;
            font-style: italic;
        }
        .signature-block {
            text-align: center;
            width: 200px;
        }
        .signature-line {
            border-top: 2px solid #000;
            margin-bottom: 4px;
        }
        .signature-label {
            font-size: 10px;
            color: #000;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Screen Preview Controls */
        @media screen {
            body {
                background: #f0f4f8;
                padding: 30px 0;
            }
            .control-bar {
                background: #1a202c;
                padding: 10px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                color: #fff;
                font-family: system-ui, -apple-system, sans-serif;
                font-size: 13px;
                width: 210mm;
                margin: 0 auto 15px auto;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                border-radius: 6px;
            }
            .control-bar button {
                background: #3182ce;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-weight: 600;
                font-size: 12px;
                transition: background 0.2s;
            }
            .control-bar button:hover {
                background: #2b6cb0;
            }
            .control-bar .close-btn {
                background: transparent;
                color: #a0aec0;
                border: 1px solid #4a5568;
                margin-right: 8px;
            }
            .control-bar .close-btn:hover {
                background: rgba(255,255,255,0.05);
                color: #fff;
            }
            .invoice-container {
                background: #fff;
                width: 210mm;
                padding: 15mm;
                margin: 0 auto;
                box-shadow: 0 10px 25px rgba(0,0,0,0.08);
                border-radius: 6px;
            }
        }

        @media print {
            body {
                background: #fff;
            }
            .control-bar {
                display: none !important;
            }
            .invoice-container {
                width: 100%;
                padding: 0;
            }
            /* Force all text elements and borders to pure black for maximum print clarity */
            body, div, p, span, strong, td, th, table, tr, label {
                color: #000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .items-table, .items-table th, .items-table td, .header, .summary-section, .signature-line, .footer-layout {
                border-color: #000 !important;
            }
        }
    </style>
</head>
<body>
    @php
    if (!function_exists('amountToWords')) {
        function amountToWords($number) {
            if ($number == 0) {
                return 'Zero Rupees Only';
            }
            $decimal = round($number - ($no = floor($number)), 2) * 100;
            
            $words = array(
                0 => '', 1 => 'One', 2 => 'Two',
                3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
                7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
                10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve',
                13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
                16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
                19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty',
                40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty',
                70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
            );
            $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
            
            $str = array();
            $digits_length = strlen($no);
            $i = 0;
            
            while ($i < $digits_length) {
                $divider = ($i == 2) ? 10 : 100;
                $num = floor($no % $divider);
                $no = floor($no / $divider);
                $i += ($divider == 10) ? 1 : 2;
                
                if ($num) {
                    $counter = count($str);
                    $hundred = ($counter == 1 && $str[0]) ? 'and' : null;
                    
                    if ($num < 21) {
                        $str[] = $words[$num] . ' ' . $digits[$counter] . ' ' . $hundred;
                    } else {
                        $tens_val = floor($num / 10) * 10;
                        $ones_val = $num % 10;
                        $str[] = $words[$tens_val] . ($ones_val ? ' ' . $words[$ones_val] : '') . ' ' . $digits[$counter] . ' ' . $hundred;
                    }
                } else {
                    $str[] = null;
                }
            }
            
            // Clean up spacing
            $Rupees = implode(' ', array_reverse(array_filter($str)));
            $Rupees = preg_replace('/\s+/', ' ', trim($Rupees));
            
            $paise = '';
            if ($decimal > 0) {
                if ($decimal < 21) {
                    $paise = $words[$decimal];
                } else {
                    $tens_val = floor($decimal / 10) * 10;
                    $ones_val = $decimal % 10;
                    $paise = $words[$tens_val] . ($ones_val ? ' ' . $words[$ones_val] : '');
                }
                $paise = ' and ' . trim($paise) . ' Paise';
            }
            
            return ($Rupees ? trim($Rupees) . ' Rupees' : '') . ($paise ? ' ' . trim($paise) : '') . ' Only';
        }
    }
    @endphp

    <div class="control-bar">
        <span>Invoice Preview (A4 Portrait)</span>
        <div>
            <button class="close-btn" onclick="if(window.__TAURI__){ history.back(); } else { window.close(); }">Close Window</button>
            <button onclick="window.print()">Print Invoice</button>
        </div>
    </div>
    
    <div class="invoice-container">
        <!-- Top Section: Header, Invoice details, Customer details, Items Table -->
        <div>
            <div class="header">
                <div class="shop-name">{{ config('settings.shop_name', 'Malabar Inventory') }}</div>
                <div class="shop-details">
                    {{ config('settings.shop_address', 'Main Road, Malabar, Kerala') }}<br>
                    @if(config('settings.shop_phone'))Phone: {{ config('settings.shop_phone') }}@endif
                    @if(config('settings.shop_email')) | Email: {{ config('settings.shop_email') }}@endif
                    @if(config('settings.shop_gstin')) | GSTIN: {{ config('settings.shop_gstin') }}@endif
                </div>
            </div>
            
            <div class="invoice-title-row">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <div>Invoice No: <strong>{{ $sale->invoice_number }}</strong></div>
                    <div>Date: <strong>{{ $sale->sale_date->format('d/m/Y') }}</strong></div>
                </div>
            </div>
            
            <div class="billing-section">
                <div class="billing-title">Bill To:</div>
                <div class="customer-name">{{ $sale->customer->name }}</div>
                @if($sale->customer->phone)
                    <div class="customer-details">Phone: {{ $sale->customer->phone }}</div>
                @endif
            </div>
            
            <!-- Bordered (Marin) Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 40%;">Item Description</th>
                        <th style="width: 15%;">Type</th>
                        <th class="num-col" style="width: 8%;">Qty</th>
                        <th class="num-col" style="width: 10%;">Rate</th>
                        <th class="num-col" style="width: 10%;">Discount</th>
                        <th class="num-col" style="width: 12%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $subtotal = 0;
                        $totalDiscount = 0;
                    @endphp
                    @foreach($sale->items as $idx => $item)
                        @php
                            $itemSubtotal = $item->sale_rate * $item->quantity;
                            $subtotal += $itemSubtotal;
                            $totalDiscount += ($item->discount ?? 0) * $item->quantity;
                        @endphp
                        <tr>
                            <td>{{ $idx + 1 }}</td>
                            <td><strong>{{ $item->product->name }}</strong></td>
                            <td>{{ $item->product->category ?? '-' }}</td>
                            <td class="num-col">{{ (float)$item->quantity }}</td>
                            <td class="num-col">₹{{ number_format($item->sale_rate, 2) }}</td>
                            <td class="num-col">
                                @if(($item->discount ?? 0) > 0)
                                    ₹{{ number_format($item->discount, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num-col">₹{{ number_format(($item->sale_rate - ($item->discount ?? 0)) * $item->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Bottom Section: Summary Block & Footer layout -->
        <div class="invoice-bottom-wrapper">
            <!-- Summary Section moved to bottom -->
            <div class="summary-section">
                <div class="payment-info">
                    <div>Payment Mode: <strong>Ledger Account (On Account)</strong></div>
                    @if($sale->notes)
                        <div style="margin-top: 5px; font-size: 9px; color: #000; line-height: 1.3;">
                            <strong>Notes:</strong> {{ $sale->notes }}
                        </div>
                    @endif
                    <div class="amount-words">
                        <strong>Amount in words:</strong><br>
                        {{ amountToWords($sale->total_amount) }}
                    </div>
                </div>
                
                <table class="totals-table">
                    <tr>
                        <td>Subtotal:</td>
                        <td class="val-col">₹{{ number_format($subtotal, 2) }}</td>
                    </tr>
                    @if($totalDiscount > 0)
                        <tr>
                            <td>Discount:</td>
                            <td class="val-col discount-val">-₹{{ number_format($totalDiscount, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total-row">
                        <td>GRAND TOTAL:</td>
                        <td class="val-col">₹{{ number_format($sale->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>

            <!-- Footer Notes & Signature Block -->
            <div class="footer-layout">
                <div class="footer-note">
                    Thank you for your business!
                </div>
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div class="signature-label">Authorized Signature</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                window.print();
            }, 500);
        });
    </script>
</body>
</html>
