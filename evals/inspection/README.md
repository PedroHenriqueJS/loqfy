# Inspection eval

Photo pairs used by `php artisan loqfy:eval inspection`.

Each case is two photos of the same equipment: one taken when it left (`checkout`) and one
taken when it came back (`return`). A person labels each pair in `cases.json`:

```json
{
  "cases": [
    {
      "id": "drill-cracked-handle",
      "equipment": "Furadeira de impacto",
      "checkout": "drill-1-out.jpg",
      "return": "drill-1-back.jpg",
      "has_damage": true,
      "note": "Cabo trincado perto do gatilho"
    }
  ]
}
```

`has_damage` is true only when the return photo shows new damage. Pairs without damage matter as
much as pairs with it: they measure false alarms.
