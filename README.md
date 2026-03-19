# BRK Datenschutz – WP Multisite Privacy Scanner

Scannt alle Sites eines WordPress-Multisite-Netzwerks automatisch nach datenschutzrelevanten Diensten und erstellt einen Bericht pro Site.

## Was wird gescannt?

| Scan-Ebene | Was wird geprüft |
|---|---|
| **Aktive Plugins** | Plugin-Slugs gegen bekannte Dienst-Datenbank abgleichen |
| **Netzwerk-Plugins** | Sitewide-Plugins gelten für alle Sites |
| **Post-Inhalte** | HTML/Shortcodes nach externen URLs (YouTube, Google Maps, etc.) |
| **Post-Meta** | Page-Builder-Daten (Elementor etc.) auf externe Ressourcen |
| **Options-Tabelle** | API-Keys, Tracking-IDs, Dienst-Konfigurationen |
| **Widgets** | Widget-Inhalte mit externen URLs |
| **Theme-Optionen** | Theme Customizer-Einstellungen |
| **Theme-Dateien** | PHP/JS/CSS-Dateien auf externe Script-/Link-Tags |

## Erkannte Dienst-Kategorien

- **Analytics/Tracking** – Google Analytics, Tag Manager, Matomo, Hotjar, Facebook Pixel, Microsoft Clarity
- **Kontaktformular** – Contact Form 7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms
- **Newsletter/E-Mail** – Mailchimp, Brevo, MailPoet, KlickTipp
- **Anti-Spam/Captcha** – Google reCAPTCHA, hCaptcha, Akismet, Antispam Bee
- **Social/Extern** – Facebook, Twitter/X, Instagram, Jetpack, Share-Buttons
- **Karten/Standort** – Google Maps, OpenStreetMap, Leaflet
- **Fonts/CDN** – Google Fonts, Font Awesome, Adobe Typekit, jsDelivr, cdnjs
- **Video/Embed** – YouTube, Vimeo, Spotify
- **E-Commerce/Zahlung** – WooCommerce, Stripe, PayPal, Klarna
- **Chat/Support** – Tawk.to, Tidio, Crisp, Zendesk, HubSpot, Userlike
- **Kommentare** – Disqus
- **Datenschutz-Tool** – Complianz, Borlabs Cookie, Real Cookie Banner, CookieYes
- **Page Builder** – Elementor (externe Google Fonts)
- **Sicherheit** – Wordfence, Sucuri (externe API-Verbindungen)
- **Backup/Cloud** – UpdraftPlus, BackWPup (Cloud-Uploads)
- **Werbung** – Google AdSense, Google Ads

## Installation & Nutzung

### Variante 1: Standalone-Skript (empfohlen für einmalige Scans)

1. `scan-privacy-services.php` ins WordPress-Root oder per WP-CLI aufrufen:

```bash
# Über WP-CLI
wp eval-file scan-privacy-services.php

# Oder direkt im WordPress-Verzeichnis
php scan-privacy-services.php
```

**Ausgabe:** Formatierter Konsolenbericht + optionaler JSON/CSV-Export.

### Variante 2: WP-CLI Kommando (empfohlen für regelmäßige Scans)

1. `wp-cli-privacy-command.php` nach `wp-content/mu-plugins/` kopieren
2. Verfügbare Befehle:

```bash
# Standard-Report (Tabelle)
wp privacy scan

# JSON-Ausgabe
wp privacy scan --format=json

# CSV-Ausgabe
wp privacy scan --format=csv

# In Datei exportieren
wp privacy scan --export=json
wp privacy scan --export=csv

# Nur eine bestimmte Site scannen
wp privacy scan --site=3

# Nach Kategorie filtern
wp privacy scan --category=Analytics

# Kategorien anzeigen
wp privacy categories
```

## Beispiel-Ausgabe

```
╔══════════════════════════════════════════════════════════════════╗
║   WP MULTISITE – DATENSCHUTZ-RELEVANTE DIENSTE (Scan-Report)   ║
╚══════════════════════════════════════════════════════════════════╝

Scan-Datum: 2026-03-18 10:00:00 UTC
Anzahl Sites: 5

┌─ Site #1: Hauptseite
│  URL: https://example.com
│
│  ▸ Analytics/Tracking
│    • Google Analytics / Tag Manager
│      ↳ Plugin: google-site-kit/google-site-kit.php
│      ↳ Post-Content: "Startseite" (ID 2)
│  ▸ Fonts/CDN
│    • Google Fonts (extern geladen)
│      ↳ Theme-Datei: /header.php
│  ▸ Kontaktformular
│    • Contact Form 7
│      ↳ Plugin: contact-form-7/wp-contact-form-7.php
│  ▸ Video/Embed
│    • YouTube Embed
│      ↳ Post-Content: "Über uns" (ID 15)
│
└───────────────────────────────────────────────────────────────────

┌─ Site #2: Kreisverband Musterstadt
│  URL: https://example.com/musterstadt
│
│  ✓ Keine datenschutzrelevanten Dienste erkannt.
│
└───────────────────────────────────────────────────────────────────
```

## Erweiterung

Neue Dienste können einfach hinzugefügt werden:

### Plugin-Erkennung erweitern
In `PrivacyServiceRegistry::get_plugin_map()` einen Eintrag hinzufügen:
```php
'mein-plugin-slug' => ['name' => 'Mein Dienst', 'category' => 'Kategorie'],
```

### Content-Pattern erweitern
In `PrivacyServiceRegistry::get_content_patterns()` ein Regex hinzufügen:
```php
'/mein-dienst\.example\.com/i' => ['name' => 'Mein Dienst', 'category' => 'Kategorie'],
```

### Options-Key erweitern
In `PrivacyServiceRegistry::get_option_patterns()`:
```php
'mein_api_key' => ['name' => 'Mein Dienst', 'category' => 'Kategorie'],
```

## Sicherheitshinweise

- Das Standalone-Skript prüft auf Super-Admin-Rechte (außer bei WP-CLI)
- **Nach dem Scan die Datei `scan-privacy-services.php` wieder aus dem Webroot entfernen!**
- JSON/CSV-Exporte werden im WordPress-Root gespeichert – ggf. per `.htaccess` schützen oder sofort herunterladen und löschen
- Keine Daten werden an externe Server gesendet – der Scan ist rein lokal

## Limitierungen

- Erkennt nur **bekannte** Dienste aus der Registry – unbekannte externe Aufrufe in Custom-Code werden nicht erkannt
- Post-Content-Scan ist auf 500 Posts/Pages pro Site limitiert (Performance)
- Theme-Datei-Scan ist auf 200 Dateien limitiert
- Dynamisch per JavaScript nachgeladene externe Ressourcen werden nicht erkannt (dafür wäre ein Browser-basierter Scan nötig)
