# 📅 EventsListing
Simple WordPress plugin for events listing

---

## Features
- Custom Post Type: `events`
- Custom fields: `Event Date`, `Event Type`, `Event External URL`, `Event Form (Dynamic Fields)`, `Event Banner Image`, `Event Max Attendees`, `Event Current Attendees Count`
- Public archive at `/events/`
- Single event pages at `/events/{event-name}/`

---

## Requirements
- WordPress 6.0+  
- PHP 7.4+ (8.x recommended)  
- Pretty permalinks enabled (Settings → Permalinks)

---

## Installation
1. Download or clone this repository into `wp-content/plugins/events/`.
2. In the WordPress admin, go to **Plugins → Installed Plugins** and **Activate**.
3. Visit **Settings → Permalinks** and click **Save** to flush rewrite rules.
4. Add GoogleMaps API Key in the plugin's settings section.

---

## Usage
### Add Events
1. Go to **Events → Add New**.
2. Fill in event details.
3. Publish.

### Archive & Single Pages
- Archive: [yourdomain.com/events/](#)  
- Single event: [yourdomain.com/events/{slug}/](#)

## Uninstall
Deactivation keeps your events.  
For full removal, delete plugin files (and optionally drop event data manually).

---

## Changelog
**1.0.0** – Initial release with Events CPT, custom fields, archive, shortcode.
