# BP UC & Mini Services

**BP UC & Mini Services** is a lightweight all-in-one WordPress plugin by **BytesPulse** that combines:

* Under Construction / Maintenance mode
* Custom login hardening
* Role-based redirects
* Lightweight custom SMTP
* Login branding
* Settings import/export
* Secure uninstall cleanup

Designed for **agency workflows, client websites, white-label installs, and lightweight production stacks**.

---

# 🇬🇧 English Documentation

## Overview

BP UC & Mini Services helps you manage the most common operational needs of a WordPress installation without requiring multiple plugins.

It is optimized for:

* agencies
* freelancers
* maintenance workflows
* client portals
* WooCommerce stores
* white-label deployments

The goal is to replace multiple plugins with **one clean lightweight suite**.

---

## Features

> **Latest version:** `v1.4.3`
> Includes the full **TOTP 2FA module**, recovery codes download/copy flow, and dedicated enable/disable handlers for maximum reliability.

### 1) Under Construction Mode

A premium animated under construction page with:

* enable / disable toggle
* logo support
* favicon support
* EL / EN language switch
* light / dark theme switch
* animated aurora background
* starfield canvas effect
* pulse animation
* countdown timer
* custom footer text
* custom contact email
* allow logged-in users bypass

Perfect for:

* new website launches
* redesigns
* temporary maintenance
* pre-launch landing states

---

### 2) Custom Login URL & Login Protection

Secure the default WordPress login flow.

Includes:

* custom login slug
* block direct `/wp-login.php`
* safe exceptions for logout/reset flows
* login logo branding
* uses the same logo as Under Construction automatically
* homepage redirect when clicking login logo
* works on:

  * login
  * lost password
  * reset password
  * custom slug login

---

### 3) Role-Based Login Redirects

After login, users can:

* stay on the same page
* go to a selected page
* go to wp-admin

Supported roles:

* Administrator
* Editor
* Author
* Contributor
* Subscriber
* Customer
* Client

Each role includes page dropdown selectors from existing WordPress pages.

---

### 4) Logout Redirects

Choose where users go after logout.

Supports:

* homepage
* selected page
* client portal landing pages
* login page redirects

---

### 5) Lightweight SMTP Mailer

A lightweight **custom SMTP module** similar to the **Other SMTP mode** of WP Mail SMTP.

Includes:

* SMTP enable / disable
* SMTP Host
* SMTP Port
* Encryption: None / SSL / TLS
* automatic default ports

  * None → 25
  * SSL → 465
  * TLS → 587
* Auto TLS
* SMTP Auth
* Username
* Password
* From Email
* From Name
* Force From Email
* Force From Name
* HTML email toggle
* test email

### SMTP Error Logging

Optional **error-only debug logging**:

* logs only failed emails
* no successful mail noise
* automatic cleanup after 30 days
* daily cleanup routine
* safe for production debugging

---

### 6) Import / Export Settings

Easily move plugin settings between sites.

Supports:

* export settings as JSON
* import settings JSON
* agency template reuse
* fast multi-client deployment

---

### 7) wp-config.php Constants Support

Sensitive SMTP settings can be securely overridden from `wp-config.php`.

Example:

```php
define('BP_UCMS_SMTP_HOST', 'mail.example.com');
define('BP_UCMS_SMTP_PORT', 587);
define('BP_UCMS_SMTP_USERNAME', 'user@example.com');
define('BP_UCMS_SMTP_PASSWORD', 'secret');
```

Ideal for:

* production environments
* Git-based deployments
* server-level secrets
* DevOps workflows

---

### 8) Two-Factor Authentication (2FA)

Per-user **TOTP-based 2FA** fully integrated into the custom login flow.

Includes:

* global 2FA module enable / disable
* per-user enable / disable from profile page
* QR code for any authenticator app
* manual secret key
* OTP verification before activation
* recovery codes
* copy recovery codes button
* download recovery codes as TXT
* regenerate recovery codes
* admin reset support
* compatible with custom login slug
* compatible with role redirects

Supported apps:

* Authy
* Google Authenticator
* Microsoft Authenticator
* Aegis
* 1Password
* Bitwarden
* any TOTP-compatible app

### Where 2FA data is stored

Stored securely per user inside `wp_usermeta`:

* `bp_ucms_2fa_enabled`
* `bp_ucms_2fa_secret`
* `bp_ucms_2fa_recovery_codes`
* `bp_ucms_2fa_created_at`

This keeps 2FA data isolated per account and follows WordPress best practices.

---

### 9) Secure Uninstall Cleanup

On uninstall the plugin can automatically remove:

* plugin settings
* scheduled cron tasks
* SMTP error logs

To preserve data:

```php
define('BP_UCMS_PRESERVE_DATA', true);
```

---

## Installation

1. Upload ZIP from WordPress Admin → Plugins → Add New → Upload Plugin
2. Activate plugin
3. Open **BP UC & Mini Services** menu
4. Configure modules as needed

---

## Recommended Use Cases

* agency starter stack
* WooCommerce customer portals
* staging websites
* maintenance mode sites
* white-label client dashboards
* SMTP without heavy plugins
* secure custom login deployments

---

# 🇬🇷 Ελληνική Τεκμηρίωση

## Περιγραφή

