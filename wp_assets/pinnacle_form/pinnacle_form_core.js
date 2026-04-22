/* Pinnacle Form — core state machine + API.
   Endpoint: same-origin /agents/pinnacle_public.php
   Depends on window.PNF_I18N (loaded first). */
(function(){
  "use strict";
  var API  = "/agents/pinnacle_public.php";
  var T    = window.PNF_I18N;
  var ORDER = ["s1","s2","s3","s4","s5","s6","s7","s8","s9","s10","s11","s12","s13","s14","s15","s16","s17","ok"];
  var STEP_TOTAL = ORDER.length - 1;       // "ok" is not a step in the counter

  var state = {
    lang: (navigator.language||"en").toLowerCase().indexOf("es")===0 ? "es" : "en",
    pageLoadedAt: Date.now(),
    history: [],                            // for Back button
    current: "s1",
    data: {                                 // collected answers
      address:"", place_id:"", city:"", state:"WI", zip:"",
      property_type:"",
      name:"", email:"", phone_raw:"",
      lead_id:"", session_token:"",
      condition:"", roof:"", beds:"", baths:"", occupancy:"", issues:"",
      timeline:"", priority:"", asking_price:"", payment_pref:"", amount_owed:""
    },
    resendTimer: null,
    submitting: false
  };
  window.PNF_STATE = state;                 // exposed for screens module + debug

  function t(key, vars){
    var s = (T[state.lang] && T[state.lang][key]) || T.en[key] || key;
    if (vars) Object.keys(vars).forEach(function(k){ s = s.replace("{"+k+"}", vars[k]); });
    return s;
  }
  window.PNF_T = t;

  function $(sel, root){ return (root||document).querySelector(sel); }
  function $all(sel, root){ return Array.prototype.slice.call((root||document).querySelectorAll(sel)); }
  window.PNF_$ = $; window.PNF_$$ = $all;

  // ---- API helpers ----
  function api(action, body){
    var payload = Object.assign({ action: action }, body||{});
    return fetch(API, {
      method: "POST",
      headers: { "Content-Type":"application/json" },
      body: JSON.stringify(payload)
    }).then(function(r){ return r.json().then(function(j){ j.__http = r.status; return j; }); });
  }
  window.PNF_API = api;

  // ---- Rendering ----
  function renderProgress(){
    var idx = ORDER.indexOf(state.current);
    if (state.current === "ok") { $("#pnf-progress-bar").style.width = "100%"; $("#pnf-step-count").textContent = ""; return; }
    var pct = Math.round(((idx+1)/STEP_TOTAL)*100);
    $("#pnf-progress-bar").style.width = pct + "%";
    $("#pnf-step-count").textContent = t("step_of",{n:idx+1,t:STEP_TOTAL});
  }

  function show(id){
    $all("#pnf-root .pnf-screen").forEach(function(el){ el.classList.remove("is-active"); });
    var next = $("#pnf-screen-"+id);
    if (!next) { console.warn("PNF: screen not found",id); return; }
    next.classList.add("is-active");
    state.current = id;
    renderProgress();
    // back button visibility
    var back = $("#pnf-screen-"+id+" .pnf-back");
    if (back) back.hidden = (state.history.length === 0);
    // focus first input for keyboard users
    var f = next.querySelector("input, textarea, button.pnf-card, button.pnf-chip");
    if (f) setTimeout(function(){ try { f.focus(); } catch(e){} }, 60);
  }

  function goTo(id){
    if (state.current !== id) state.history.push(state.current);
    show(id);
  }
  function goBack(){
    var prev = state.history.pop();
    if (prev) show(prev);
  }
  window.PNF_GO = goTo; window.PNF_BACK = goBack;

  // ---- Validation ----
  function validEmail(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); }
  function validPhone(v){ return (v.replace(/\D/g,"").length >= 10); }
  window.PNF_VALID = { email: validEmail, phone: validPhone };

  // ---- Language toggle ----
  function setLang(lang){
    state.lang = lang;
    $all("#pnf-root .pnf-lang button").forEach(function(b){
      b.classList.toggle("is-active", b.getAttribute("data-lang") === lang);
    });
    // re-render current screen (screens module re-reads i18n on render)
    if (window.PNF_SCREENS && window.PNF_SCREENS.rerender) window.PNF_SCREENS.rerender();
    renderProgress();
  }
  window.PNF_SETLANG = setLang;

  // ---- Resend timer for SMS ----
  function startResendTimer(btn, seconds){
    var remaining = seconds;
    btn.disabled = true;
    btn.textContent = t("resend_in",{s:remaining});
    if (state.resendTimer) clearInterval(state.resendTimer);
    state.resendTimer = setInterval(function(){
      remaining--;
      if (remaining <= 0){
        clearInterval(state.resendTimer);
        btn.disabled = false;
        btn.textContent = t("resend");
      } else {
        btn.textContent = t("resend_in",{s:remaining});
      }
    }, 1000);
  }
  window.PNF_TIMER = startResendTimer;

  // ---- Error display helper ----
  function setError(screenId, msg){
    var el = $("#pnf-screen-"+screenId+" .pnf-error-msg");
    if (el) el.textContent = msg || "";
  }
  window.PNF_ERR = setError;

  // ---- Submit finale ----
  function submitFinal(){
    if (state.submitting) return;
    state.submitting = true;
    var resetBtn = function(){
      var scrId = state.current;
      var nxt = $("#pnf-screen-"+scrId+" .pnf-next");
      if (nxt){ nxt.disabled = false; nxt.textContent = t("next"); }
    };
    api("update_lead", {
      lead_id: state.data.lead_id,
      session_token: state.data.session_token,
      phase2: { condition: state.data.condition, roof: state.data.roof, beds: state.data.beds, baths: state.data.baths, occupancy: state.data.occupancy, issues: state.data.issues },
      phase3: { timeline: state.data.timeline, priority: state.data.priority, asking_price: state.data.asking_price, payment_pref: state.data.payment_pref, amount_owed: state.data.amount_owed }
    }).then(function(resp){
      state.submitting = false;
      if (resp && resp.ok){
        state.data.score = resp.score;
        state.data.heat = resp.heat;
        goTo("ok");
      } else {
        var msg = (resp && resp.error) ? resp.error : t("err_generic");
        setError(state.current, msg);
        resetBtn();
      }
    }).catch(function(e){
      state.submitting = false;
      setError(state.current, t("err_generic") + " ("+(e && e.message ? e.message : "network")+")");
      resetBtn();
    });
  }
  window.PNF_SUBMIT = submitFinal;

  // ---- Boot hooks (called by screens.js AFTER mountAll) ----
  window.PNF_CORE_INIT = function(){
    $all("#pnf-root .pnf-lang button").forEach(function(b){
      b.addEventListener("click", function(){ setLang(b.getAttribute("data-lang")); });
    });
    setLang(state.lang);
  };
  window.PNF_SHOW_FIRST = function(){ show("s1"); };
})();
