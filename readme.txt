=== Freer Reviews ===
Contributors: jacobfrees
Tags: reviews, testimonials, rating, google reviews, schema
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collect five-star reviews on your own site, sync Google Business reviews, and follow up by email. Free forever, no monthly fee, no SaaS lock-in.

== Description ==

Freer Reviews turns feedback into a freedom loop. Visitors leave a review on your site, Google reviews pull in alongside them, and everyone gets a warm thank-you by email.

It replaces review tools that charge big money every month with something free, forever. No SaaS lock-in, no data sold. Your reviews are your own.

Features:

* Beautiful star-rating form (shortcode `[freer_reviews_review_form]`) with configurable stars from 1 up to 10
* Review Targets: collect and manage reviews for many different things (products, services, posts) independently
* Per-target moderation: auto-publish or review each one individually
* Display your reviews with `[freer_reviews target="slug"]`
* Average rating + schema.org AggregateRating for SEO/AEO with `[freer_reviews_rating target="slug"]`
* Google Business review sync (Places API) or zero-credential JSON/CSV import
* Thank-you email to every reviewer, plus a review-request flow
* Hook layer (`freer_reviews_send_review_email`, `freer_reviews_thankyou_email`, `freer_reviews_review_request_email`) so any mailing plugin can take over routing
* Schema.org Review + AggregateRating JSON-LD

== Installation ==

1. Upload the `freer-reviews` folder to `/wp-content/plugins/`, or install the ZIP under Plugins > Add New.
2. Activate the plugin.
3. Put `[freer_reviews_review_form]` on your review page and `[freer_reviews]` where you want them displayed.
4. Visit Freer Reviews > Settings to configure moderation, email, and Google sync.

== Frequently Asked Questions ==

= Is it really free? =

Yes. Completely free, GPL open source, forever. It replaces review tools that bill a monthly subscription for the same thing.

= Does this need a Google API key? =

Only for the automatic Google Business pull (your own Places API key + place_id). The Import tab works with zero credentials: paste your reviews as JSON or CSV and they land instantly.

= Can it send email through my mailing plugin? =

Yes. It fires standard hooks (`freer_reviews_send_review_email`, `freer_reviews_thankyou_email`, `freer_reviews_review_request_email`), so FluentCRM, Mailchimp, MailPoet and other mailing plugins can route the emails.

= Can I rate things out of more than five stars? =

Yes. Each review target can use its own star scale, from 1 up to 10.

= Can I collect reviews for many different things at once? =

Yes. Manage reviews for many products, services, blog posts or anything else from one dashboard.

== Changelog ==

= 1.7.0 =
* Google-grade human verification: a single-click "I'm not a robot" check is now on by default on the review form (and comments). Uses Cloudflare Turnstile when you add keys, otherwise a built-in server-verified single click. Toggle it off anytime in Settings.

= 1.6.0 =
* Bot & spam protection: the review form now uses layered defence (honeypots, a time trap, per-IP rate limiting, and link-in-text scanning), and the same protection is applied to the WordPress comment form so blog comments are covered too.

= 1.5.0 =
* Naming & hierarchy: the reviews list is now "Submitted Reviews"; review targets are now "Review Forms"; a new parent "Review Groups" taxonomy groups forms into collections. Dashboard shows Groups > Forms > Reviews.

= 1.4.1 =
* Admin polish: "Review Targets" renamed to "Review Groups" with clearer labels; the reviews list shows each review's group; the Review Groups screen lists existing groups on top with the add-new form below.

= 1.4.0 =
* Full per-form customization: show/hide each field (rating, name, email, text), mark any field required or optional, change labels, placeholders, submit button text, and text min-length. Configurable per review target in the admin, or inline per shortcode.

= 1.3.1 =
* Order fix: the default review display (average + stars + people's text) now appears ABOVE the review form.

= 1.3.0 =
* Default display: the review form now automatically shows the approved reviews + average rating (opt out per shortcode with show="0").

= 1.2.0 =
* Review Targets: collect independent reviews for many different things, each with its own form; track & manage them all from one dashboard.
* Configurable star scale (1 to 10), global default + per-target override.
* Per-target posting: auto-publish or individually moderate.
* Per-target form titles; schema bestRating reflects the target scale.

= 1.1.0 =
* Self-update via the Freer Plugin Hub.

= 1.0.0 =
* Initial release.

== Screenshots ==

== Other Notes ==

Freer Reviews is one of a family of free WordPress plugins and tools by Jacob Frees, distributed with automatic updates. See https://jacobfrees.com/wiki-everything-given-for-free/ for everything given away free, forever.
