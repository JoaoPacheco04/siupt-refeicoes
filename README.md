# SIUPT — Meal Purchase & Payment Module

A curricular internship project built at the Information Systems Department of Universidade Portucalense (UPT), extending the university's internal system (SIUPT) with a full meal ordering, payment, and validation flow for the campus canteen — from menu browsing to QR-code pickup and back-office management.

`PHP` `SQL Server` `jQuery` `Bootstrap` `PHPUnit`

## Why this project

Canteen ordering looks simple until you have to handle real operational constraints: rotating menus with per-item deadlines, extras that follow different rules than the daily menu, holidays and one-off "special days" that change what's orderable, staff needing a fallback when QR scanning fails, and three different user roles that each see a different slice of the system. This project was built to solve that full picture, end to end, on top of a legacy institutional codebase.

## Key features

**For students & staff**
- Browse the daily menu and available extras
- Place and pay for orders, respecting per-item purchase deadlines
- Pick up meals via a personal QR code
- Cancel a pending (unpaid) order
- Transfer a paid order to another user (once, after payment)
- Rate a completed meal and, optionally, leave a complaint reason

**For canteen attendants**
- Validate orders by scanning the QR code at pickup
- Manual contingency validation via a daily PDF list of short order codes, for when QR scanning fails

**For canteen admins**
- Everything attendants can do, plus:
- Manage extras, prices (with full price history), and purchase deadlines
- Manage holidays, special days, and complaint reasons
- Assign or remove the attendant role for other users
- Generate monthly reports (PDF and CSV)

## Architecture & business logic

| Concern | Solution |
|---|---|
| Menu day states | Five distinct states — holiday, no-menu/extras-only, special closed day, special day with extras, regular day — computed dynamically rather than hardcoded per date |
| Order deadlines | Regular menu items lock at 14:30 the day before; extras remain orderable until 10:00 the same day |
| Contingency | QR failures are covered by a daily PDF fallback list of short order codes for manual staff validation |
| Access control | Three-layer security: conditional UI rendering, page-level login checks, and API-level role checks (HTTP 403 on unauthorized access) |
| Data integrity | 17-table relational model; historical pricing resolved via latest-effective-date lookups rather than overwriting old prices |
| Holiday generation | Automatic yearly generation of fixed and moveable holidays, fixed after an early bug where a name-based anchor broke on holidays without a stable name |

## Tech stack

**Backend:** PHP · PDO · SQL Server (Laragon-hosted for local development)

**Frontend:** jQuery · Bootstrap 5.3.8 · server-rendered pages

**QR codes:** `html5-qrcode` (scanning) · `davidshimjs-qrcodejs` (generation)

**Testing:** PHPUnit, with an isolated test database (`siupt_refeicoes_test`) — real database integration tests, not mocks

**Tools:** VS Code · Postman

## Project structure

```
public/api/        # API endpoints
src/
 ├─ Infrastructure/ # Database.php
 ├─ Services/       # PagamentoService.php
 └─ Support/        # Auth.php, Assets.php
```

## Screenshots

*(add screenshots here — menu view, QR order, attendant validation screen, admin dashboard)*

## Testing

```
# from the project root, using the Laragon PHP binary
php vendor/bin/phpunit
```

45 tests, 67 assertions, across 11 test classes — covering pricing and deadlines, order creation, QR validation, transfers, ratings, extras management, holidays and special days, user roles, duplicate payment prevention, order cancellation, and complaint-reason logic. Tests run against an isolated database and clean up after themselves rather than relying on transaction rollback, since nested transactions aren't supported by the target database engine.

## Known limitations / future work

- Deadline validation is enforced server-side but can currently be bypassed via direct API calls in some edge cases
- No automated CI pipeline
- Built as an extension of an existing institutional system, so some conventions (naming, structure) follow constraints outside this module's control

## License

Academic project — developed for a curricular internship at UPT, not intended for production use.
