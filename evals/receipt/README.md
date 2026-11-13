# Receipt eval

Photos of receipts used by `php artisan loqfy:eval receipt`.

Put each photo in this folder and describe it in `cases.json` with what a person reads from it:

```json
{
  "reference_date": "2026-11-12",
  "cases": [
    {
      "id": "posto-diesel",
      "photo": "posto-diesel.jpg",
      "total": 450.00,
      "date": "2026-11-10",
      "category": "Combustível e transporte",
      "note": "Cupom de posto, com subtotal e desconto"
    }
  ]
}
```

`category` must be one of the expense categories in `App\Models\FinancialEntry`. Hard cases are worth
including: a crumpled coupon, a handwritten receipt, a photo taken at an angle, a note with several totals.
