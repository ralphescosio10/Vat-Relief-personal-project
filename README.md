# VAT Relief Management System

A Laravel 13 application for an accounting firm working with multiple client companies. This workspace now contains a functional, end-to-end MVP: sign-in and password recovery, company/user administration, company-scoped accounting records, CSV/XLSX imports, VAT schedules, reviewer workflow, exports, secure client documents, submission tracking, deadlines, and audit history.

## Requirements

- PHP 8.3+ with PDO SQLite or PDO MySQL, `fileinfo`, `zip`, `xml`, and `gd`
- Composer
- MySQL 8+ recommended for deployment; automated tests use in-memory SQLite
- A current browser and network access for the Bootstrap 5 CDN assets

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

The default Laravel environment uses SQLite. For MySQL, create an empty database and set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env` before migrating.

There are no seeded accounts or default passwords. Open `/login` and select **Create an accountant account** to register. Every registered account is an accountant with access to the shared company portfolio. Registration is public, so anyone who signs up can access all client company data; keep this app private to trusted staff.

Password recovery is available from the sign-in page. The default `.env.example` mailer is `log`, which records reset email messages in the application log for local development; configure a real mail transport and a public `APP_URL` before sending recovery links to users.

## Main workflows

1. Register or sign in as an accountant, create each client company, and configure its optional default VAT rate. All accounts can open all companies, while each company's records remain scoped to that company.
2. Maintain the company’s customers and suppliers.
3. Enter invoices or upload a CSV/Excel `.xlsx` workbook for validation and review. Valid rows are imported only after an explicit commit. Correctable invalid rows can be fixed in the preview; valid rows already imported remain marked and are not re-imported when unresolved rows are corrected later.
4. Generate a sales/purchase VAT schedule for a non-overlapping date range. Review validation warnings and compare the saved schedule against current transactions.
5. Submit the report for review. An accountant can approve or return it with a note. Approved reports can be downloaded as CSV or XLSX; closing a report locks transactions in the reporting period. Accountants can reopen a closed period with a required reason.
6. Track client file submissions and manually configured internal due dates. Supporting document uploads accept PDF, JPG, and PNG only; transaction-data imports separately accept CSV and Excel `.xlsx`. Documents are kept on the private local disk and served only through company-authorized routes.
7. Review the per-company audit history for transaction changes, uploads, report decisions, exports, and period events.

## Roles

- **Accountant:** every registered account has the accountant role and can access the shared portfolio, manage company records, import files, prepare and review reports, track submissions, and upload documents.

Company membership is automatically shared across all accounts. Company records and transaction/contact references still carry their own `company_id`, so opening one company shows that company's separate accounting data. Transaction writes validate that referenced contacts belong to the same company. The database enforces company-consistent transaction/contact references, unique company memberships, and foreign keys.

## Data model

- `users`, `companies`, and `company_user` establish identities, tenant boundaries, and company-specific roles.
- `counterparties` stores company-owned customers and suppliers; deletion is soft so historical invoice references remain.
- `transactions` stores sales and purchase invoices with their company, counterparty, classification, amount, VAT rate, and computed VAT.
- `import_batches` stores validated file hashes and row-level outcomes to prevent re-importing an identical file for the same company.
- `vat_reports` stores report status, reporting dates, due dates, submission checklist timestamps, and a JSON snapshot of generated schedules.
- `report_approvals` stores report submissions, decisions, comments, closing, and reopening events.
- `client_documents` stores private-storage metadata for company-scoped supporting files.
- `audit_logs` stores actor, company, action, subject, timestamp, and before/after or other relevant details.

All accounting, import, report, and document queries are scoped to the authorized company. Never add a new tenant-owned feature without carrying its `company_id` through writes, queries, and authorization checks.

## Tests and checks

```powershell
php artisan test
vendor\bin\pint --test
php artisan view:cache
php artisan view:clear
```

Feature tests cover memberships, tenant boundaries, VAT-inclusive calculations, cross-company contact protection, imports (CSV and Excel, including row correction and partial retries), password recovery, report reconciliation/approval, period locks and reopening, client-role permissions, and private document access.

## Tax and deployment notes

The VAT calculator applies the rate entered for each transaction and handles inclusive/exclusive VAT with two-decimal rounding. A company default rate is only a prefill; it is not a tax determination. Tax treatment, current Philippine VAT rules, BIR relief-schedule fields, deadlines, and any official filing format have **not** been verified or certified by this application. Have a qualified Philippine tax professional verify those items before production use. The export is a generic schedule, not a certified BIR form.

This is a working MVP, not a production compliance certification. Before exposing it publicly, configure HTTPS, mail delivery and password recovery, backups, least-privilege database credentials, production error handling, monitoring, private document retention/encryption and malware scanning, and a formal security review. Configure a durable private storage backend for multi-server deployments.
