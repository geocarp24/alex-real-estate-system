/* Pinnacle Email Capture Popup — 5s timer, bilingual EN/ES.
   Shows once per session; suppressed permanently only after subscribe.
   Skips on /get-my-offer/ (server-enqueue filter) and on mobile <480px.
   Posts to /agents/pinnacle_public.php action=subscribe_email. */
(function(){
  "use strict";
  if (window.__pnpPopupLoaded) return;
  window.__pnpPopupLoaded = true;

  var API = "/agents/pinnacle_public.php";
  // URL override for testing: append ?pnp_force=1 to any page URL to bypass all gates
  // and fire the popup in 500ms. Regular visitors are unaffected.
  var FORCE = /[?&]pnp_force=1/.test(location.search || "");
  var TRIGGER_MS = FORCE ? 500 : 5000;
  var K_SESSION_SEEN = "pnp_popup_seen";       // sessionStorage: shown this session (clears when tab closes)
  var K_SUBSCRIBED   = "pnp_popup_subscribed"; // localStorage: "1" once subscribed (permanent)

  // ---- Anti-annoyance pre-checks (bypassed by ?pnp_force=1) ----
  if (!FORCE) {
    try {
      if (localStorage.getItem(K_SUBSCRIBED) === "1") return;
      if (sessionStorage.getItem(K_SESSION_SEEN) === "1") return;
    } catch(e) {}
  }

  // ---- i18n ----
  var LANG = (navigator.language || "en").toLowerCase().indexOf("es") === 0 ? "es" : "en";
  var T = {
    en: {
      badge: "Wisconsin Homeowners",
      headline: "Thinking about selling? Know your options first.",
      subhead: "Free guides for homeowners facing foreclosure, inherited property, back taxes, or needing a fast sale — pros & cons of every path, plus monthly Wisconsin market updates. No pressure, unsubscribe anytime.",
      placeholder: "you@example.com",
      cta: "Send Me Free Guides",
      decline: "Maybe later",
      trust: "Free · no obligation · Wisconsin-specific",
      err_required: "Please enter your email",
      err_invalid: "Please enter a valid email",
      err_server: "Connection hiccup — try again.",
      success_title: "You're in!",
      success_msg: "Check your inbox — your first guide is on the way. You'll get monthly Wisconsin market updates and homeowner tips, no spam.",
      success_close: "Got it"
    },
    es: {
      badge: "Dueños de Casa en Wisconsin",
      headline: "¿Pensando en vender? Conoce tus opciones primero.",
      subhead: "Guías gratis para dueños en foreclosure, con casa heredada, atraso en taxes o que necesitan vender rápido — pros y contras de cada opción, más análisis mensual del mercado de Wisconsin. Sin compromiso, cancela cuando quieras.",
      placeholder: "tu@ejemplo.com",
      cta: "Recibir Guías Gratis",
      decline: "Tal vez después",
      trust: "Gratis · sin compromiso · específico para Wisconsin",
      err_required: "Ingresa tu email",
      err_invalid: "Ingresa un email válido",
      err_server: "Fallo de conexión — intenta otra vez.",
      success_title: "¡Listo!",
      success_msg: "Revisa tu inbox — tu primera guía viene en camino. Recibirás análisis mensual del mercado de Wisconsin y tips para dueños de casa, sin spam.",
      success_close: "Entendido"
    }
  };
  function t(k){ return (T[LANG] && T[LANG][k]) || T.en[k] || k; }

  // ---- DOM ----
  var root, modal, form, emailInput, errMsg, ctaBtn;
  var startTs = Date.now(); // for min-time honeypot check

  function build(){
    root = document.createElement("div");
    root.id = "pnp-backdrop";
    root.hidden = true;
    root.innerHTML = [
      '<div class="pnp-modal" role="dialog" aria-modal="true" aria-labelledby="pnp-headline">',
        '<button class="pnp-close" type="button" aria-label="Close">×</button>',
        '<div class="pnp-hero">',
          '<div class="pnp-badge">' + esc(t("badge")) + '</div>',
          '<h2 class="pnp-headline" id="pnp-headline">' + esc(t("headline")) + '</h2>',
          '<p class="pnp-subhead">' + esc(t("subhead")) + '</p>',
        '</div>',
        '<div class="pnp-body">',
          '<form class="pnp-form" novalidate>',
            '<input class="pnp-input" type="email" name="email" autocomplete="email" placeholder="' + esc(t("placeholder")) + '" required />',
            '<div class="pnp-error-msg" role="alert"></div>',
            '<button class="pnp-cta" type="submit">' + esc(t("cta")) + '</button>',
            '<input class="pnp-hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" />',
          '</form>',
          '<div class="pnp-footer">',
            '<span class="pnp-trust">' + esc(t("trust")) + '</span>',
            '<button class="pnp-decline" type="button">' + esc(t("decline")) + '</button>',
          '</div>',
        '</div>',
      '</div>'
    ].join("");
    document.body.appendChild(root);

    modal     = root.querySelector(".pnp-modal");
    form      = root.querySelector(".pnp-form");
    emailInput= root.querySelector('.pnp-input[type="email"]');
    errMsg    = root.querySelector(".pnp-error-msg");
    ctaBtn    = root.querySelector(".pnp-cta");

    root.querySelector(".pnp-close").addEventListener("click", dismiss);
    root.querySelector(".pnp-decline").addEventListener("click", dismiss);
    root.addEventListener("click", function(e){ if (e.target === root) dismiss(); });
    document.addEventListener("keydown", function(e){ if (!root.hidden && e.key === "Escape") dismiss(); });
    form.addEventListener("submit", onSubmit);
  }

  function esc(s){ return String(s).replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  }); }

  function show(){
    if (!root) build();
    root.hidden = false;
    // Mark as "seen this session" when popup becomes visible — clears when tab closes.
    if (!FORCE) { try { sessionStorage.setItem(K_SESSION_SEEN, "1"); } catch(e){} }
    setTimeout(function(){ try { emailInput.focus(); } catch(e){} }, 300);
  }

  function dismiss(){
    if (!root) return;
    root.hidden = true;
  }

  function setError(msg){
    errMsg.textContent = msg || "";
    if (msg) emailInput.classList.add("pnp-err"); else emailInput.classList.remove("pnp-err");
  }

  function validateEmail(v){
    v = (v || "").trim();
    if (!v) return t("err_required");
    // RFC-lite: local@domain.tld
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return t("err_invalid");
    return null;
  }

  function onSubmit(e){
    e.preventDefault();
    setError("");
    var email = emailInput.value;
    var err = validateEmail(email);
    if (err) { setError(err); emailInput.focus(); return; }

    var honeypot = form.querySelector('input[name="website"]').value;
    var elapsed_ms = Date.now() - startTs;

    ctaBtn.disabled = true;
    var oldText = ctaBtn.textContent;
    ctaBtn.textContent = "…";

    fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "subscribe_email",
        email: email.trim(),
        lang: LANG,
        source: location.pathname,
        honeypot: honeypot,
        elapsed_ms: elapsed_ms
      })
    }).then(function(r){ return r.json(); }).then(function(resp){
      if (!resp || !resp.ok) {
        ctaBtn.disabled = false; ctaBtn.textContent = oldText;
        setError(t("err_server"));
        return;
      }
      try { localStorage.setItem(K_SUBSCRIBED, "1"); } catch(e){}
      renderSuccess();
    }).catch(function(){
      ctaBtn.disabled = false; ctaBtn.textContent = oldText;
      setError(t("err_server"));
    });
  }

  function renderSuccess(){
    modal.innerHTML = [
      '<div class="pnp-success">',
        '<div class="pnp-success-icon">✓</div>',
        '<h3 class="pnp-success-title">' + esc(t("success_title")) + '</h3>',
        '<p class="pnp-success-msg">' + esc(t("success_msg")) + '</p>',
        '<button class="pnp-success-close" type="button">' + esc(t("success_close")) + '</button>',
      '</div>'
    ].join("");
    modal.querySelector(".pnp-success-close").addEventListener("click", function(){ root.hidden = true; });
  }

  // ---- Boot ----
  function boot(){ setTimeout(show, TRIGGER_MS); }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
