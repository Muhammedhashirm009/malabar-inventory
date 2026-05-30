<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payments = \Illuminate\Support\Facades\DB::table('customer_ledger')
    ->where('reference_type', 'payment')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "PAYMENTS COUNT: " . count($payments) . "\n";
foreach ($payments as $p) {
    echo "ID: {$p->id}, Customer ID: {$p->customer_id}, Amount: {$p->amount}, Type: {$p->type}, Date: {$p->transaction_date}\n";
}
