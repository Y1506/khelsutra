<?php

namespace App\Services\Operations\Gateways;

class NullFinanceExpenseGateway implements FinanceExpenseGateway
{
    public function recordExpense(int $orgId, float $amount, string $description, array $options = []): int
    {
        // Null Object Pattern - do nothing and return a dummy ID
        return 0;
    }

    public function voidExpense(int $expenseId, int $orgId): void
    {
        // Null Object Pattern - do nothing
    }
}
