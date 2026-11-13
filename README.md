# University Registration and Online Payment System

Laravel build of the design in the SIA midterm paper. It is a proposed design running on sample data, not a deployed university system.

## Run it

Start MySQL in the XAMPP Control Panel, then create the two databases:

```bash
/c/xampp/mysql/bin/mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS sia_registration CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS sia_legacy_records CHARACTER SET utf8mb4;"
```

Then install and load the sample data:

```bash
composer install
npm install
npm run build
php artisan migrate:fresh --seed
php artisan serve
```

Open http://localhost:8000. To retry stalled enrollments automatically, keep this running in a second terminal:

```bash
php artisan schedule:work
```

Run the integration tests (IT-01, IT-02, IT-03, and the PayPal adapter):

```bash
php artisan test
```

## Sample sign-ins

All three use the password in `DatabaseSeeder::DEMO_PASSWORD`.

| Role | Sign-in ID | Notes |
|---|---|---|
| Student | `2024-00001` | Has completed CS 101 and MATH 101 |
| Student | `2024-00002` | Failed CS 101, so CS 102 is blocked |
| Registrar | `registrar` | Reconciliation queue and integration log |

## Payments

Set these in `.env`, using a REST app from the PayPal developer dashboard:

```
PAYMENT_DRIVER=paypal
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
```

Then run `php artisan config:clear`. Sign in at PayPal with a sandbox personal account when paying.

`PAYMENT_DRIVER=stub` replaces PayPal with the test stub from section 2.2 of the paper, which lets you choose success, declined, pending, or temporary failure.

Amounts come from `config/registration.php` and are sample values.

## How the paper maps to the code

| Paper component | Code |
|---|---|
| Student Web Portal | `routes/web.php`, `resources/views` |
| API Gateway | `routes/api.php` (`/api/v1`), `app/Http/Middleware/ApiGateway.php` |
| Registration Service | `app/Services/Registration/RegistrationService.php` |
| Course Catalog Microservice | `app/Services/Catalog/CourseCatalog.php` (a service class in this one-app build) |
| Payment Integration System | `app/Services/Payments/PaymentIntegration.php` |
| Payment Gateway or stub | `PayPalGateway.php`, `StubPaymentGateway.php` |
| Student Records API / Adapter | `app/Services/Records/LegacyStudentRecords.php` |
| Legacy Student Records System | separate `legacy` database with its own formats (a stand-in) |
| Retry and staff reconciliation | `app/Services/Registration/EnrollmentRecovery.php`, `/staff/reconciliation` |
| Logs and status tracking | `integration_logs` table, `/staff/integration-log` |

The web portal and the REST gateway call the same services. REST callers send the `X-Gateway-Key` header, set by `API_GATEWAY_KEY` in `.env`.

## Demonstrating IT-03

1. As a student, reserve a course.
2. As `registrar`, choose **Simulate an outage**.
3. As the student, pay. The slip shows payment confirmed and enrollment pending.
4. As `registrar`, choose **Restore the system**, then **Retry now**.
5. The student is enrolled with the original payment reference and one capture.
