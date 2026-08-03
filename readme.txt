=== FreeFlow Reviews ===
Contributors: jacobfrees
Tags: reviews, rating, google reviews, testimonials, schema
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect 5-star reviews on your site, sync Google Business reviews, and follow up by email. Free, built with love.

== Description ==

FreeFlow Reviews turns feedback into a feedback loop. Visitors leave a 5-star review on your site, Google reviews pull in alongside them, and everyone gets a warm thank-you by email.

Built to be free, simple, and honest. No SaaS lock-in, no monthly fee, no data sold. Your reviews are your own.

Features:

* A beautiful star-rating review form (shortcode `[freeflow_review_form]`)
* Display your reviews with `[freeflow_reviews limit="20"]`
* Show your average rating + schema.org AggregateRating for SEO/AEO with `[freeflow_rating]`
* Google Business review sync (Places API) or manual JSON/CSV import
* Moderation queue (auto-approve optional)
* Thank-you email to every reviewer
* Send review-request emails to a list of past customers
* A hook layer (`ffr_send_review_email`, `ffr_thankyou_email`, `ffr_review_request_email`) so any mailing plugin can take over email routing
* Schema.org Review + AggregateRating JSON-LD

== Installation ==

1. Upload the `freeflow-reviews` folder to `/wp-content/plugins/`, or upload the ZIP under Plugins > Add New.
2. Activate the plugin.
3. Put `[freeflow_review_form]` on your review page and `[freeflow_reviews]` where you want them displayed.
4. Visit FreeFlow Reviews > Settings to configure moderation, email, and Google sync.

== Frequently Asked Questions ==

= Does this need a Google API key? =

For the automatic Google pull, yes (your own Places API key + place_id). But the Import tab works with zero credentials: just paste your reviews as JSON or CSV and they land instantly.

= Can it send email through my mailing plugin? =

Yes. All emails go through `ffr_send_review_email` filter and fire `ffr_thankyou_email` / `ffr_review_request_email` actions, so any plugin (FluentCRM, Mailchimp, MailPoet, ...) can intercept and route them.

== Changelog ==

= 1.0.0 =
* Initial release.
