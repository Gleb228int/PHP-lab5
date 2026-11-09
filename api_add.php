<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    require_once 'lib/db.php';
    require_once 'classes/Transaction.php';
    require_once 'classes/RecurringExpense.php';
    require_once 'classes/Wallet.php';
    require_once 'lib/functions.php';

    $amount = trim($_POST['amount'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $date = trim($_POST['date'] ?? '');
    $type = trim($_POST['type'] ?? 'standard');
    $frequency = trim($_POST['frequency'] ?? 'Щомісячно');

    if ($amount === '' || !is_numeric($amount) || floatval($amount) <= 0 || empty($category) || empty($date)) {
        http_response_code(422);
        echo json_encode(['error' => 'Некоректні дані у формі. Перевірте поля.']);
        exit;
    }

    $wallet = new Wallet($pdo);

    if ($type === 'recurring') {
        $newTx = new RecurringExpense(floatval($amount), $category, $date, $frequency);
    } else {
        $newTx = new Transaction(floatval($amount), $category, $date);
    }

    $newId = $wallet->addTransaction($newTx, $type, $type === 'recurring' ? $frequency : null);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $newId,
        'total' => formatMoney($wallet->totalBalance())
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}