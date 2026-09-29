<?php

namespace App\Services\Operations\Gateways;

interface FinanceExpenseGateway
{
    public function recordExpense(int $orgId, float $amount, string $description, array $options = []): int;
    public function voidExpense(int $expenseId, int $orgId): void;
}
