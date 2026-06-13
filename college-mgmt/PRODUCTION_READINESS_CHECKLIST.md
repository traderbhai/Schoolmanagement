# EduManage Production Readiness Checklist

Use this checklist before every staging or production launch. The application contains sensitive applicant, student, parent, staff, academic, document, and payment data, so launch readiness must be treated as an operational gate, not a one-time setup task.

## Environment

- Copy `.env.production.example` to `.env.production` in the deployment environment and fill every secret outside source control.
- Set `APP_ENV=production`, `APP_DEBUG=false`, a real `APP_URL`, and a generated `APP_KEY`.
- Use MySQL or PostgreSQL for production data. SQLite is only acceptable for local development and automated tests.
- Keep `SESSION_ENCRYPT=true` and use a central session backend when running more than one app instance.
- Keep `LOG_LEVEL=warning` or stricter in production unless temporarily investigating an incident.

## Database

- Run migrations with `php artisan migrate --force`.
- Take a database backup before every migration, import, bulk promotion, fee run, or result publication.
- Confirm indexes for high-volume filters: role, program, batch, status, due date, academic year, student, applicant, and payment lookup fields.
- Verify restore by loading the latest backup into a staging database at least once per release cycle.

## Queue Workers

- Run a supervised queue worker for mail, notifications, reports, admission follow-ups, fee jobs, and long-running imports:

```bash
php artisan queue:work redis --queue=default --sleep=3 --tries=3 --timeout=120
```

- Restart workers after each deployment with `php artisan queue:restart`.
- Monitor `failed_jobs`; failed mail, fee, admission, document, and result jobs must create an operational alert.
- Use Redis for production queue throughput. The database queue is acceptable for local development and small staging environments only.

## Scheduler

- Install one cron entry per production environment:

```cron
* * * * * cd /var/www/edumanage && php artisan schedule:run >> /dev/null 2>&1
```

- Confirm the scheduled commands run in production:
  - `admission:deadline-reminders`
  - `admission:followup-reminders`
  - `admission:close-expired-windows`
  - `accounts:mark-overdue-demands`
  - `fees:apply-late-fees`
  - `library:apply-fines`

## Storage And Files

- Use private object storage for applicant documents, payment proofs, student submissions, leave attachments, and generated records.
- Use public storage only for intentionally public assets such as profile photos or learning materials approved for broad access.
- Do not expose `storage/app/private` through the web server.
- Enable object storage versioning or lifecycle-backed backups for uploaded files.

## Mail And Notifications

- Use a real transactional provider for production mail.
- Keep `MAIL_FROM_ADDRESS` on a verified domain and align SPF, DKIM, and DMARC.
- Queue all bulk or workflow mail; do not send high-volume notices synchronously from web requests.
- Test password reset, admission follow-up, offer, fee reminder, and notice emails after changing mail configuration.

## Security

- Verify role access after every release using the feature test suite.
- Keep generic self-registration disabled; public users should enter through the admissions application flow.
- Keep browser security headers enabled for web pages, redirects, and API JSON responses.
- Store third-party API keys in environment variables only. Never commit real keys.
- Review file upload validation and private download authorization for admission documents and payment proofs.

## Release Gate

Run these commands from the app root before tagging a release:

```bash
php artisan test
npm run build
npm audit --audit-level=critical
composer audit
```

The release is not ready if any command fails, if the scheduler is not installed, if queue workers are not supervised, or if database and file backups cannot be restored.
