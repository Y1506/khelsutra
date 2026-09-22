<?php

namespace App\Services\Operations;

use App\Services\Operations\Interfaces\ExpenseRecorderInterface;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class ExpenseRecorder implements ExpenseRecorderInterface
{
    /**
     * Record an expense in the finance system.
     *
     * @param int $orgId Organization ID
     * @param float $amount Expense amount
     * @param string $description Expense description
     * @param array $options Optional parameters (vendor_id, event_id, created_by)
     * @return int Created expense ID
     */
    public function recordExpense(int $orgId, float $amount, string $description, array $options = []): int
    {
        return DB::transaction(function () use ($orgId, $amount, $description, $options) {
            // Member 5's tables/models: expenses, finance_categories
            // Find a relevant category
            $category = DB::table('finance_categories')
                ->where('organization_id', $orgId)
                ->where('status', 'active')
                ->where('name', 'like', '%Operations%')
                ->first();
            
            $categoryId = $category ? $category->id : null;

            // Generate expense reference
            $count = DB::table('expenses')->where('organization_id', $orgId)->count() + 1;
            $reference = 'EXP-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $expenseId = DB::table('expenses')->insertGetId([
                'organization_id' => $orgId,
                'expense_reference' => $reference,
                'finance_category_id' => $categoryId,
                'vendor_id' => $options['vendor_id'] ?? null,
                'event_id' => $options['event_id'] ?? null,
                'expense_date' => date('Y-m-d'),
                'description' => $description,
                'amount' => $amount,
                'tax_amount' => 0,
                'total_amount' => $amount,
                'payment_status' => 'pending',
                'created_by' => $options['created_by'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            return $expenseId;
        });
    }
}
