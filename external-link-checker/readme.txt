# External Link Checker

Contributors: khalihossain  
Tags: links, external links, broken links, link checker, seo  
Requires at least: 6.0  
Tested up to: 6.7  
Stable tag: 1.0.0  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  
Donate link: https://khalihossain.com.bd  

Find broken external links in your WordPress website before your visitors do. A lightweight plugin that scans your content for external links and identifies broken, redirected, or unavailable URLs.

## Description

External Link Checker is a lightweight, free WordPress plugin that scans your website content for external links and helps you identify links that are broken, redirected, unavailable, or need review.

It is designed to do one thing well: **help website owners find problematic external links without installing a large SEO plugin.**

### Features

* 🔗 Scan external links in WordPress content
* ✅ Check whether external links are reachable
* ❌ Identify broken links such as 404 errors
* ↪️ Detect redirected URLs
* ⚠️ Flag links that need review
* 📊 View scan statistics
* 📝 See which post contains a problematic link
* 🔍 View the anchor text associated with the link
* ✏️ Quickly open the post for editing
* 🔄 Recheck individual links
* 🧹 Delete old scan results
* 🚫 Exclude selected domains
* ⚙️ Configure basic scanning options
* 🪶 Lightweight and focused

### Why External Link Checker?

External links can become unavailable over time. A website may delete a page, change a URL, move content, return a 404 error, redirect to another page, stop responding, or change its domain.

A broken external link can create a poor user experience and make maintaining a large website difficult.

External Link Checker gives you a simple way to monitor these links from your WordPress dashboard.

### How It Works

The plugin follows a simple workflow:

1. Scans WordPress content (posts, pages, custom post types)
2. Finds external links (links pointing outside your domain)
3. Checks each URL via HTTP request
4. Analyzes the HTTP response
5. Stores the result in the database
6. Displays a report in the admin dashboard

### Link Statuses

The plugin classifies links into several categories:

* **Working** - The external URL responds successfully (HTTP 2xx)
* **Broken** - The URL cannot be reached or returns an HTTP error (404, 410, 500, etc.)
* **Redirected** - The original URL redirects to another URL (HTTP 3xx)
* **Needs Review** - The plugin cannot confidently determine whether the link is usable (timeouts, access restrictions, bot protection, etc.)

### External Links Only

External Link Checker focuses on links pointing outside your own website. Internal links are not treated as external links.

### Domain Exclusions

You can exclude domains that should not be checked. This is useful for websites containing many links to services that intentionally restrict automated requests (e.g., youtube.com, facebook.com).

### Post Information

For every detected problematic link, the plugin shows useful context:

* Post Title
* Post Type
* Post URL (edit link)
* External URL
* Anchor Text
* HTTP Status
* Last Checked Date

### Recheck Links

Internet resources can temporarily fail. The plugin allows individual links to be checked again to prevent temporary network problems from being treated as permanent broken links.

### Lightweight by Design

External Link Checker is not intended to replace a complete SEO suite. It focuses specifically on **external link monitoring** without keyword research, rank tracking, backlink analysis, site audits, AI content generation, page builders, or analytics.

### Privacy

External Link Checker does not track visitors. The plugin performs link checks from the WordPress server. Depending on the website configuration, checking an external URL may send a request to that external website.

### Performance

The plugin is designed to perform link checking separately from normal visitor requests and to process links in controlled batches. Very large websites should configure scanning carefully according to their hosting resources.

## Installation

### From WordPress Admin

1. Log in to your WordPress dashboard.
2. Go to **Plugins → Add New Plugin**.
3. Search for **External Link Checker**.
4. Click **Install Now**.
5. Click **Activate**.
6. Open **External Link Checker** from the WordPress dashboard.

### Manual Installation

1. Download the plugin ZIP file.
2. Upload the `external-link-checker` folder to the `/wp-content/plugins/` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.
4. Open **External Link Checker** from the WordPress dashboard.

## Frequently Asked Questions

### Does this plugin check internal links?

No. The primary purpose of the plugin is checking external links only.

### Does it automatically delete broken links?

No. The plugin helps administrators identify problematic links. The website owner decides whether to edit, replace, or remove them.

### Does a "Needs Review" link always mean it is broken?

No. Some websites block automated requests or temporarily return unusual responses. These links should be reviewed manually.

### Will the plugin slow down my website?

The plugin is designed to perform link checking separately from normal visitor requests and to process links in controlled batches. However, very large websites should configure scanning carefully according to their hosting resources.

### Can I exclude a domain?

Yes. Administrators can exclude domains that should not be checked from the settings page.

### Is the plugin free?

Yes. The core plugin is free and open source under the GPLv2 license.

## Screenshots

1. Dashboard showing scan statistics and link management interface
2. Filter links by status (All, Broken, Redirected, Needs Review, Working)
3. Settings page with batch size, timeout, and domain exclusion options

## Changelog

### 1.0.0
* Initial release
* External link scanning
* HTTP status detection
* Broken link detection
* Redirect detection
* Link result dashboard
* Post and anchor-text information
* Individual link rechecking
* Domain exclusion
* Basic scan management

## Upgrade Notice

= 1.0.0 =
Initial release of External Link Checker.