Το **BP UC & Mini Services** είναι ένα ελαφρύ all-in-one WordPress plugin της **BytesPulse**, σχεδιασμένο ώστε να καλύπτει τις πιο συχνές ανάγκες ενός site χωρίς πολλά διαφορετικά plugins.

Στόχος του είναι να προσφέρει:

* καλύτερο performance
* λιγότερα conflicts
* εύκολο maintenance
* agency-ready χρήση

---

## Λειτουργίες

### 1) Under Construction / Maintenance

Περιλαμβάνει πλήρως animated σελίδα με:

* ενεργοποίηση / απενεργοποίηση
* λογότυπο
* favicon
* αλλαγή γλώσσας EL / EN
* light / dark mode
* animated aurora
* starfield background
* pulse animation
* countdown
* footer text
* contact email
* εξαίρεση για logged-in users

Ιδανικό για:

* νέα site
* redesign
* maintenance εργασίες
* pre-launch κατάσταση

---

### 2) Custom Login URL & Προστασία

Προσφέρει ασφαλέστερο login flow:

* custom login slug
* απόκρυψη direct `/wp-login.php`
* σωστές εξαιρέσεις για logout / reset password
* custom branding logo στο login
* χρησιμοποιεί αυτόματα το ίδιο logo με το under construction

Λειτουργεί σε:

* login
* lost password
* reset password
* custom login slug

---

### 3) Redirects ανά Role

Μετά το login μπορείς να ορίσεις redirect για κάθε role:

* να μείνει στην ίδια σελίδα
* να πάει σε συγκεκριμένη σελίδα
* να πάει wp-admin

Υποστηρίζονται:

* Administrator
* Editor
* Author
* Contributor
* Subscriber
* Customer
* Client

Με dropdown από όλες τις διαθέσιμες σελίδες.

---

### 4) Logout Redirect

Ορίζεις πού θα πηγαίνει ο χρήστης μετά το logout.

Ιδανικό για:

* portals
* client dashboards
* login pages
* homepage επιστροφή

---

### 5) Lightweight Custom SMTP

Περιλαμβάνει ελαφρύ SMTP module αντίστοιχο του **Other SMTP mode**.

Πεδία:

* SMTP enable
* Host
* Port
* None / SSL / TLS
* αυτόματη αλλαγή default port
* Auto TLS
* Authentication
* Username
* Password
* From Email
* From Name
* Force sender
* HTML emails
* test email

### Error Log

Προαιρετικό debug logging:

* καταγράφει **μόνο σφάλματα αποστολής**
* δεν γεμίζει με επιτυχημένα emails
* αυτόματο cleanup μετά από **30 ημέρες**
* ασφαλές για production χρήση

---

### 6) Import / Export Ρυθμίσεων

Μπορείς να μεταφέρεις ρυθμίσεις μεταξύ sites.

Χρήσιμο για:

* agency templates
* multisite deployment
* γρήγορο setup πελατών

---

### 7) Υποστήριξη Constants από wp-config.php

Για καλύτερο security μπορείς να βάλεις SMTP credentials στο `wp-config.php`.

Παράδειγμα:

```php
define('BP_UCMS_SMTP_HOST', 'mail.example.com');
define('BP_UCMS_SMTP_PORT', 587);
define('BP_UCMS_SMTP_USERNAME', 'user@example.com');
define('BP_UCMS_SMTP_PASSWORD', 'secret');
```

Ιδανικό για production και Git deployments.

---

### 8) Two-Factor Authentication (2FA)

Πλήρες **TOTP 2FA ανά χρήστη**, ενσωματωμένο στο υπάρχον login flow.

Περιλαμβάνει:

* global ενεργοποίηση / απενεργοποίηση module
* ενεργοποίηση ανά χρήστη από το profile
* QR code για Authy / authenticator apps
* manual secret key
* verify code πριν την ενεργοποίηση
* recovery codes
* copy κουμπί
* download TXT
* regenerate recovery codes
* admin reset
* συμβατό με custom login slug
* συμβατό με redirects ανά role

Υποστηρίζει:

* Authy
* Google Authenticator
* Microsoft Authenticator
* Aegis
* 1Password
* Bitwarden
* οποιοδήποτε TOTP app

### Αποθήκευση δεδομένων 2FA

Τα δεδομένα αποθηκεύονται σωστά **ανά χρήστη στο `wp_usermeta`**:

* `bp_ucms_2fa_enabled`
* `bp_ucms_2fa_secret`
* `bp_ucms_2fa_recovery_codes`
* `bp_ucms_2fa_created_at`

---

### 9) Safe Uninstall Cleanup

Κατά την απεγκατάσταση καθαρίζει:

* settings
* cron jobs
* SMTP error logs

Για να διατηρηθούν:

```php
define('BP_UCMS_PRESERVE_DATA', true);
```

---

## Εγκατάσταση

1. Plugins → Add New → Upload Plugin
2. ανέβασε το ZIP
3. Activate
4. μπες στο menu **BP UC & Mini Services**

---

## Ιδανικό για

* agencies
* maintenance υπηρεσίες
* WooCommerce stores
* client portals
* white-label εγκαταστάσεις
* ελαφρύ SMTP
* ασφαλές custom login

---

## License

GPL v2 or later

---

## Author

**BytesPulse**
Innovative and tailored IT solutions
