<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialEntry extends Model
{
    public const INCOME = 'income';

    public const EXPENSE = 'expense';

    public const RENTAL_CATEGORY = 'Locação';

    public const CATEGORIES = [
        self::INCOME => [self::RENTAL_CATEGORY, 'Cobrança de avaria', 'Outras receitas'],
        self::EXPENSE => ['Manutenção', 'Combustível e transporte', 'Folha de pagamento', 'Aluguel e contas', 'Compra de equipamento', 'Impostos', 'Outras despesas'],
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'float', 'due_date' => 'date:Y-m-d', 'paid_at' => 'date:Y-m-d'];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function status(): string
    {
        if ($this->paid_at !== null) {
            return 'paid';
        }

        return $this->due_date->lt(today()) ? 'overdue' : 'pending';
    }

    public function signedAmount(): float
    {
        return $this->type === self::INCOME ? $this->amount : -$this->amount;
    }
}
