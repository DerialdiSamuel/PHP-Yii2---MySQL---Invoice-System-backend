# Invoice System API

REST API for the invoice-system assessment, built with **PHP 7.4, Yii2 and MySQL 5.7**.

## Run with Docker

From the project root, run:

```bash
docker compose up -d --build
docker compose exec api php yii migrate --interactive=0
docker compose exec api php yii seed/demo
```

The `--build` option rebuilds the API image so that source-code changes are included in the container. If you change PHP source files and the container does not reflect those changes, rebuild with:

```bash
docker compose up -d --build api
```

API base URL: `http://localhost:8080/v1`

## Authentication and Postman

Demo login: **`admin` / `admin123`**

Most endpoints require a bearer token. A request to a protected endpoint without a valid token is unauthorized; it is not evidence that the route or database is broken.

1. Import `postman/Invoice-System.postman_collection.json` into Postman.
2. Send `POST http://localhost:8080/v1/auth/login` with this JSON body:

   ```json
   {
     "username": "admin",
     "password": "admin123"
   }
   ```

3. Copy the token from the successful login response.
4. For protected requests, open **Authorization** in Postman, choose **Bearer Token**, and paste the token. Alternatively, send this header:

   ```http
   Authorization: Bearer <token>
   ```

5. Send the request again. Ensure the token is copied as-is; do not include the word `Bearer` in the Postman's Bearer Token value because Postman adds that prefix itself.

`GET /health` is public and can be used to check that the API is responding. Customers, products, invoices, and dashboard endpoints should be tested with the login token.

### Quick smoke tests

```http
GET  http://localhost:8080/v1/health
POST http://localhost:8080/v1/auth/login
GET  http://localhost:8080/v1/customers
GET  http://localhost:8080/v1/products
```

For the login request, select **Body → raw → JSON** in Postman and use the demo credentials above. For the customers and products requests, configure **Authorization → Bearer Token** first.

## Main endpoints

- `POST /auth/login`
- `GET /health`
- `/customers` — list, create, view, update and delete
- `/products` — list, create, view, update and deactivate
- `/invoices` — list, create, view, update and delete drafts
- `POST /invoices/{id}/send`
- `POST /invoices/{id}/mark-paid`
- `GET /dashboard/summary`

Invoice and reference-data lists support filtering and cursor pagination. Import `postman/Invoice-System.postman_collection.json` to try the API.

## Routing notes

Public URLs use plural resource names (`/customers` and `/products`), while the Yii controller classes are singular (`CustomerController` and `ProductController`) under `src/controllers/v1/`. The URL rules in `config/web.php` map those public URLs to the corresponding controller actions. Keep the route targets and `controllerNamespace` consistent when editing this configuration.

If you modify source code, rebuild the API image with `docker compose up -d --build api` so the container runs the updated code.

## Design

- Controllers handle HTTP; `InvoiceService` contains invoice business logic.
- Invoice writes use database transactions.
- Line totals, tax and invoice totals are calculated by the server.
- Invoice items retain description and unit-price snapshots.
- Responses use a consistent JSON structure.

## Tests

Run the unit suite:

```bash
docker compose exec api composer test
```

Or locally:

```bash
composer install
vendor/bin/codecept run unit --no-interaction
```

The suite includes calculation and invoice-service tests, plus large-dataset checks in `InvoiceLoadTest`.

## Million-row test

Run this against a local/test database only. It inserts synthetic data and can take time and disk space; it does not automatically remove generated rows.

```bash
# Create one million invoices and corresponding invoice items
docker compose exec api php yii seed/bigdata 1000000 1000

# Run the large-dataset tests
docker compose exec api vendor/bin/codecept run unit InvoiceLoadTest --no-interaction
```

`InvoiceLoadTest` checks that the invoice-list query returns a bounded page within its configured time budget (default **1,000 ms**) and that cursor pages are ordered and do not overlap. These tests are skipped unless the database contains at least one million invoices.

To use a smaller dataset for a smoke test, run `php yii seed/bigdata 10000 1000`; the million-row assertions will remain skipped.

The list implementation uses cursor pagination and indexed ordering to avoid large `OFFSET` scans. For further performance analysis, inspect the relevant query plans with MySQL `EXPLAIN`; actual timings depend on the environment.
