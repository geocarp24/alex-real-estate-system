/* Pinnacle Form — screens. Renders 18 screens into #pnf-stage.
   Depends on core (PNF_GO, PNF_T, PNF_STATE, PNF_API, PNF_VALID, PNF_$). */
(function(){
  "use strict";
  var t = window.PNF_T, go = window.PNF_GO, back = window.PNF_BACK, st = window.PNF_STATE;
  var $ = window.PNF_$, $$ = window.PNF_$$, api = window.PNF_API, V = window.PNF_VALID, setErr = window.PNF_ERR;

  // helpers
  function el(html){ var d = document.createElement("div"); d.innerHTML = html.trim(); return d.firstElementChild; }
  function navRow(hasSkip, onNext, nextKey){
    return '<div class="pnf-nav">'+
      '<button type="button" class="pnf-back">'+t("back")+'</button>'+
      (hasSkip ? '<button type="button" class="pnf-skip">'+t("skip")+'</button>' : '')+
      '<button type="button" class="pnf-next" disabled>'+t(nextKey||"next")+'</button>'+
    '</div>';
  }
  function wireNav(scr, onNext, onSkip){
    var b = scr.querySelector(".pnf-back"); if (b) b.addEventListener("click", back);
    var n = scr.querySelector(".pnf-next"); if (n) n.addEventListener("click", onNext);
    var s = scr.querySelector(".pnf-skip"); if (s && onSkip) s.addEventListener("click", onSkip);
  }
  function cardsHTML(opts){
    return '<div class="pnf-cards">' + opts.map(function(o){
      return '<button type="button" class="pnf-card" data-val="'+o.v+'">'+
        (o.emoji?'<span class="pnf-emoji">'+o.emoji+'</span>':'')+
        '<span><span>'+o.label+'</span>'+(o.sub?'<span class="pnf-sub">'+o.sub+'</span>':'')+'</span>'+
      '</button>';
    }).join("") + '</div>';
  }
  function chipsHTML(values){
    return '<div class="pnf-row">' + values.map(function(v){
      return '<button type="button" class="pnf-chip" data-val="'+v+'">'+v+'</button>';
    }).join("") + '</div>';
  }
  function header(scr, eyebrowKey, qKey, hintKey, vars){
    return (eyebrowKey?'<p class="pnf-eyebrow">'+t(eyebrowKey)+'</p>':'')+
      '<h2 class="pnf-question">'+t(qKey,vars)+'</h2>'+
      (hintKey?'<p class="pnf-hint">'+t(hintKey,vars)+'</p>':'');
  }
  function onCardClick(scr, field, nextId){
    $$(".pnf-card", scr).forEach(function(c){
      c.addEventListener("click", function(){
        $$(".pnf-card", scr).forEach(function(x){ x.classList.remove("is-selected"); });
        c.classList.add("is-selected");
        st.data[field] = c.getAttribute("data-val");
        setTimeout(function(){ go(nextId); }, 180);
      });
    });
  }
  function onChipClick(scr, field, nextId){
    $$(".pnf-chip", scr).forEach(function(c){
      c.addEventListener("click", function(){
        $$(".pnf-chip", scr).forEach(function(x){ x.classList.remove("is-selected"); });
        c.classList.add("is-selected");
        st.data[field] = c.getAttribute("data-val");
        setTimeout(function(){ go(nextId); }, 180);
      });
    });
  }

  // ---------- SCREEN BUILDERS ----------
  var builders = {};

  // S1 Address with autocomplete
  builders.s1 = function(){
    var html =
      '<p class="pnf-eyebrow">'+t("s1_eyebrow")+'</p>'+
      '<h2 class="pnf-question">'+t("s1_q")+'</h2>'+
      '<p class="pnf-hint">'+t("s1_hint")+'</p>'+
      '<input type="text" class="pnf-input" id="pnf-addr" placeholder="'+t("s1_ph")+'" autocomplete="off" />'+
      '<div class="pnf-suggestions" id="pnf-sug" hidden></div>'+
      '<input type="text" name="website" class="pnf-hp" tabindex="-1" autocomplete="off" />'+
      '<p class="pnf-error-msg"></p>'+
      navRow(false, null);
    var scr = el('<section id="pnf-screen-s1" class="pnf-screen">'+html+'</section>');
    var input = scr.querySelector("#pnf-addr"), sug = scr.querySelector("#pnf-sug"), nxt = scr.querySelector(".pnf-next");
    var debounceId;
    input.addEventListener("input", function(){
      clearTimeout(debounceId); nxt.disabled = !(input.value.trim().length>5 && st.data.place_id);
      var q = input.value.trim(); if (q.length<3){ sug.hidden=true; return; }
      debounceId = setTimeout(function(){
        api("places_proxy",{input:q}).then(function(r){
          var items = (r && r.suggestions) || []; if (!items.length){ sug.hidden=true; return; }
          sug.innerHTML = items.map(function(x){ return '<div class="pnf-suggestion" data-pid="'+x.place_id+'">'+x.text+'</div>'; }).join("");
          sug.hidden=false;
          $$(".pnf-suggestion", sug).forEach(function(s){
            s.addEventListener("click", function(){
              input.value = s.textContent; st.data.address = s.textContent; st.data.place_id = s.getAttribute("data-pid");
              // parse "1234 St, City, STATE ZIP, USA"
              var parts = s.textContent.split(",").map(function(p){ return p.trim(); });
              if (parts.length>=3){ st.data.city = parts[1]; var m = (parts[2]||"").match(/([A-Z]{2})\s*(\d{5})?/); if (m){ st.data.state = m[1]; st.data.zip = m[2]||""; } }
              sug.hidden=true; nxt.disabled=false;
            });
          });
        });
      }, 220);
    });
    nxt.addEventListener("click", function(){ if (!st.data.place_id){ setErr("s1", t("err_required")); return; } go("s2"); });
    return scr;
  };

  // S2 Property type
  builders.s2 = function(){
    var opts = [
      {v:"Single Family", emoji:"🏠", label:t("s2_sf"), sub:t("s2_sf_sub")},
      {v:"Duplex/Triplex", emoji:"🏘️", label:t("s2_du"), sub:t("s2_du_sub")},
      {v:"Multi-Family", emoji:"🏢", label:t("s2_mf"), sub:t("s2_mf_sub")},
      {v:"Mobile Home",  emoji:"🚚", label:t("s2_mh"), sub:t("s2_mh_sub")},
      {v:"Land",         emoji:"🌳", label:t("s2_lot"),sub:t("s2_lot_sub")}
    ];
    var scr = el('<section id="pnf-screen-s2" class="pnf-screen">'+
      '<p class="pnf-eyebrow">'+t("s2_eyebrow")+'</p>'+
      '<h2 class="pnf-question">'+t("s2_q")+'</h2>'+
      '<p class="pnf-hint">'+t("s2_hint")+'</p>'+
      cardsHTML(opts)+ navRow(false)+'</section>');
    onCardClick(scr, "property_type", "s3");
    wireNav(scr);
    return scr;
  };

  // Helper for simple text-input screens (name, email, phone, price, owed)
  function textScreen(id, eyebrowKey, qKey, hintKey, phKey, field, nextId, opts){
    opts = opts || {};
    var scr = el('<section id="pnf-screen-'+id+'" class="pnf-screen">'+
      header(null, eyebrowKey, qKey, hintKey)+
      (opts.prefix?'<div class="pnf-input-row"><span class="pnf-prefix">'+opts.prefix+'</span><input type="'+(opts.type||"text")+'" class="pnf-input" placeholder="'+t(phKey)+'" inputmode="'+(opts.inputmode||"text")+'" /></div>':'<input type="'+(opts.type||"text")+'" class="pnf-input" placeholder="'+t(phKey)+'" inputmode="'+(opts.inputmode||"text")+'" />')+
      '<p class="pnf-error-msg"></p>'+
      navRow(!!opts.skip)+'</section>');
    var input = scr.querySelector(".pnf-input"), nxt = scr.querySelector(".pnf-next");
    input.addEventListener("input", function(){ nxt.disabled = !input.value.trim(); setErr(id,""); });
    nxt.addEventListener("click", function(){
      var v = input.value.trim();
      if (opts.validate){ var r = opts.validate(v); if (r!==true){ setErr(id, t(r)); return; } }
      st.data[field] = v;
      if (opts.onNext) opts.onNext(v, nxt).then(function(ok){ if (ok) go(nextId); });
      else go(nextId);
    });
    wireNav(scr, null, opts.skip ? function(){ st.data[field] = ""; go(nextId); } : null);
    return scr;
  }

  builders.s3 = function(){ return textScreen("s3","s3_eyebrow","s3_q","s3_hint","s3_ph","name","s4"); };
  builders.s4 = function(){ return textScreen("s4",null,"s4_q","s4_hint","s4_ph","email","s5",{type:"email",inputmode:"email",validate:function(v){ return V.email(v)?true:"err_email"; }}); };
  // Helper: send start_lead (with optional reopen_lead_id) and advance to s6
  function startLeadAndGo(nxt, navigateTo){
    nxt.disabled = true; nxt.innerHTML = t("sending")+'<span class="pnf-spinner"></span>';
    return api("start_lead",{
      name: st.data.name, email: st.data.email, phone: st.data.phone_raw,
      address: st.data.address, place_id: st.data.place_id,
      property_type: st.data.property_type, city: st.data.city, state: st.data.state, zip: st.data.zip,
      reopen_lead_id: st.data.reopen_lead_id || "",
      elapsed_ms: Date.now() - st.pageLoadedAt, website: ""
    }).then(function(r){
      nxt.disabled = false; nxt.textContent = t("next");
      if (r && r.ok){
        st.data.lead_id = r.lead_id; st.data.session_token = r.session_token;
        st.data.phone_masked = r.phone_masked; st.data.is_reopen = !!r.is_reopen;
        if (navigateTo) go(navigateTo);
        return true;
      }
      setErr(st.current, (r && r.error) || t("err_generic")); return false;
    }).catch(function(){ nxt.disabled = false; nxt.textContent = t("next"); setErr(st.current, t("err_generic")); return false; });
  }
  window.PNF_START_LEAD = startLeadAndGo;

  builders.s5 = function(){
    return textScreen("s5",null,"s5_q","s5_hint","s5_ph","phone_raw","s6",{type:"tel",inputmode:"tel",validate:function(v){ return V.phone(v)?true:"err_phone"; },
      onNext:function(v, nxt){
        // Save phone to state first
        st.data.phone_raw = v;
        // Step 1: lookup_existing — check if returning client
        nxt.disabled = true; nxt.innerHTML = t("sending")+'<span class="pnf-spinner"></span>';
        return api("lookup_existing",{phone:v, address: st.data.address}).then(function(lookup){
          if (lookup && lookup.found){
            // Returning client → show s_returning, do NOT auto-advance to s6
            nxt.disabled = false; nxt.textContent = t("next");
            st.data.existing_match = lookup;
            go("s_returning");
            return false; // tell textScreen we already navigated
          }
          // No match → standard new-lead flow
          return startLeadAndGo(nxt, null).then(function(ok){ return ok; });
        }).catch(function(){
          // If lookup fails, fall back to standard new-lead flow (don't block user)
          return startLeadAndGo(nxt, null).then(function(ok){ return ok; });
        });
      } });
  };

  // S_RESUME — shown on load if localStorage has an in-progress session
  builders.s_resume = function(){
    var html =
      '<p class="pnf-eyebrow">'+t("res_eyebrow")+'</p>'+
      '<h2 class="pnf-question">'+t("res_q")+'</h2>'+
      '<p class="pnf-hint">'+t("res_hint")+'</p>'+
      '<div class="pnf-cards">'+
        '<button type="button" class="pnf-card" data-action="continue">'+
          '<span class="pnf-emoji">▶️</span>'+
          '<span><span>'+t("res_continue")+'</span><span class="pnf-sub">'+t("res_continue_sub")+'</span></span>'+
        '</button>'+
        '<button type="button" class="pnf-card" data-action="restart">'+
          '<span class="pnf-emoji">🔄</span>'+
          '<span><span>'+t("res_restart")+'</span><span class="pnf-sub">'+t("res_restart_sub")+'</span></span>'+
        '</button>'+
      '</div>';
    var scr = el('<section id="pnf-screen-s_resume" class="pnf-screen">'+html+'</section>');
    scr.querySelector('[data-action="continue"]').addEventListener("click", function(){
      var p = window.PNF_SESSION.load();
      if (p && p.current) { window.PNF_SESSION.restore(p); go(p.current); }
      else { go("s1"); }
    });
    scr.querySelector('[data-action="restart"]').addEventListener("click", function(){
      window.PNF_SESSION.clear();
      go("s1");
    });
    return scr;
  };

  // S_RETURNING — shown when lookup_existing finds a prior lead for this phone/address
  builders.s_returning = function(){
    var m = st.data.existing_match || {};
    var first = (m.name || st.data.name || "").split(" ")[0] || "";
    var stage = m.stage || "—";
    var addr = m.address || st.data.address;
    var html =
      '<p class="pnf-eyebrow">'+t("ret_eyebrow")+'</p>'+
      '<h2 class="pnf-question">'+t(first ? "ret_q_named" : "ret_q_anon",{name:first})+'</h2>'+
      '<p class="pnf-hint">'+t("ret_hint",{address:addr,stage:stage})+'</p>'+
      '<div class="pnf-cards">'+
        '<button type="button" class="pnf-card" data-action="update">'+
          '<span class="pnf-emoji">✏️</span>'+
          '<span><span>'+t("ret_update")+'</span><span class="pnf-sub">'+t("ret_update_sub")+'</span></span>'+
        '</button>'+
        '<a class="pnf-card" href="tel:+19207779886" data-action="call" style="text-decoration:none">'+
          '<span class="pnf-emoji">📞</span>'+
          '<span><span>'+t("ret_call")+'</span><span class="pnf-sub">'+t("ret_call_sub")+'</span></span>'+
        '</a>'+
        '<button type="button" class="pnf-card" data-action="new">'+
          '<span class="pnf-emoji">🆕</span>'+
          '<span><span>'+t("ret_new")+'</span><span class="pnf-sub">'+t("ret_new_sub")+'</span></span>'+
        '</button>'+
      '</div>'+
      '<p class="pnf-error-msg"></p>'+
      navRow(false);
    var scr = el('<section id="pnf-screen-s_returning" class="pnf-screen">'+html+'</section>');
    var updateBtn = scr.querySelector('[data-action="update"]');
    var newBtn    = scr.querySelector('[data-action="new"]');
    if (updateBtn) updateBtn.addEventListener("click", function(){
      st.data.reopen_lead_id = m.lead_id || "";
      var spinner = '<span class="pnf-spinner"></span>';
      updateBtn.innerHTML = '<span class="pnf-emoji">⏳</span><span><span>'+t("sending")+'</span></span>';
      startLeadAndGo({disabled:false, innerHTML:"", textContent:""}, "s6").catch(function(){
        updateBtn.innerHTML = '<span class="pnf-emoji">✏️</span><span><span>'+t("ret_update")+'</span><span class="pnf-sub">'+t("ret_update_sub")+'</span></span>';
        setErr("s_returning", t("err_generic"));
      });
    });
    if (newBtn) newBtn.addEventListener("click", function(){
      st.data.reopen_lead_id = ""; // force new lead
      newBtn.innerHTML = '<span class="pnf-emoji">⏳</span><span><span>'+t("sending")+'</span></span>';
      startLeadAndGo({disabled:false, innerHTML:"", textContent:""}, "s6").catch(function(){
        newBtn.innerHTML = '<span class="pnf-emoji">🆕</span><span><span>'+t("ret_new")+'</span><span class="pnf-sub">'+t("ret_new_sub")+'</span></span>';
        setErr("s_returning", t("err_generic"));
      });
    });
    wireNav(scr);
    return scr;
  };

  // S6 SMS verify
  builders.s6 = function(){
    var scr = el('<section id="pnf-screen-s6" class="pnf-screen">'+
      '<h2 class="pnf-question">'+t("s6_q")+'</h2>'+
      '<p class="pnf-hint" id="pnf-s6-hint"></p>'+
      '<div class="pnf-code-boxes">'+Array(6).fill(0).map(function(_,i){ return '<input maxlength="1" inputmode="numeric" pattern="\\d" data-i="'+i+'" />'; }).join("")+'</div>'+
      '<p class="pnf-error-msg"></p>'+
      '<button type="button" class="pnf-resend">'+t("resend")+'</button>'+
      navRow(false, null, "verify")+'</section>');
    scr.querySelector("#pnf-s6-hint").textContent = t("s6_hint",{phone: st.data.phone_masked||"your phone"});
    var boxes = $$(".pnf-code-boxes input", scr), nxt = scr.querySelector(".pnf-next");
    boxes.forEach(function(b,i){
      b.addEventListener("input", function(){
        b.value = b.value.replace(/\D/g,"").slice(0,1);
        if (b.value && boxes[i+1]) boxes[i+1].focus();
        var code = boxes.map(function(x){ return x.value; }).join("");
        nxt.disabled = code.length < 6;
      });
      b.addEventListener("keydown", function(e){ if (e.key==="Backspace" && !b.value && boxes[i-1]){ boxes[i-1].focus(); }});
    });
    nxt.addEventListener("click", function(){
      var code = boxes.map(function(x){ return x.value; }).join("");
      nxt.disabled = true;
      api("verify_phone",{lead_id: st.data.lead_id, session_token: st.data.session_token, code: code}).then(function(r){
        if (r && r.ok){ go("s7"); } else { setErr("s6", t("s6_wrong")); nxt.disabled = false; }
      });
    });
    scr.querySelector(".pnf-resend").addEventListener("click", function(){
      var btn = this; api("resend_code",{lead_id: st.data.lead_id, session_token: st.data.session_token}).then(function(r){
        if (r && r.ok) window.PNF_TIMER(btn, 30); else alert((r&&r.error)||t("err_generic"));
      });
    });
    wireNav(scr);
    return scr;
  };

  // Helpers for remaining card screens
  function cardScreen(id, eyebrowKey, qKey, hintKey, opts, field, nextId){
    var scr = el('<section id="pnf-screen-'+id+'" class="pnf-screen">'+
      header(null, eyebrowKey, qKey, hintKey)+ cardsHTML(opts)+ navRow(false)+'</section>');
    onCardClick(scr, field, nextId); wireNav(scr); return scr;
  }
  function chipScreen(id, qKey, values, field, nextId){
    var scr = el('<section id="pnf-screen-'+id+'" class="pnf-screen">'+
      header(null, null, qKey, null)+ chipsHTML(values)+ navRow(false)+'</section>');
    onChipClick(scr, field, nextId); wireNav(scr); return scr;
  }

  builders.s7 = function(){ return cardScreen("s7","s7_eyebrow","s7_q","s7_hint",[
    {v:"Move-in Ready",emoji:"🟢",label:t("s7_ready"),sub:t("s7_ready_sub")},
    {v:"Minor Repairs",emoji:"🟡",label:t("s7_minor"),sub:t("s7_minor_sub")},
    {v:"Major Repairs",emoji:"🟠",label:t("s7_major"),sub:t("s7_major_sub")},
    {v:"Distressed",   emoji:"🔴",label:t("s7_distress"),sub:t("s7_distress_sub")}
  ],"condition","s8"); };
  builders.s8 = function(){ return cardScreen("s8",null,"s8_q","s8_hint",[
    {v:"<5 years",emoji:"🆕",label:t("s8_new")},{v:"5-15 years",emoji:"⏳",label:t("s8_mid")},
    {v:">15 years",emoji:"🧓",label:t("s8_old")},{v:"Unknown",emoji:"❓",label:t("s8_unk")}
  ],"roof","s9"); };
  builders.s9  = function(){ return chipScreen("s9","s9_q", ["1","2","3","4","5+"], "beds","s10"); };
  builders.s10 = function(){ return chipScreen("s10","s10_q",["1","2","3","4","5+"], "baths","s11"); };
  builders.s11 = function(){ return cardScreen("s11",null,"s11_q",null,[
    {v:"Owner",emoji:"🏡",label:t("s11_owner"),sub:t("s11_owner_sub")},
    {v:"Rented",emoji:"🔑",label:t("s11_rented"),sub:t("s11_rented_sub")},
    {v:"Vacant",emoji:"🚪",label:t("s11_vacant"),sub:t("s11_vacant_sub")}
  ],"occupancy","s12"); };

  // S12 textarea (skip allowed)
  builders.s12 = function(){
    var scr = el('<section id="pnf-screen-s12" class="pnf-screen">'+
      header(null,null,"s12_q","s12_hint")+
      '<textarea class="pnf-textarea" placeholder="'+t("s12_ph")+'"></textarea>'+
      '<p class="pnf-error-msg"></p>'+ navRow(true)+'</section>');
    var ta = scr.querySelector("textarea"), nxt = scr.querySelector(".pnf-next");
    ta.addEventListener("input", function(){ nxt.disabled = !ta.value.trim(); });
    nxt.addEventListener("click", function(){ st.data.issues = ta.value.trim(); go("s13"); });
    wireNav(scr, null, function(){ st.data.issues = ""; go("s13"); });
    return scr;
  };

  builders.s13 = function(){ return cardScreen("s13","s13_eyebrow","s13_q",null,[
    {v:"ASAP",emoji:"⚡",label:t("s13_asap"),sub:t("s13_asap_sub")},
    {v:"30 days",emoji:"📅",label:t("s13_30")},
    {v:"60-90 days",emoji:"📆",label:t("s13_60")},
    {v:"Exploring",emoji:"🧭",label:t("s13_expl"),sub:t("s13_expl_sub")}
  ],"timeline","s14"); };
  builders.s14 = function(){ return cardScreen("s14",null,"s14_q","s14_hint",[
    {v:"Highest Cash",emoji:"💰",label:t("s14_cash")},
    {v:"Fast Close",emoji:"⚡",label:t("s14_fast")},
    {v:"As-Is Sale",emoji:"🧱",label:t("s14_asis")},
    {v:"No Hassle",emoji:"🧘",label:t("s14_nohassle")}
  ],"priority","s15"); };

  builders.s15 = function(){ return textScreen("s15",null,"s15_q","s15_hint","s15_ph","asking_price","s16",{type:"text",inputmode:"numeric",prefix:"$",skip:true}); };
  builders.s16 = function(){ return cardScreen("s16",null,"s16_q","s16_hint",[
    {v:"All Cash",emoji:"💵",label:t("s16_cash")},
    {v:"Down+Monthly",emoji:"📆",label:t("s16_down")},
    {v:"Open",emoji:"🤝",label:t("s16_open")}
  ],"payment_pref","s17"); };

  builders.s17 = function(){
    var scr = el('<section id="pnf-screen-s17" class="pnf-screen">'+
      header(null,null,"s17_q","s17_hint")+
      '<div class="pnf-input-row"><span class="pnf-prefix">$</span><input type="text" class="pnf-input" inputmode="numeric" placeholder="'+t("s17_ph")+'" /></div>'+
      '<p class="pnf-error-msg"></p>'+ navRow(true,null,"submitting")+'</section>');
    var input = scr.querySelector("input"), nxt = scr.querySelector(".pnf-next");
    nxt.disabled = false; nxt.textContent = t("next");
    input.addEventListener("input", function(){ st.data.amount_owed = input.value.replace(/[^\d]/g,""); });
    nxt.addEventListener("click", function(){
      nxt.disabled = true; nxt.innerHTML = t("submitting")+'<span class="pnf-spinner"></span>';
      window.PNF_SUBMIT();
    });
    wireNav(scr, null, function(){ st.data.amount_owed = ""; nxt.disabled = true; nxt.innerHTML = t("submitting")+'<span class="pnf-spinner"></span>'; window.PNF_SUBMIT(); });
    return scr;
  };

  // OK final
  builders.ok = function(){
    var scr = el('<section id="pnf-screen-ok" class="pnf-screen">'+
      '<div class="pnf-success">'+
      '<div class="pnf-success-icon">✓</div>'+
      '<h2>'+t("ok_title")+'</h2>'+
      '<p>'+t("ok_msg",{name: st.data.name.split(" ")[0] || ""})+'</p>'+
      '<div class="pnf-score">'+t("ok_score",{score: st.data.score||"—"})+'</div>'+
      '<div class="pnf-ok-actions">'+
        '<a href="/" class="pnf-next pnf-ok-home">'+t("ok_home")+'</a>'+
        '<button type="button" class="pnf-ok-close">'+t("ok_close")+'</button>'+
      '</div>'+
      '</div></section>');
    var closeBtn = scr.querySelector(".pnf-ok-close");
    if (closeBtn) closeBtn.addEventListener("click", function(){
      // Try to close tab (works if window was opened via script); else go home
      window.close();
      setTimeout(function(){ window.location.href = "/"; }, 300);
    });
    return scr;
  };

  // ---------- MOUNT ----------
  function mountAll(){
    var stage = document.getElementById("pnf-stage");
    if (!stage) return;
    stage.innerHTML = "";
    ["s_resume","s1","s2","s3","s4","s5","s_returning","s6","s7","s8","s9","s10","s11","s12","s13","s14","s15","s16","s17","ok"]
      .forEach(function(id){ stage.appendChild(builders[id]()); });
  }
  window.PNF_SCREENS = { rerender: mountAll };

  function boot(){
    mountAll();
    if (window.PNF_CORE_INIT)  window.PNF_CORE_INIT();
    if (window.PNF_SHOW_FIRST) window.PNF_SHOW_FIRST();
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
