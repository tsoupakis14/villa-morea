/**
 * VILLA MOREA — MAIN SITE SCRIPT (v3)
 * No dependencies, no build step. The site works without JS:
 * booking links, maps link and language links are plain HTML.
 * This script only adds: contact details from config, mobile menu,
 * gallery slider + lightbox, click-to-load map, language dropdown.
 */
(function () {
  "use strict";
  var config = window.VILLA_MOREA_CONFIG || {};
  var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

  function $(s, root) { return (root || document).querySelector(s); }
  function $$(s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); }

  /* Keep keyboard focus inside an open dialog */
  function trapFocus(container, e) {
    if (e.key !== "Tab") return;
    var items = $$(FOCUSABLE, container).filter(function (el) { return el.offsetParent !== null; });
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  /* 1. Booking links (already in HTML; config can override) */
  function wireBookingLinks() {
    if (!config.BOOKING_ENGINE_URL) return;
    $$("[data-booking-link]").forEach(function (a) { a.href = config.BOOKING_ENGINE_URL; });
  }

  /* 2. Contact details — rows stay hidden until a value exists */
  function wireContact() {
    var email = (config.CONTACT_EMAIL || "").trim();
    var phone = (config.CONTACT_PHONE || "").trim();
    var wa = (config.CONTACT_WHATSAPP || "").replace(/\D/g, "");
    if (email) {
      $$("[data-contact-email]").forEach(function (a) { a.href = "mailto:" + email; a.textContent = email; });
      $$('[data-contact-row="email"]').forEach(function (r) { r.hidden = false; });
    }
    if (phone) {
      $$("[data-contact-phone]").forEach(function (a) { a.href = "tel:" + phone.replace(/[^\d+]/g, ""); a.textContent = phone; });
      $$('[data-contact-row="phone"]').forEach(function (r) { r.hidden = false; });
    }
    if (wa) {
      $$("[data-contact-whatsapp]").forEach(function (a) { a.href = "https://wa.me/" + wa; });
      $$('[data-contact-row="whatsapp"]').forEach(function (r) { r.hidden = false; });
      $$("[data-whatsapp-fab]").forEach(function (b) { b.hidden = false; });
    }
    if (config.GOOGLE_MAPS_URL) {
      $$("[data-maps-link]").forEach(function (a) { a.href = config.GOOGLE_MAPS_URL; });
    }
  }

  /* 3. Mobile menu (dialog with focus trap) */
  function initMobileMenu() {
    var toggle = $("[data-menu-toggle]"), menu = $("[data-mobile-menu]");
    if (!toggle || !menu) return;
    var closeBtn = $("[data-menu-close]", menu);
    function open() {
      menu.hidden = false;
      requestAnimationFrame(function () { menu.setAttribute("data-open", "true"); });
      toggle.setAttribute("aria-expanded", "true");
      document.body.classList.add("is-locked");
      setTimeout(function () { (closeBtn || menu).focus(); }, 50);
    }
    function close(returnFocus) {
      menu.setAttribute("data-open", "false");
      toggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("is-locked");
      setTimeout(function () { if (menu.getAttribute("data-open") === "false") menu.hidden = true; }, 360);
      if (returnFocus !== false) toggle.focus();
    }
    toggle.addEventListener("click", open);
    if (closeBtn) closeBtn.addEventListener("click", function () { close(); });
    menu.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
      trapFocus(menu, e);
    });
    $$("a", menu).forEach(function (a) { a.addEventListener("click", function () { close(false); }); });
    window.addEventListener("resize", function () { if (window.innerWidth >= 960 && menu.getAttribute("data-open") === "true") close(false); });
  }

  /* 4. Language dropdown: close on outside click / Escape */
  function initLangMenu() {
    $$("[data-lang-menu]").forEach(function (d) {
      document.addEventListener("click", function (e) { if (d.open && !d.contains(e.target)) d.open = false; });
      d.addEventListener("keydown", function (e) { if (e.key === "Escape" && d.open) { d.open = false; d.querySelector("summary").focus(); } });
    });
  }

  /* 5. Gallery slider (phones) */
  function initGallerySlider() {
    var gallery = $("[data-gallery]");
    if (!gallery) return;
    var track = $("[data-gallery-track]", gallery);
    var items = $$(".gallery__item", track);
    var count = $("[data-gallery-count]", gallery);
    var prev = $("[data-gallery-prev]", gallery), next = $("[data-gallery-next]", gallery);
    var pad = 0;
    function measure() { pad = parseFloat(getComputedStyle(track).paddingLeft || 0) || 0; }
    function current() {
      var left = track.scrollLeft, base = track.offsetLeft + pad, best = 0, dist = Infinity;
      items.forEach(function (it, i) {
        var d = Math.abs(it.offsetLeft - base - left);
        if (d < dist) { dist = d; best = i; }
      });
      return best;
    }
    function update() {
      var i = current();
      if (count) count.textContent = (i + 1) + " / " + items.length;
      if (prev) prev.disabled = i === 0;
      if (next) next.disabled = i === items.length - 1 || track.scrollLeft + track.clientWidth >= track.scrollWidth - 4;
    }
    function go(i) {
      i = Math.max(0, Math.min(items.length - 1, i));
      track.scrollTo({ left: items[i].offsetLeft - track.offsetLeft - pad, behavior: "smooth" });
    }
    if (prev) prev.addEventListener("click", function () { go(current() - 1); });
    if (next) next.addEventListener("click", function () { go(current() + 1); });
    var t;
    track.addEventListener("scroll", function () { clearTimeout(t); t = setTimeout(update, 60); }, { passive: true });
    window.addEventListener("resize", function () { measure(); update(); });
    // first measurement after layout is done (avoids a forced reflow during page load)
    window.addEventListener("load", function () { requestAnimationFrame(function () { measure(); update(); }); });
  }

  /* 6. Lightbox (accessible dialog) */
  function initLightbox() {
    var triggers = $$("[data-lightbox-trigger]"), lb = $("[data-lightbox]");
    if (!triggers.length || !lb) return;
    var imgEl = $("[data-lightbox-image]", lb), cap = $("[data-lightbox-caption]", lb);
    var closeBtn = $("[data-lightbox-close]", lb);
    var items = triggers.map(function (t) { var im = t.querySelector("img"); return { src: t.getAttribute("data-full"), alt: im ? im.alt : "" }; });
    var idx = 0, lastFocus = null;
    function show(i) {
      idx = (i + items.length) % items.length;
      imgEl.src = items[idx].src; imgEl.alt = items[idx].alt;
      cap.textContent = items[idx].alt + "  ·  " + (idx + 1) + " / " + items.length;
      var n = items[(idx + 1) % items.length]; (new Image()).src = n.src; // preload next
    }
    function open(i) { lastFocus = document.activeElement; show(i); lb.hidden = false; document.body.classList.add("is-locked"); closeBtn.focus(); }
    function close() { lb.hidden = true; document.body.classList.remove("is-locked"); if (lastFocus) lastFocus.focus(); }
    triggers.forEach(function (t, i) { t.addEventListener("click", function () { open(i); }); });
    closeBtn.addEventListener("click", close);
    $("[data-lightbox-prev]", lb).addEventListener("click", function () { show(idx - 1); });
    $("[data-lightbox-next]", lb).addEventListener("click", function () { show(idx + 1); });
    lb.addEventListener("click", function (e) { if (e.target === lb) close(); });
    lb.addEventListener("keydown", function (e) {
      if (e.key === "Escape") close();
      else if (e.key === "ArrowLeft") show(idx - 1);
      else if (e.key === "ArrowRight") show(idx + 1);
      trapFocus(lb, e);
    });
    var x0 = null;
    lb.addEventListener("touchstart", function (e) { x0 = e.changedTouches[0].clientX; }, { passive: true });
    lb.addEventListener("touchend", function (e) {
      if (x0 === null) return;
      var d = e.changedTouches[0].clientX - x0;
      if (d > 40) show(idx - 1); else if (d < -40) show(idx + 1);
      x0 = null;
    }, { passive: true });
  }

  /* 7. Click-to-load Google map (GDPR: nothing loads before the click) */
  function initMap() {
    $$("[data-map-embed]").forEach(function (box) {
      var btn = $("[data-map-load]", box);
      if (!btn) return;
      btn.addEventListener("click", function () {
        var f = document.createElement("iframe");
        f.src = box.getAttribute("data-src");
        f.title = box.getAttribute("data-title") || "Map";
        f.loading = "lazy";
        f.referrerPolicy = "strict-origin-when-cross-origin";
        f.setAttribute("allowfullscreen", "");
        box.appendChild(f);
        box.classList.add("is-loaded");
      });
    });
  }

  /* 8. Contact form — AJAX submit to /contact.php (works without JS too) */
  function initContactForm() {
    var form = $("[data-contact-form]");
    if (!form) return;
    var status = $("[data-form-status]", form);
    var btn = $('button[type="submit"]', form);
    var ts = $("[data-form-ts]", form);
    if (ts) ts.value = String(Date.now());
    function say(msg, ok) { status.textContent = msg; status.className = "form-status " + (ok ? "is-ok" : "is-err"); }
    var q = /[?&]form=(sent|error)/.exec(location.search);
    if (q) say(form.getAttribute(q[1] === "sent" ? "data-msg-ok" : "data-msg-err"), q[1] === "sent");
    /* Google reCAPTCHA v3 — loaded only when the visitor starts using the form */
    var siteKey = (config.RECAPTCHA_SITE_KEY || "").trim(), rcPromise = null;
    function loadRecaptcha() {
      if (!siteKey) return Promise.resolve(null);
      if (rcPromise) return rcPromise;
      rcPromise = new Promise(function (resolve) {
        var sc = document.createElement("script");
        sc.src = "https://www.google.com/recaptcha/api.js?render=" + encodeURIComponent(siteKey);
        sc.async = true;
        sc.onload = function () { window.grecaptcha.ready(function () { resolve(window.grecaptcha); }); };
        sc.onerror = function () { resolve(null); };
        document.head.appendChild(sc);
      });
      return rcPromise;
    }
    function getToken() {
      return loadRecaptcha().then(function (g) {
        if (!g) return "";
        return g.execute(siteKey, { action: "contact" }).then(function (t) { return t; }, function () { return ""; });
      });
    }
    if (siteKey) {
      $$("[data-recaptcha-note]").forEach(function (n) { n.hidden = false; });
      form.addEventListener("focusin", loadRecaptcha, { once: true });
    }
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      btn.disabled = true; say(form.getAttribute("data-msg-sending"), true);
      getToken().then(function (token) {
        var fd = new FormData(form);
        if (token) fd.append("g-recaptcha-response", token);
        return fetch(form.action, { method: "POST", body: fd, headers: { "Accept": "application/json" } });
      })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (d) {
          if (d && d.ok) { var ty = form.getAttribute("data-thanks"); if (ty) { window.location.href = ty; return; } form.reset(); if (ts) ts.value = String(Date.now()); say(form.getAttribute("data-msg-ok"), true); }
          else say(form.getAttribute("data-msg-err"), false);
        })
        .catch(function () { say(form.getAttribute("data-msg-err"), false); })
        .then(function () { btn.disabled = false; });
    });
  }

  /* 9. Google Analytics 4 — loaded ONLY after the visitor clicks "Accept".
     Nothing loads and no banner shows while GOOGLE_ANALYTICS_ID is empty. */
  function initConsent() {
    var id = config.GOOGLE_ANALYTICS_ID;
    if (!id) return;
    var KEY = "vm_consent", MAX_AGE = 365 * 24 * 3600 * 1000;
    var banner = document.querySelector("[data-cookie-banner]");
    var loaded = false;
    function read() {
      try { var v = JSON.parse(localStorage.getItem(KEY) || "null");
        return v && v.t && (Date.now() - v.t) < MAX_AGE ? v.v : null; } catch (e) { return null; }
    }
    function save(v) { try { localStorage.setItem(KEY, JSON.stringify({ v: v, t: Date.now() })); } catch (e) {} }
    function loadGA() {
      if (loaded) return; loaded = true;
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag("consent", "default", { analytics_storage: "granted", ad_storage: "denied", ad_user_data: "denied", ad_personalization: "denied" });
      window.gtag("js", new Date());
      window.gtag("config", id, { allow_google_signals: false, allow_ad_personalization_signals: false });
      var s = document.createElement("script");
      s.async = true; s.src = "https://www.googletagmanager.com/gtag/js?id=" + encodeURIComponent(id);
      document.head.appendChild(s);
    }
    function clearGA() {
      var host = location.hostname.replace(/^www\./, "");
      document.cookie.split(";").forEach(function (c) {
        var n = c.split("=")[0].trim();
        if (n === "_ga" || n.indexOf("_ga_") === 0 || n === "_gid") {
          ["", "; domain=" + host, "; domain=." + host].forEach(function (d) {
            document.cookie = n + "=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/" + d;
          });
        }
      });
    }
    function show() { if (banner) banner.hidden = false; }
    function hide() { if (banner) banner.hidden = true; }
    var acc = document.querySelector("[data-cookie-accept]"), rej = document.querySelector("[data-cookie-reject]");
    if (acc) acc.addEventListener("click", function () { save("granted"); hide(); loadGA(); });
    if (rej) rej.addEventListener("click", function () {
      var was = loaded; save("denied"); hide(); clearGA();
      if (was) location.reload(); /* fully stop GA if it was running */
    });
    document.querySelectorAll("[data-cookie-row]").forEach(function (r) { r.hidden = false; });
    document.querySelectorAll("[data-cookie-settings]").forEach(function (b) { b.addEventListener("click", show); });
    var choice = read();
    if (choice === "granted") loadGA();
    else if (choice !== "denied") show();
  }

  function init() {
    wireBookingLinks(); wireContact(); initMobileMenu(); initLangMenu();
    initGallerySlider(); initLightbox(); initMap(); initContactForm(); initConsent();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
