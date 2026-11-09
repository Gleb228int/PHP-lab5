<?php
require_once 'lib/db.php';
require_once 'classes/Wallet.php';
require_once 'lib/functions.php';

$wallet = new Wallet($pdo);
$totalAmount = $wallet->totalBalance();
$utilitiesTotal = $wallet->totalByCategory('Комунальні послуги');
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Практична робота №5 — AJAX та JSON</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Трекер особистих витрат (AJAX / JSON)</h1>

        <div id="alertBox"></div>

        <div class="form-card">
            <h2>Додати нову транзакцію (без перезавантаження)</h2>

            <form id="transactionForm" method="post">
                <div class="form-group">
                    <label for="type">Тип витрати (Клас):</label>
                    <select id="type" name="type">
                        <option value="standard">Звичайна витрата (Transaction)</option>
                        <option value="recurring">Регулярна витрата (RecurringExpense)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="amount">Сума (грн):</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                </div>

                <div class="form-group">
                    <label for="category">Категорія:</label>
                    <select id="category" name="category" required>
                        <option value="">-- Оберіть категорію --</option>
                        <option value="Продукти">Продукти</option>
                        <option value="Техніка">Техніка</option>
                        <option value="Транспорт">Транспорт</option>
                        <option value="Одяг">Одяг</option>
                        <option value="Розваги">Розваги</option>
                        <option value="Комунальні послуги">Комунальні послуги</option>
                        <option value="Інше">Інше</option>
                    </select>
                </div>

                <div class="form-group" id="frequencyGroup" style="display: none;">
                    <label for="frequency">Регулярність:</label>
                    <input type="text" id="frequency" name="frequency" value="Щомісячно" placeholder="Наприклад: Щомісячно">
                </div>

                <div class="form-group">
                    <label for="date">Дата:</label>
                    <input type="date" id="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <button type="submit" class="btn">Додати витрату</button>
            </form>
        </div>

        <div style="margin-top: 30px;">
            <h2>Список транзакцій з бази даних</h2>
            <div class="form-group" style="margin-top: 15px;">
                <input type="text" id="search" placeholder="Живий пошук за категорією або датою...">
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>№</th>
                    <th>Опис об'єкта (getInfo)</th>
                    <th>Місяць</th>
                    <th>Статус</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody id="resultsTableBody">
                <!-- Дані завантажуються динамічно через AJAX -->
            </tbody>
        </table>

        <div class="summary">
            <p>Витрати на комунальні послуги: <strong id="utilitiesSummary"><?= formatMoney($utilitiesTotal) ?></strong></p>
            <h3>Загальна сума витрат: <span id="totalSummary"><?= formatMoney($totalAmount) ?></span></h3>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeSelect = document.getElementById('type');
            const frequencyGroup = document.getElementById('frequencyGroup');
            const transactionForm = document.getElementById('transactionForm');
            const resultsTableBody = document.getElementById('resultsTableBody');
            const searchInput = document.getElementById('search');
            const totalSummary = document.getElementById('totalSummary');
            const utilitiesSummary = document.getElementById('utilitiesSummary');
            const alertBox = document.getElementById('alertBox');

            typeSelect.addEventListener('change', () => {
                frequencyGroup.style.display = typeSelect.value === 'recurring' ? 'block' : 'none';
            });

            async function loadTransactions(query = '') {
                try {
                    const url = query ? `api_list.php?q=${encodeURIComponent(query)}` : 'api_list.php';
                    const response = await fetch(url);
                    
                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error("Помилка парсингу JSON від сервера: " + text.substring(0, 100));
                    }

                    if (!response.ok) throw new Error(data.error || 'Помилка завантаження даних');
                    
                    renderTable(data.transactions);
                    totalSummary.textContent = data.total;
                    utilitiesSummary.textContent = data.utilities;
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            }

            function renderTable(transactions) {
                resultsTableBody.innerHTML = '';
                if (!transactions || transactions.length === 0) {
                    resultsTableBody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Транзакцій не знайдено.</td></tr>';
                    return;
                }

                transactions.forEach((tx, index) => {
                    const badgeClass = tx.is_large ? 'badge large' : 'badge normal';
                    const badgeText = tx.is_large ? 'Велика витрата' : 'Звичайна';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${escapeHtml(tx.info)}</td>
                        <td>${tx.month}</td>
                        <td><span class="${badgeClass}">${badgeText}</span></td>
                        <td>
                            <a href="edit.php?id=${tx.id}" class="action-btn edit">Редагувати</a>
                            <a href="delete.php?id=${tx.id}" class="action-btn delete" onclick="return confirm('Ви впевнені, що хочете видалити цей запис?');">Видалити</a>
                        </td>
                    `;
                    resultsTableBody.appendChild(tr);
                });
            }

            function escapeHtml(text) {
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return text.replace(/[&<>"']/g, m => map[m]);
            }

            function showAlert(message, type = 'success') {
                alertBox.className = type === 'success' ? 'alert success' : 'alert error';
                alertBox.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
                alertBox.style.color = type === 'success' ? '#155724' : '#721c24';
                alertBox.style.padding = '12px 15px';
                alertBox.style.borderRadius = '4px';
                alertBox.style.marginBottom = '20px';
                alertBox.textContent = message;
                setTimeout(() => { alertBox.textContent = ''; alertBox.style.padding = '0'; }, 4000);
            }

            searchInput.addEventListener('input', (e) => {
                loadTransactions(e.target.value.trim());
            });

            transactionForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(transactionForm);

                try {
                    const response = await fetch('api_add.php', {
                        method: 'POST',
                        body: formData
                    });

                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (e) {
                        throw new Error("Помилка відповіді сервера: " + text.substring(0, 100));
                    }

                    if (!response.ok) {
                        throw new Error(result.error || 'Помилка збереження');
                    }

                    if (result.success) {
                        showAlert('Транзакцію успішно додано через AJAX!');
                        transactionForm.reset();
                        frequencyGroup.style.display = 'none';
                        loadTransactions();
                    }
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            });

            loadTransactions();
        });
    </script>
</body>
</html>