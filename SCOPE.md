# Assessment Scope

The supplied design shows invoice creation/editing, invoice lists, customer selection, item selection, invoice detail, Paid/Unpaid states and invoice totals. The assessment's written example also contains payment/amount-due information, but it does not define a payment transaction workflow.

Therefore this implementation intentionally does not implement:

- partial payments
- payment methods
- payment transaction records
- payment history
- accounting/ledger integration

Instead, the API provides:

`POST /v1/invoices/{id}/mark-paid`

This performs a single state transition to `paid` and sets `amount_paid` to the invoice total. It is not a payment-processing system.

The implementation also includes draft/sent/cancelled states as application workflow states. They are kept separate from payment processing and can be simplified if the final frontend only needs Paid/Unpaid.
