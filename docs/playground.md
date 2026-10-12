# Playground demo

The demo site runs in [WordPress Playground](https://wordpress.org/playground/), entirely in the browser: nothing is installed and nothing is kept when the tab is closed.

**[Open the demo](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/NeilWJames/email-notice-wp-document-revisions/main/playground/blueprint.json)**

It has WP Document Revisions (the version on WordPress.org) and this plugin (the latest code on GitHub, `main`), set up by `playground/demo-content.php`.

## What is in it

- **Users** (password `password`): `admin` (Administrator), `editor` (Elena Editor), `author` (Arthur Author), `contributor` (Connie Contributor), `subscriber` (Sam Subscriber) and `subscriber2` (Sue Subscriber). The administrator, Elena, Sam and Sue receive notifications; Sam also asks for the document as an attachment. Log out and in again as another user to see what each role can do.
- **Departments**: a taxonomy on Documents added for the demo, with Finance (Payroll, Purchasing), Operations and Human Resources.
- **Documents**: 2026 Budget, Payroll calendar, Purchasing policy (public); Staff handbook (private); Fire drill procedure and Travel expenses form (public, not yet notified); Strategy 2027 (draft). Each published Document has a small text file as its document.
- **Document External Lists**:
  - *Auditors*: Finance (so also Payroll and Purchasing), any term, document attached; Bob's address is paused; log entries deleted 90 days after the Document last changed.
  - *Board members*: Finance and Operations, all terms must match.
  - *Suppliers*: Purchasing.
  - *Newsletter*: Operations, but paused.
- **Notifications already sent**, so both email logs have entries: the budget twice (the second with extra text and the "previously sent" repeat text), the payroll calendar, the purchasing policy (to its Lists only) and the staff handbook (private: only the administrator and Elena).

A playground has no mail server, so a small must-use plugin (`playground/demo-mu-plugin.php`) records each email as sent without sending it. The logs show what would have been sent.

## Things to try

- Open *Travel expenses form* and use **Send notification email(s)** and **Send external list email(s)** in the Document Email Notifications box (it matches the Auditors List); add some extra text first.
- Open *Fire drill procedure*: no List matches Operations alone (Board members needs Finance too, and Newsletter is paused), so only internal users can be notified.
- Look at **Document Emails > Internal User Email Log** and **External User Email Log**, search them, and use **Export CSV**.
- Edit a Document External List: addresses, match rule, Pause, Document Attach and the Email Log days.
- Log in as `author`: Arthur can notify internal users about his own *Travel expenses form*, but sees no Lists or logs. Log in as `contributor`: no sending at all.
- Settings > Writing (notification templates, Internal email log days) and Settings > General (From name and address).

## Other versions

- **Local**: `npm run playground` (Node 20+) runs `playground/blueprint-local.json` with this plugin folder mounted, at http://127.0.0.1:8889. The server only answers on the machine it runs on, so start it where your browser is (for example from PowerShell in the plugin folder on a network drive), or forward the port from that machine with `ssh -N -L 8889:127.0.0.1:8889 <server>`.
- **WordPress.org Live Preview** (the released plugin): `npm run assets-blueprint` builds `assets/blueprints/blueprint.json` from the files above, with the demo code inside it, for the plugin's WordPress.org assets. Build it only once the released version has what the demo uses (4.0.0).
