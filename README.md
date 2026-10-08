# Villa Morea — Website

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

1. **Domain:** κάντε find & replace σε ΟΛΑ τα αρχεία το `www.YOUR-DOMAIN.gr`
   με το πραγματικό domain (HTML, `sitemap.xml`, `robots.txt`).
2. **Φόρμα επικοινωνίας (`contact.php`, χρειάζεται PHP — δουλεύει στο Papaki, όχι σε Cloudflare/GitHub Pages):**
   - `$TO`: λίστα με τα email που λαμβάνουν τα αιτήματα. Τώρα: `['tsoupakis14@gmail.com']`.
     Για δεύτερο παραλήπτη: `['tsoupakis14@gmail.com', 'άλλο@email.gr']`.
   - Προτείνεται **SMTP**: φτιάξτε στο Papaki ένα mailbox στο domain του site (π.χ. `noreply@villa-morea.gr`)
     και συμπληρώστε στο `$SMTP` host / port / user / pass (Papaki: συνήθως `mail.<domain>`, 465/ssl ή 587/tls).
     Αν μείνει κενό, χρησιμοποιείται το `mail()` του server.
   - Προστασία spam: κρυφό πεδίο, ελάχιστος χρόνος 3", έως 5 αιτήματα/ώρα ανά IP.
   - Κάντε μια δοκιμαστική αποστολή μετά το ανέβασμα (ελέγξτε και τον φάκελο Spam).
3. **Στοιχεία επικοινωνίας:** τηλέφωνο +30 210 300 2211 και info@villa-morea.com είναι ήδη στο footer.
   Για WhatsApp (προαιρετικό) συμπληρώστε `CONTACT_WHATSAPP` στο `assets/js/config.js`.
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

## Φωτογραφίες

Κάθε φωτογραφία έχει μια full έκδοση και μια `-800.webp` για κινητά (srcset).
Αν αντικαταστήσετε φωτογραφία, κρατήστε το ίδιο όνομα και φτιάξτε και τη `-800` έκδοση.
Το `assets/images/og/` έχει το JPG 1200×630 για τα previews σε Facebook/WhatsApp.

## Επεξεργασία κειμένων

Οι σελίδες παράγονται από τον φάκελο `villa-morea-source/` (δεν ανεβαίνει στον server).
Αλλάζετε τα κείμενα στα `content_*.py` και τρέχετε `python3 build.py`.
Εναλλακτικά μπορείτε να επεξεργαστείτε απευθείας τα HTML.

## Analytics

Δεν φορτώνεται κανένα tracking. Αν προστεθεί Google Analytics στο `config.js`,
πρέπει πρώτα να μπει banner συγκατάθεσης και να ενημερωθεί η Πολιτική Cookies.
