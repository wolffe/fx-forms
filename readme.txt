=== FX Forms ===
Contributors: butterflymedia
Donate link: https://buymeacoffee.com/wolffe
Tags: forms, contact form, classicpress
Requires at least: 5.3
Requires CP: 2.5
Tested up to: 2.7.3
Requires PHP: 8.1
Stable tag: 2.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A small contact-form plugin for ClassicPress. Build forms in the admin, drop them anywhere with a shortcode, and get notified by email.

== Description ==

FX Forms is a minimal, no-frills form plugin built for ClassicPress (works on WordPress too). It does one thing well: lets you define forms in the admin and embed them in pages or posts. When a visitor submits, you get an email — no submissions are stored in the database.

Find more tools at [ClassicPress Plugins](https://getbutterfly.com/classicpress-plugins/).

* Custom post type "Forms" for managing form definitions
* Field types: text, full name, email, phone, number, URL, date, time, textarea, select, checkbox
* Full / half width fields, side-by-side via CSS Grid
* Per-field help text shown under each input
* Drag-and-drop field ordering (uses ClassicPress 2.7+ native SortableJS)
* Generic fields — pick any type and give it your own label
* Embed with `[fxform id=N]` shortcode
* Sends email on submit, supports multiple recipients (comma- or semicolon-separated)
* Sets `Reply-To` automatically when an email field is present
* Honeypot anti-spam field built in
* No submissions stored in the database
* No jQuery, no React, no build step — vanilla JS only

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it in the Plugins menu.
3. Go to **FX Forms → Add New** and build your form.
4. Copy the shortcode shown above the title and paste it into any page or post.

== Frequently Asked Questions ==

= How do I embed a form? =

Each form has a shortcode shown at the top of the editor — `[fxform id=42]`. Paste it into any page or post.

= Where do submissions go? =

Straight to your email. Nothing is stored on the site.

= Can I send to multiple recipients? =

Yes — in the form's "Send to" field, separate addresses with commas or semicolons.

= Can replies go to the visitor? =

If your form has a field of type "Email" and the visitor fills it in, that address is automatically set as the `Reply-To` header on the notification email.

= What email placeholders are available? =

`{form_title}`, `{form_id}`, `{data}` (all fields), and any individual field id like `{email}` or `{message}`. For "Full Name" fields, `{your_name}` produces "First Last", and `{your_name_first}` / `{your_name_last}` work too.

== Changelog ==

= 2.1.2 =
* Confirm compatibility with ClassicPress 2.7.3.

= 2.1.1 =
* Confirm compatibility with ClassicPress 2.7.2.
* Add links to ClassicPress Plugins and the donation page.
* Add an installable plugin ZIP to each GitHub release.

= 2.1.0 =
* New: Global Settings page (FX Forms → Settings) — choose the sending method and configure the email log.
* New: SMTP2Go mailer — enter your API key and select SMTP2Go as the mailer to send via the SMTP2Go REST API instead of wp_mail. More mailer options can be added later.
* New: Email log — optionally store a copy of every sent email in a dedicated database table; configurable maximum entry count (default 1000); entries are pruned automatically; viewable and clearable from FX Forms → Email Log.
* New: Per-form CAPTCHA — enable a text verification code on any form to deter spam; stateless (no PHP sessions), challenge stored in a short-lived transient.

= 2.0.0 =
* Initial release as FX Forms.
* Procedural PHP 8.1+, vanilla JS, no jQuery, no React, no build step.
* Generic field builder: pick a type, give it your label.
* Field types include a composite "Full Name" (First / Last sub-inputs).
* Full / half width fields via CSS Grid.
* Per-field help text shown under each input.
* No submissions are stored — emailed only.
* Server-rendered shortcode, classic POST/redirect submit flow (no REST).
* Multiple notification recipients.
* Automatic Reply-To from a submitted email field.
* Built-in honeypot.
