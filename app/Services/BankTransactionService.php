<?php

namespace App\Services;

use App\Core\Auth;
use PDO;
use RuntimeException;

class BankTransactionService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public static function loadActiveAccounts(PDO $db): array
    {
        return $db->query(
            'SELECT id, account_name, bank_name, account_number, current_balance
             FROM bank_accounts WHERE is_active = 1 ORDER BY account_name'
        )->fetchAll();
    }

    public function credit(
        int $bankAccountId,
        float $amount,
        string $referenceType,
        ?int $referenceId,
        string $description,
        ?int $userId = null,
        ?string $transactionDate = null
    ): int {
        return $this->post($bankAccountId, 'credit', $amount, $referenceType, $referenceId, $description, $userId, $transactionDate);
    }

    public function debit(
        int $bankAccountId,
        float $amount,
        string $referenceType,
        ?int $referenceId,
        string $description,
        ?int $userId = null,
        ?string $transactionDate = null
    ): int {
        return $this->post($bankAccountId, 'debit', $amount, $referenceType, $referenceId, $description, $userId, $transactionDate);
    }

    /** @return array{0:int,1:int} bank_account_id, affect_bank flag */
    public static function resolveBankLink(array $data): array
    {
        $affect = !empty($data['affect_bank']) || !empty($data['affect_bank_balance']);
        $bankId = (int)($data['bank_account_id'] ?? 0);
        if (!$affect) {
            return [0, 0];
        }
        if ($bankId <= 0) {
            throw new RuntimeException('Select a bank account when recording a bank transaction.');
        }
        return [$bankId, 1];
    }

    /** @return array{0:string,1:float,2:float} status, paid, due */
    public static function resolvePoPaymentAmounts(array $data, float $totalAmount): array
    {
        $status = strtolower(trim((string)($data['payment_status'] ?? 'unpaid')));
        if (!in_array($status, ['unpaid', 'full', 'partial'], true)) {
            $status = 'unpaid';
        }
        if ($status === 'full') {
            return ['full', round($totalAmount, 2), 0.0];
        }
        if ($status === 'unpaid') {
            return ['unpaid', 0.0, round($totalAmount, 2)];
        }
        $paid = max(0, (float)($data['amount_paid'] ?? 0));
        if ($paid <= 0) {
            throw new RuntimeException('Enter how much was paid for a partial payment.');
        }
        if ($paid >= $totalAmount) {
            throw new RuntimeException('Partial payment must be less than the PO total. Choose Full paid if the full amount was paid.');
        }
        return ['partial', round($paid, 2), round($totalAmount - $paid, 2)];
    }

    public function postIfLinked(
        array $data,
        float $amount,
        string $direction,
        string $referenceType,
        int $referenceId,
        string $description,
        ?string $transactionDate = null
    ): void {
        [$bankId, $affect] = self::resolveBankLink($data);
        if (!$affect || $amount <= 0) {
            return;
        }
        $userId = Auth::id() ?: null;
        if ($direction === 'credit') {
            $this->credit($bankId, $amount, $referenceType, $referenceId, $description, $userId, $transactionDate);
            return;
        }
        $this->debit($bankId, $amount, $referenceType, $referenceId, $description, $userId, $transactionDate);
    }

    /** @return list<array<string, mixed>> */
    public function listForAccount(int $bankAccountId, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT bt.*, u.first_name, u.last_name
             FROM bank_transactions bt
             JOIN users u ON u.id = bt.created_by
             WHERE bt.bank_account_id = ?
             ORDER BY bt.transaction_date DESC, bt.id DESC
             LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute([$bankAccountId]);
        return $stmt->fetchAll();
    }

    private function post(
        int $bankAccountId,
        string $type,
        float $amount,
        string $referenceType,
        ?int $referenceId,
        string $description,
        ?int $userId,
        ?string $transactionDate
    ): int {
        if (!in_array($type, ['credit', 'debit'], true)) {
            throw new RuntimeException('Invalid transaction type.');
        }
        $allowedRefs = ['manual', 'opening_balance', 'sell_order', 'purchase_order', 'adjustment'];
        if (!in_array($referenceType, $allowedRefs, true)) {
            throw new RuntimeException('Invalid transaction reference.');
        }

        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Transaction amount must be greater than zero.');
        }

        $description = trim($description);
        if ($description === '') {
            throw new RuntimeException('Transaction description is required.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, account_name, current_balance, account_type, is_active FROM bank_accounts WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$bankAccountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            throw new RuntimeException('Bank account not found.');
        }
        if (!(int)$account['is_active']) {
            throw new RuntimeException('Bank account is inactive.');
        }

        $balance = round((float)$account['current_balance'], 2);
        if ($type === 'credit') {
            $balanceAfter = round($balance + $amount, 2);
        } else {
            if ($account['account_type'] !== 'overdraft' && $balance < $amount) {
                throw new RuntimeException(
                    'Insufficient balance in ' . ($account['account_name'] ?? 'account') . ' (available ' . number_format($balance, 2) . ').'
                );
            }
            $balanceAfter = round($balance - $amount, 2);
        }

        $this->db->prepare('UPDATE bank_accounts SET current_balance = ? WHERE id = ?')
            ->execute([$balanceAfter, $bankAccountId]);

        $this->db->prepare(
            'INSERT INTO bank_transactions (
                bank_account_id, transaction_type, amount, balance_after,
                reference_type, reference_id, description, transaction_date, created_by
             ) VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $bankAccountId,
            $type,
            $amount,
            $balanceAfter,
            $referenceType,
            $referenceId,
            $description,
            $transactionDate ?: date('Y-m-d'),
            $userId ?? Auth::id(),
        ]);

        return (int)$this->db->lastInsertId();
    }
}
