# Commerce draft tools

| Tool | Required Workspace permission |
| --- | --- |
| `kontor_quotation_draft_create` | Quotation create and customer-type view |
| `kontor_quotation_draft_update` | Quotation view and edit |
| `kontor_invoice_draft_from_order` | Invoice create and sales order view |
| `kontor_invoice_draft_update` | Invoice view and draft edit |

All four require the MCP `draft` scope and a linked user in the Workspace
organization. Operations write to the sales or invoice audit log. Use the
same idempotency key and payload if a save needs retrying.

To find source documents, use `kontor_sales_quotations_list` or
`kontor_sales_orders_list` with a document number or a status filter. For
invoice drafts, look for `confirmed` or `completed` orders. The corresponding
`kontor_sales_quotation_get` and `kontor_sales_order_get` tools show totals
and up to 25 document lines. These tools use the quotation or order view
permission. Customer IDs and customer filters additionally require permission
to view that customer type.

Create a quotation with a customer UID, currency and 1–20 lines encoded as a
JSON array. Use integer amounts in minor units. Quantity and tax rate may be
decimal strings with up to six fractional digits.

```json
[
  {"title":"Showroom consultation","quantity":"2","unit_code":"hour","unit_price_minor":12500,"tax_rate":"8.25"}
]
```

Quotation updates accept a JSON object containing `language`, `valid_until`
or replacement `lines`. Replacement recalculates subtotal, tax and total.
For a preview, pass `preview=true` on quotation creation or editing.

Invoice creation uses a confirmed or completed sales order and copies its
lines to an invoice draft. An optional `due_date` overrides the default.
Invoice draft editing accepts a JSON object containing `due_date` or
`language`; `preview=true` checks the proposed header. Dates use
`YYYY-MM-DD` and languages are `en`, `de`, `fr` and `es`.

The resulting documents remain drafts. Issuing, sending, credit notes and
payment settlement use the existing Workspace workflows.
