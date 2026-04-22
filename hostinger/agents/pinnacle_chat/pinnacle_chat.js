/* Pinnacle Chat Widget — floating Fer bot on all pages.
   Self-contained, no external deps. Posts to /agents/pinnacle_public.php
   with action=chat_message. Keeps transcript in localStorage (2h TTL). */
(function(){
  "use strict";
  if (window.__pnfChatLoaded) return;
  window.__pnfChatLoaded = true;

  var API = "/agents/pinnacle_public.php";
  var STORAGE_KEY = "pnf_chat";
  var TTL_MS = 2 * 60 * 60 * 1000;

  // ---- i18n ----
  var LANG = (navigator.language || "en").toLowerCase().indexOf("es") === 0 ? "es" : "en";
  var T = {
    en: {
      label: "Chat with us",
      header: "Chat with Pinnacle",
      sub: "AI assistant · bilingual · 24/7",
      greet: "Hi! I'm Fer from Pinnacle Holdings. How can I help you today? Are you thinking about selling a property?",
      placeholder: "Type your message...",
      send: "Send",
      close: "Close",
      reset: "Start over",
      thinking: "Thinking…",
      net_err: "Connection hiccup — try again.",
      escalated: "✓ Got it! A Pinnacle team member will reach out within 24 hours."
    },
    es: {
      label: "Chatea con nosotros",
      header: "Chat con Pinnacle",
      sub: "Asistente IA · bilingüe · 24/7",
      greet: "¡Hola! Soy Fer de Pinnacle Holdings. ¿En qué te puedo ayudar? ¿Estás pensando vender una propiedad?",
      placeholder: "Escribe tu mensaje...",
      send: "Enviar",
      close: "Cerrar",
      reset: "Empezar de nuevo",
      thinking: "Pensando…",
      net_err: "Fallo de conexión — intenta otra vez.",
      escalated: "✓ ¡Recibido! Un miembro del equipo de Pinnacle te contactará en 24 horas."
    }
  };
  function t(k){ return (T[LANG] && T[LANG][k]) || T.en[k] || k; }

  // ---- State ----
  var state = {
    open: false,
    session_id: null,
    history: [],           // [{role, content}]
    pending: false,
    escalated: false,
  };

  function loadState(){
    try {
      var raw = localStorage.getItem(STORAGE_KEY); if (!raw) return;
      var p = JSON.parse(raw);
      if (!p.saved_at || (Date.now() - p.saved_at) > TTL_MS) {
        localStorage.removeItem(STORAGE_KEY); return;
      }
      state.session_id = p.session_id || null;
      state.history    = Array.isArray(p.history) ? p.history : [];
      state.escalated  = !!p.escalated;
    } catch(e){}
  }
  function saveState(){
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        session_id: state.session_id, history: state.history,
        escalated: state.escalated, saved_at: Date.now()
      }));
    } catch(e){}
  }
  function clearState(){
    state.session_id = null; state.history = []; state.escalated = false;
    try { localStorage.removeItem(STORAGE_KEY); } catch(e){}
  }

  // ---- DOM ----
  var root, bubble, panel, messages, input, sendBtn, resetBtn;

  function build(){
    root = document.createElement("div");
    root.id = "pnc-root";
    root.innerHTML = [
      '<button class="pnc-bubble" type="button" aria-label="'+t("label")+'">',
        '<span class="pnc-bubble-icon">💬</span>',
        '<span class="pnc-bubble-dot"></span>',
      '</button>',
      '<div class="pnc-panel" role="dialog" aria-label="'+t("header")+'" hidden>',
        '<div class="pnc-head">',
          '<div class="pnc-head-main">',
            '<div class="pnc-avatar">F</div>',
            '<div><div class="pnc-head-title">'+t("header")+'</div><div class="pnc-head-sub">'+t("sub")+'</div></div>',
          '</div>',
          '<div class="pnc-head-actions">',
            '<button class="pnc-reset" type="button" title="'+t("reset")+'">↻</button>',
            '<button class="pnc-close" type="button" aria-label="'+t("close")+'">×</button>',
          '</div>',
        '</div>',
        '<div class="pnc-messages" id="pnc-messages"></div>',
        '<form class="pnc-input-row" onsubmit="return false">',
          '<input class="pnc-input" type="text" autocomplete="off" placeholder="'+t("placeholder")+'" />',
          '<button class="pnc-send" type="submit" aria-label="'+t("send")+'">→</button>',
        '</form>',
      '</div>'
    ].join("");
    document.body.appendChild(root);
    bubble   = root.querySelector(".pnc-bubble");
    panel    = root.querySelector(".pnc-panel");
    messages = root.querySelector(".pnc-messages");
    input    = root.querySelector(".pnc-input");
    sendBtn  = root.querySelector(".pnc-send");
    resetBtn = root.querySelector(".pnc-reset");

    bubble.addEventListener("click", open);
    root.querySelector(".pnc-close").addEventListener("click", close);
    resetBtn.addEventListener("click", function(){ clearState(); messages.innerHTML=""; renderGreeting(); });
    root.querySelector(".pnc-input-row").addEventListener("submit", function(e){ e.preventDefault(); onSend(); });
    sendBtn.addEventListener("click", onSend);
    input.addEventListener("keydown", function(e){ if (e.key==="Enter" && !e.shiftKey){ e.preventDefault(); onSend(); } });
  }

  function addMsg(role, text, opts){
    opts = opts || {};
    var m = document.createElement("div");
    m.className = "pnc-msg pnc-msg-" + role;
    m.innerHTML = '<div class="pnc-bubble-msg">' + escapeHTML(text).replace(/\n/g, "<br>") + '</div>';
    if (opts.pending) m.classList.add("pnc-pending");
    if (opts.id) m.id = opts.id;
    messages.appendChild(m);
    messages.scrollTop = messages.scrollHeight;
    return m;
  }
  function escapeHTML(s){ return String(s).replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  }); }

  function renderGreeting(){
    messages.innerHTML = "";
    if (state.history.length === 0) {
      addMsg("assistant", t("greet"));
    } else {
      state.history.forEach(function(h){ addMsg(h.role, h.content); });
      if (state.escalated) addMsg("assistant", t("escalated"));
    }
  }

  function open(){
    state.open = true; panel.hidden = false; root.classList.add("pnc-open");
    if (messages.children.length === 0) renderGreeting();
    setTimeout(function(){ try { input.focus(); } catch(e){} }, 150);
  }
  function close(){
    state.open = false; panel.hidden = true; root.classList.remove("pnc-open");
  }

  function onSend(){
    var text = (input.value || "").trim();
    if (!text || state.pending || state.escalated) return;
    input.value = "";
    state.history.push({ role: "user", content: text });
    addMsg("user", text);
    saveState();

    state.pending = true;
    sendBtn.disabled = true;
    var thinkingEl = addMsg("assistant", t("thinking"), { pending: true, id: "pnc-thinking" });

    fetch(API, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "chat_message",
        session_id: state.session_id,
        lang: LANG,
        history: state.history,
      })
    }).then(function(r){ return r.json(); }).then(function(resp){
      if (thinkingEl && thinkingEl.parentNode) thinkingEl.parentNode.removeChild(thinkingEl);
      state.pending = false; sendBtn.disabled = false;
      if (!resp || !resp.ok) { addMsg("assistant", t("net_err")); return; }
      state.session_id = resp.session_id || state.session_id;
      var reply = resp.reply || "";
      if (reply) {
        state.history.push({ role: "assistant", content: reply });
        addMsg("assistant", reply);
      }
      if (resp.escalate) {
        state.escalated = true;
        addMsg("assistant", t("escalated"));
      }
      saveState();
    }).catch(function(){
      if (thinkingEl && thinkingEl.parentNode) thinkingEl.parentNode.removeChild(thinkingEl);
      state.pending = false; sendBtn.disabled = false;
      addMsg("assistant", t("net_err"));
    });
  }

  // Public API (for Contact page "Chat With Us" card)
  window.PinnacleChat = {
    open: open,
    close: close,
    reset: function(){ clearState(); messages && (messages.innerHTML = ""); renderGreeting(); }
  };

  // Boot
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function(){ loadState(); build(); });
  } else {
    loadState(); build();
  }
})();
