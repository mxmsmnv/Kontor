# Catalog workspace tools

The catalog tools use the linked ProcessWire user's catalog permissions and
the Workspace organization. Each write affects one item, category, price
list, or price tier and records the actor in the catalog audit log.

| Tool suffix | Purpose |
| --- | --- |
| `kontor_catalog_list` | Search and page through records of kind `item`, `category`, or `pricelist`. |
| `kontor_catalog_get` | Read one record by UID. Cost and purchase prices require item edit permission. |
| `kontor_catalog_save` | Create or update one record. Supply `uid` to update, and omit it to create. |
| `kontor_catalog_price_tiers_list` | Page through tiers in a price list, optionally for one item. |
| `kontor_catalog_price_tier_save` | Set the amount and validity dates for one list, item and quantity. |
| `kontor_catalog_references_get` | Discover writable fields, default language, currency, units and tax codes. |

Saving requires `draft` scope and the corresponding Workspace create or edit
permission. Reading price tiers requires both price-list and item view.
Setting a tier requires price-list edit and item view. The selected list
determines tier currency.

For record saves, supply `fields` as a JSON object encoded as a string. Omit
unchanged fields. Use `null` to clear an optional scalar field or a translated
value inside its language map. Creation requires a title or category name in
the organization's default language. Price lists require a name, with the
organization currency used by default. A list's currency can change once all
its price tiers are removed.

Example item fields, for an organization using English by default:

```json
{
  "title": {"en": "Showroom dining table"},
  "item_type": "product",
  "sku": "SHOWROOM-TABLE-01",
  "unit_code": "pcs",
  "sales_price": {"amount_minor": 149900, "currency": "USD"}
}
```

For price tiers, use a positive decimal string with at most six decimal
places for `quantity`, an integer amount in minor units, and optional
`YYYY-MM-DD` validity dates. Saving a tier with the same item, list and
quantity replaces its amount and validity dates.

Use `preview=true` to check a proposal without persisting it. For a retry
after saving, reuse the same idempotency key and payload. A different payload
with the same key is rejected.
