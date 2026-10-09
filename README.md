# Villa Morea — Website (www.villamorea.gr)

Στατικό site σε 4 γλώσσες (EN / EL / DE / IT). Μόνο HTML, CSS και JS,
χωρίς βάση δεδομένων. Ανεβαίνει όπως είναι σε Apache hosting (Papaki).

## Δομή

| URL | Σελίδα |
|---|---|
| `/`, `/el/`, `/de/`, `/it/` | Αρχική σελίδα ανά γλώσσα |
| `/privacy-policy.html`, `/terms.html` (+ `/el/`, `/de/`, `/it/`) | Νομικά (noindex) |
| `/contact.php` | Αποστολή της φόρμας επικοινωνίας με email |
| `/404.html`, `/el/404.html`, ... | Σελίδα 404 ανά γλώσσα (ρυθμίζεται στο `.htaccess`) |

## Πριν ανέβει live

1. **Domain:** `www.villamorea.gr` — έχει ήδη περαστεί σε όλα τα αρχεία (canonical, hreflang, sitemap, robots, schema).
2. **Φόρμα επικοινωνίας (`contact.php`, χρειάζεται PHP — δουλεύει στο Papaki, όχι σε Cloudflare/GitHub Pages):**
   - `$TO`: λίστα με τα email που λαμβάνουν τα αιτήματα. Τώρα: `['villamorea@gmail.com']`.
     Για δεύτερο παραλήπτη: `['villamorea@gmail.com', 'άλλο@email.gr']`.
   - Μετά την αποστολή ο επισκέπτης πηγαίνει στη σελίδα **Ευχαριστούμε** (`/thank-you.html`, `/el/thank-you.html` κ.λπ., noindex).
     Στέλνει στο Analytics το event `generate_lead` (σημειώστε το ως Key event στο GA4).
   - **Αυτόματο email επιβεβαίωσης** στον επισκέπτη, στη γλώσσα της σελίδας (`$AUTOREPLY = true`).
     Όταν ο επισκέπτης απαντά σε αυτό, η απάντηση πηγαίνει στο `$AUTOREPLY_REPLY_TO` (τώρα `info@villamorea.gr` — πρέπει να υπάρχει το mailbox ή forward).
     Γράφει ρητά ότι δεν αποτελεί κράτηση (με link στο online booking) και δεν περιέχει το κείμενο του μηνύματος (ασφάλεια).
   - Προτείνεται **SMTP**: φτιάξτε στο Papaki ένα mailbox στο domain του site (π.χ. `noreply@villamorea.gr`)
     και συμπληρώστε στο `$SMTP` host / port / user / pass (Papaki: συνήθως `mail.<domain>`, 465/ssl ή 587/tls).
     Αν μείνει κενό, χρησιμοποιείται το `mail()` του server.
   - Προστασία spam: κρυφό πεδίο, ελάχιστος χρόνος 3", έως 5 αιτήματα/ώρα ανά IP **και Google reCAPTCHA v3**:
     φτιάξτε κλειδιά στο https://www.google.com/recaptcha/admin (τύπος v3, domain `villamorea.gr`),
     βάλτε το **Site key** στο `assets/js/config.js` → `RECAPTCHA_SITE_KEY` και το **Secret key**
     στο `contact.php` → `$RECAPTCHA_SECRET`. Κενά = reCAPTCHA εκτός (η φόρμα δουλεύει κανονικά).
   - Plesk: ενεργοποιήστε **DKIM** (Mail → Mail Settings) και ελέγξτε ότι υπάρχει **SPF** record
     στο DNS του domain — βοηθούν πολύ να μην πηγαίνουν τα email στα spam.
   - Κάντε μια δοκιμαστική αποστολή μετά το ανέβασμα (ελέγξτε και τον φάκελο Spam).
3. **Στοιχεία επικοινωνίας:** τηλέφωνο +30 210 300 2211 και info@villamorea.gr είναι ήδη στο footer.
4. **Χάρτης:** δείχνει ήδη την πινέζα «Villa Morea With Pool Near Chania City» (φορτώνει μόνο με κλικ).
5. **SSL:** το `.htaccess` κάνει redirect σε `https://www.` με ένα βήμα.
   Χρειάζεται ενεργό SSL στο domain.
6. Νομικά κείμενα (Πολιτική Απορρήτου, Όροι & Ακυρώσεις): καλό είναι να τα δει νομικός.

## Booking

Τα κουμπιά κράτησης έχουν το URL `https://villamorea.hotelyzer.gr/en`
γραμμένο απευθείας στο HTML, ώστε να δουλεύουν και χωρίς JavaScript.
Για αλλαγή: find & replace αυτού του URL σε όλα τα HTML (και στο `config.js`).

## CSS & Cache (σημαντικό)

Το CSS (`assets/css/styles.css`) μπαίνει **inline** μέσα σε κάθε σελίδα για ταχύτερο φόρτωμα.
Αν αλλάξετε το `styles.css`, τρέξτε `python3 build.py` (φάκελος source) για να περάσει στις σελίδες.
Το JS φορτώνεται ως `main.js?v=14`· σε αλλαγή του αυξήστε τον αριθμό (V στο `build.py`).

## Φωτογραφίες

Κάθε φωτογραφία υπάρχει σε μεγέθη -480, -768, -1080 και πλήρες, και ο browser διαλέγει το κατάλληλο.

## Γραμματοσειρές (GDPR)

Όλες φορτώνονται από το `/assets/fonts/` (καμία κλήση στη Google).
Aboreto + Work Sans για λατινικούς χαρακτήρες, EB Garamond + Commissioner
για ελληνικά. Τα ελληνικά αρχεία κατεβαίνουν μόνο όταν η σελίδα έχει ελληνικό κείμενο.

Το `assets/images/og/` έχει το JPG 1200×630 για τα previews σε Facebook/WhatsApp.

## Επεξεργασία κειμένων

Οι σελίδες παράγονται από τον φάκελο `villa-morea-source/` (δεν ανεβαίνει στον server).
Αλλάζετε τα κείμενα στα `content_*.py` και τρέχετε `python3 build.py`.
Εναλλακτικά μπορείτε να επεξεργαστείτε απευθείας τα HTML.

## Google Analytics & cookies

- Φτιάξτε GA4 property στο https://analytics.google.com και βάλτε το **Measurement ID** (`G-XXXXXXXXXX`)
  στο `assets/js/config.js` → `GOOGLE_ANALYTICS_ID`.
- Μόλις μπει το ID, εμφανίζεται αυτόματα **banner cookies** (Αποδοχή / Απόρριψη) σε 4 γλώσσες.
  Το GA4 φορτώνει **μόνο μετά την Αποδοχή**· με Απόρριψη δεν φορτώνει τίποτα.
  Η επιλογή κρατιέται 12 μήνες και αλλάζει από το «Ρυθμίσεις cookies» στο footer.
- Στο GA4: Admin → Data collection → Google signals off, Data retention 14 μήνες.
- Η Πολιτική Απορρήτου (ενότητα «Cookies και στατιστικά», `#cookies`) περιγράφει ήδη το GA4.

## Google Search Console

- Προσθέστε **Domain property** `villamorea.gr` στο https://search.google.com/search-console
  και περάστε το TXT record που δίνει η Google στο DNS του Plesk.
- Μετά την επιβεβαίωση: Sitemaps → υποβολή `https://www.villamorea.gr/sitemap.xml`.
