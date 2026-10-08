/**
 * VILLA MOREA — SITE CONFIGURATION
 * ---------------------------------------------------------------
 * Edit ONLY this file to update the domain, contact details and
 * the booking engine URL across the entire website.
 *
 * IMPORTANT: after editing WEBSITE_DOMAIN or BOOKING_ENGINE_URL,
 * also update:
 *   - the <link rel="canonical"> and hreflang tags in every HTML file
 *   - sitemap.xml
 *   - robots.txt (Sitemap: line)
 * This file only controls runtime behaviour (links built by
 * main.js, optional analytics). It cannot rewrite static HTML.
 * See README.md, section "Changing the domain", for full steps.
 * ---------------------------------------------------------------
 */

window.VILLA_MOREA_CONFIG = {

  /* ---------------------------------------------------------
   * 1. DOMAIN (informational only — see README "Changing the domain")
   * Replace with your real domain once it is registered/pointed
   * at Papaki hosting. Do NOT include a trailing slash.
   * ------------------------------------------------------- */
  WEBSITE_DOMAIN: "https://www.YOUR-DOMAIN.gr",

  /* ---------------------------------------------------------
   * 2. OFFICIAL DIRECT BOOKING ENGINE (Hotelyzer)
   * The URL is ALSO written directly into every HTML button (so
   * the buttons work without JavaScript and are crawlable). If you
   * change it here, also find-and-replace it in the HTML files.
   * Confirmed from the source material provided — do not change
   * unless the property switches booking engine providers.
   * ------------------------------------------------------- */
  BOOKING_ENGINE_URL: "https://villamorea.hotelyzer.gr/en",

  /* ---------------------------------------------------------
   * 3. CONTACT DETAILS
   * Fill these in when confirmed. Empty "" = the item stays
   * hidden on the site (no placeholder text is ever shown).
   * A WhatsApp number also turns on the floating WhatsApp button.
   * ------------------------------------------------------- */
  CONTACT_EMAIL: "info@villa-morea.com",
  CONTACT_PHONE: "+30 210 300 2211",
  CONTACT_WHATSAPP: "",           // full international number, digits only, e.g. "306900000000"

  /* ---------------------------------------------------------
   * 4. LOCATION
   * The Airbnb listing confirms the general area only:
   * "Mournies, Greece" — about 4 km from Chania's historic
   * centre / Venetian Harbour and about 5 km from area beaches.
   * No exact street address or GPS pin was disclosed in the
   * source material, so no coordinates are set here.
   * INFORMATION REQUIRED before enabling a map pin or precise
   * driving directions.
   * ------------------------------------------------------- */
  // Optional exact pin, e.g. "https://maps.app.goo.gl/XXXXXXXX".
  // Empty = the "Open in Google Maps" button shows the Mournies area.
  GOOGLE_MAPS_URL: "",
  LOCATION_AREA: "Mournies, Chania, Crete",

  /* ---------------------------------------------------------
   * 5. OPTIONAL ANALYTICS / TRACKING
   * Leave blank to load NOTHING and show no cookie banner.
   * If you add an ID here later, you must also add a
   * GDPR-compliant consent banner BEFORE any non-essential
   * script fires. See README.md, "Adding analytics later".
   * ------------------------------------------------------- */
  GOOGLE_ANALYTICS_ID: "",   // e.g. "G-XXXXXXXXXX"
  META_PIXEL_ID: "",         // e.g. "000000000000000"

  /* ---------------------------------------------------------
   * 6. MANAGEMENT CREDIT (footer)
   * Only shown if MANAGED_BY_HOTELYZER is set to true.
   * ------------------------------------------------------- */
  MANAGED_BY_HOTELYZER: true,
  HOTELYZER_URL: "https://hotelyzer.gr/",

  /* ---------------------------------------------------------
   * 7. PROPERTY REGISTRATION NUMBER
   * Confirmed in the Airbnb listing ("Registration Details").
   * Displayed in the footer for legal transparency.
   * ------------------------------------------------------- */
  PROPERTY_REGISTRATION_NUMBER: "1252589"
};
