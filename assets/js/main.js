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
    function current() {
      var left = track.scrollLeft, best = 0, dist = Infinity;
      items.forEach(function (it, i) {
        var d = Math.abs(it.offsetLeft - track.offsetLeft - parseFloat(getComputedStyle(track).paddingLeft || 0) - left);
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
      var pad = parseFloat(getComputedStyle(track).paddingLeft || 0);
      track.scrollTo({ left: items[i].offsetLeft - track.offsetLeft - pad, behavior: "smooth" });
    }
    if (prev) prev.addEventListener("click", function () { go(current() - 1); });
    if (next) next.addEventListener("click", function () { go(current() + 1); });
    var t;
    track.addEventListener("scroll", function () { clearTimeout(t); t = setTimeout(update, 60); }, { passive: true });
    window.addEventListener("resize", update);
    update();
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
        f.referrerPolicy = "no-referrer-when-downgrade";
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
    var arr = $("[data-date-arrival]", form), dep = $("[data-date-departure]", form);
    var ts = $("[data-form-ts]", form);
    if (ts) ts.value = String(Date.now());
    function iso(d) { return d.toISOString().slice(0, 10); }
    var today = new Date(); today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
    arr.min = iso(today); dep.min = iso(today);
    arr.addEventListener("change", function () {
      if (!arr.value) return;
      var n = new Date(arr.value); n.setDate(n.getDate() + 1);
      dep.min = iso(n);
      if (dep.value && dep.value <= arr.value) dep.value = "";
    });
    function say(msg, ok) { status.textContent = msg; status.className = "form-status " + (ok ? "is-ok" : "is-err"); }
    var q = /[?&]form=(sent|error)/.exec(location.search);
    if (q) say(form.getAttribute(q[1] === "sent" ? "data-msg-ok" : "data-msg-err"), q[1] === "sent");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (dep.value <= arr.value) { say(form.getAttribute("data-msg-dates"), false); dep.focus(); return; }
      btn.disabled = true; say(form.getAttribute("data-msg-sending"), true);
      fetch(form.action, { method: "POST", body: new FormData(form), headers: { "Accept": "application/json" } })
        .then(function (r) { return r.json().catch(function () { return { ok: false }; }); })
        .then(function (d) {
          if (d && d.ok) { form.reset(); if (ts) ts.value = String(Date.now()); say(form.getAttribute("data-msg-ok"), true); }
          else say(form.getAttribute(d && d.error === "dates" ? "data-msg-dates" : "data-msg-err"), false);
        })
        .catch(function () { say(form.getAttribute("data-msg-err"), false); })
        .then(function () { btn.disabled = false; });
    });
  }

  /* 9. Optional analytics — only if an ID is configured (add a consent banner first!) */
  function initOptionalAnalytics() {
    if (!config.GOOGLE_ANALYTICS_ID) return;
    var s = document.createElement("script");
    s.async = true; s.src = "https://www.googletagmanager.com/gtag/js?id=" + config.GOOGLE_ANALYTICS_ID;
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    function gtag() { window.dataLayer.push(arguments); }
    gtag("js", new Date()); gtag("config", config.GOOGLE_ANALYTICS_ID);
  }

  function init() {
    wireBookingLinks(); wireContact(); initMobileMenu(); initLangMenu();
    initGallerySlider(); initLightbox(); initMap(); initContactForm(); initOptionalAnalytics();
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init); else init();
})();
