<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'lib/db.php';
    require_once 'classes/Transaction.php';
    require_once 'classes/RecurringExpense.php';
    require_once 'classes/Wallet.php';
    require_once 'lib/functions.php';

    $wallet = new Wallet($pdo);
    $q = $_GET['q'] ?? null;

    $transactions = $wallet->getTransactions($q);
    $data = [];

    foreach ($transactions as $tx) {
        $data[] = [
            'id' => $tx->getId(),
            'info' => $tx->getInfo(),
            'date' => $tx->getDate(),
            'month' => monthName($tx->getDate()),
            'amount' => $tx->getAmount(),
            'is_large' => $tx->getAmount() > 1000
        ];
    }

    echo json_encode([
        'transactions' => $data,
        'total' => formatMoney($wallet->totalBalance()),
        'utilities' => formatMoney($wallet->totalByCategory('Комунальні послуги'))
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}