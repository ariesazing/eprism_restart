# Review and management updates

- Approval requires a server-validated score of at least 70/100. Revision remains available at any score. Existing minor/major revision records display as Revision and continue to participate in decision calculations.
- Evaluators see their own submitted evaluation alongside peer evaluations; peer evaluations remain hidden until the evaluator submits their own.
- Filters start expanded. User, school/office, researcher, and reviewer lists support date ordering with newest first by default. Paginated lists show the page number even when there is only one page.
- Repository filters support school/office category and individual unit without changing role-based visibility.
- Account creation closes with cleared inputs; school/office edits save one listing at a time. Management actions have labeled icons.
- Profile navigation switches between the desktop header and mobile sidebar, including mobile editor pages. Similarity reports retain the app sidebar. Quick Actions are removed.
- Shared dialogs teleport to the document body and reserve scrollbar space. Notification controls have improved contrast.

## Security

Authentication continues to use Laravel session cookies and CSRF protection. Seeing your own login credentials in the browser's outgoing request or cookies/CSRF tokens in DevTools is expected; these cannot be hidden from the browser sending them. HTTPS protects transport. Authentication and error responses are not cached, sensitive values are not flashed, production sessions default to encryption and secure cookies, and responses suppress referrers and MIME sniffing.

The ONLYOFFICE chapter/template callbacks previously accepted any valid JWT and then consumed unsigned request-body fields. They now consume the authenticated payload, bind it to the document key in the signed callback URL, limit downloads to the configured ONLYOFFICE origin, and reject redirects. Raw download exceptions no longer appear in user-visible activity entries.

## Deployment

1. Run `php artisan migrate --force` and rebuild assets with `npm ci` / `npm run build` as part of the normal deployment. Refresh application caches and restart workers.
2. Keep the scheduler and queue workers running, with a working mail transport. The existing Docker Supervisor configuration already runs both. No live email delivery was attempted during development.
3. Reopen ONLYOFFICE chapter/template editors after deployment to obtain callback URLs containing their signed document key. Previously opened editor sessions use the old callback format.

The new migration establishes notification state without emailing historical changes. Manual opening/closing changes queue an email to every non-deleted user; the scheduler checks effective availability every minute and sends scheduled transitions once. Saving an unchanged timeline does not send another notification.
