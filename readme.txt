=== ChangeScout ===
Contributors: anikfahmid
Tags: changelog, ai, email notifications, release notes, automation
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered changelog monitoring for product and marketing teams tracking plugins, SaaS tools, and product updates.

== Description ==

**ChangeScout** helps product managers, marketers, founders, researchers, customer success teams, and operations teams keep up with product updates without manually checking changelog pages every week.

Add the changelog URLs you want to monitor, choose your preferred AI provider, and ChangeScout will fetch the latest updates, detect meaningful changes, and generate easy-to-read summaries you can review in WordPress or receive by email.

This is useful for:

* **Product managers** who want to monitor competitor features, releases, and roadmap signals
* **Marketers** who want to track product launches, positioning changes, and messaging updates
* **Founders** who want a quick view of market movement without reviewing long changelog pages
* **Researchers and analysts** who want structured summaries of product changes across multiple tools
* **Customer success and support teams** who want to stay aware of updates that may affect customers
* **Operations teams** who want a lightweight way to monitor important tools and platforms

= Key Features =

* **Multi-URL tracking** — monitor multiple changelog pages from plugins, SaaS tools, documentation sites, or product update pages
* **AI summaries** — generate readable summaries using Google Gemini, OpenAI, or Anthropic Claude
* **Scheduled emails** — receive changelog summaries weekly, bi-weekly, or monthly
* **Force send** — fetch and email the latest changelogs on demand without waiting for the next schedule
* **Change detection** — only send summaries when the source content actually changes
* **Custom SMTP** — use Gmail or any SMTP server for more reliable delivery
* **Auto-detect** — try common changelog and release-note URLs automatically from a domain
* **Dashboard widget** — review recent summaries directly from the WordPress admin dashboard

= External Services =

This plugin connects to third-party services to fetch and summarize changelog content.

**Google Gemini API**
Used when you choose Gemini as your AI provider. Extracted changelog content is sent to Gemini to generate a summary.
* Service: https://ai.google.dev
* Terms: https://ai.google.dev/terms

**OpenAI API**
Used when you choose OpenAI as your AI provider. Extracted changelog content is sent to OpenAI to generate a summary.
* Service: https://openai.com
* Privacy Policy: https://openai.com/policies/privacy-policy/

**Anthropic Claude API**
Used when you choose Claude as your AI provider. Extracted changelog content is sent to Anthropic to generate a summary.
* Service: https://anthropic.com
* Privacy Policy: https://www.anthropic.com/privacy

**Jina Reader (r.jina.ai)**
* Service: https://jina.ai
* Privacy Policy: https://jina.ai/legal/

== Installation ==

1. Upload the `changescout` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **Settings > ChangeScout**
4. Enter your AI provider API key (Gemini, OpenAI, or Claude)
5. Add your changelog URLs to track
6. Configure your notification email and schedule

== Frequently Asked Questions ==

= Which AI providers are supported? =

Google Gemini (free tier available), OpenAI GPT-4, and Anthropic Claude. You need to provide your own API key for the chosen provider.

= Does the force-send button affect scheduled emails? =

No. Force-send is completely independent from scheduled emails. It bypasses the cache and sends immediately without updating the change-detection hash, so scheduled emails still fire normally.

= How do I set up Gmail SMTP? =

Enable 2-Factor Authentication on your Google account, then generate an App Password at myaccount.google.com/apppasswords. Use `smtp.gmail.com`, port `587`, encryption `TLS`, and enter your Gmail address and the App Password.

== Screenshots ==

1. General settings screen for AI provider and changelog URL configuration
2. Notification settings for email schedule and delivery options
3. SMTP configuration screen for custom email sending setup
4. Changelog preview and testing tools inside the plugin settings
5. WordPress dashboard widget showing recent changelog summaries at a glance

== Changelog ==

= 1.0.2 =
* Fixed the send time dropdown labels to display clean times like 8:00 AM

= 1.0.1 =
* Fixed settings fields that were not persisting correctly after save
* Fixed notification email field saving
* Fixed SMTP enable/disable setting persistence
* Fixed dashboard widget refresh behavior

= 1.0.0 =
* Initial release
* AI-powered changelog summaries with Gemini, OpenAI, and Claude
* Auto-detect changelog URLs from any domain
* SMTP configuration with test button
* Eye toggle for API key and password fields
* Force-fetch isolation from scheduled emails
* Content extraction via Jina Reader

== Upgrade Notice ==

= 1.0.2 =
Fixes the send time dropdown labels.
