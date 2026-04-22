/* Pinnacle Email Capture Popup — 5s timer, bilingual EN/ES, 30d cool-down.
   Skips on /get-my-offer/ (server-enqueue filter) and on mobile <480px.
   Posts to /agents/pinnacle_public.php action=subscribe_email. */
(function(){
  "use strict";
  if (window.__pnpPopupLoaded) return;
  window.__pnpPopupLoaded = true;

  var API = "/agents/pinnacle_public.php";
  var TRIGGER_MS = 5000;
  var COOLDOWN_MS = 30 * 24 * 60 * 60 * 1000; // 30 days
  var MOBILE_THRESHOLD = 480;
  var K_SHOWN  = "pnp_popup_shown";    // timestamp of last dismiss
  var K_SUBSCRIBED = "pnp_popup_subscribed"; // "1" once subscribed

  // ---- Anti-annoyance pre-checks ----
  try {
    if (localStorage.getItem(K_SUBSCRIBED) === "1") return;
    var last = parseInt(localStorage.getItem(K_SHOWN) || "0", 10);
    if (last && (Date.now() - last) < COOLDOWN_MS) return;
  } catch(e) {}
  if (window.innerWidth < MOBILE_THRESHOLD) return;

  // ---- i18n ----
  var LANG = (navigator.language || "en").toLowerCase().indexOf("es") === 0 ? "es" : "en";
  var T = {
    en: {
      badge: "Wisconsin Investors",
      headline: "Want a head start on the next deal?",
      subhead: "Off-market opportunities + Wisconsin market insights, twice a month. No spam — unsubscribe anytime.",
      placeholder: "you@example.com",
      cta: "Send Me Deals",
      decline: "No thanks",
      trust: "We never share your email",
      err_required: "Please enter your email",
      err_invalid: "Please enter a valid email",
      err_server: "Connection hiccup — try again.",
      success_title: "You're in!",
      success_msg: "Check your inbox for a welcome note. Next deal alert coming soon.",
      success_close: "Got it"
    },
    es: {
      badge: "Inversionistas Wisconsin",
      headline: "¿Quieres adelantarte al próximo deal?",
      subhead: "Oportunidades off-market + análisis del mercado de Wisconsin, dos veces al mes. Sin spam — cancela cuando quieras.",
      placeholder: "tu@ejemplo.com",
      cta: "Envíenme Deals",
      decline: "No, gracias",
      trust: "Nunca compartimos tu email",
      err_required: "Ingresa tu email",
      err_invalid: "Ingresa un email válido",
      err_server: "Fallo de conexión — intenta otra vez.",
      success_title: "¡Listo!",
      success_msg: "Revisa tu inbox para un mensaje de bienvenida. Próxima alerta de deal en camino.",
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
    setTimeout(function(){ try { emailInput.focus(); } catch(e){} }, 300);
  }

  function dismiss(){
    if (!root) return;
    root.hidden = true;
    try { localStorage.setItem(K_SHOWN, String(Date.now())); } catch(e){}
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
