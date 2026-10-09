# Architecture Notes

## Request flow

```text
HTTP request
→ Yii routing
→ CORS / Bearer authentication
→ REST Controller
→ InvoiceService for invoice business operations
→ ActiveRecord
→ MySQL
→ JSON response envelope
```

## MVC + Service Layer

Controllers own HTTP concerns such as request parameters, status codes and serialization. `InvoiceService` owns invoice validation, line-item replacement, calculation and transactional persistence.

This prevents invoice business logic from being duplicated between create/update/action endpoints.

## Dependency Injection

`InvoiceService` is injected into `InvoiceController` through Yii's DI container. This keeps the controller independent from the concrete construction of the service and makes the service easier to replace in tests.

## Data integrity

Invoice totals are never accepted as authoritative client input. The server recalculates line totals, subtotal, tax and grand total from the submitted line items.

Invoice writes use a database transaction because an invoice and its line items must either all succeed or all fail.

Products are referenced by ID but invoice lines also store their description and unit price. This creates a historical snapshot of what was invoiced.

## Pagination

Invoice, customer and product list endpoints use cursor pagination:

```sql
WHERE id < :cursor
ORDER BY id DESC
LIMIT :limit
```

Compared with a large `OFFSET`, this lets MySQL continue from an indexed primary-key position rather than scanning/skipping hundreds of thousands of rows.

## Indexing

Indexes are provided for common invoice filtering paths, customer/date lookups, invoice item lookups and active product searches. The assessment's million-row requirement should still be validated with realistic fixtures and `EXPLAIN` plans before production use.

## Error handling

All web exceptions are converted to JSON through `ApiExceptionHandler`. Successful responses are wrapped consistently by `ApiResponse`.

Known HTTP errors retain a useful client-facing message. Unexpected errors expose a generic message outside debug mode while still being logged by Yii.
