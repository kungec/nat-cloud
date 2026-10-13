/* NAT Cloud - 前端交互（原生 JS） */
(function () {
  "use strict";

  /* 主题初始化与切换 */
  function applyTheme(t) {
    if (t === "light") {
      document.documentElement.setAttribute("data-theme", "light");
    } else {
      document.documentElement.removeAttribute("data-theme");
    }
    try { localStorage.setItem("nc_theme", t); } catch (e) {}
  }
  var savedTheme = null;
  try { savedTheme = localStorage.getItem("nc_theme"); } catch (e) {}
  if (savedTheme === "light") {
    document.documentElement.setAttribute("data-theme", "light");
  }
  var themeBtn = document.getElementById("themeToggle");
  if (themeBtn) {
    themeBtn.addEventListener("click", function () {
      var cur = document.documentElement.getAttribute("data-theme") === "light" ? "light" : "dark";
      applyTheme(cur === "light" ? "dark" : "light");
    });
  }

  /* 闪存提示自动消失 */
  setTimeout(function () {
    document.querySelectorAll(".flash").forEach(function (el) {
      el.style.transition = "opacity .5s";
      el.style.opacity = "0";
      setTimeout(function () { el.remove(); }, 550);
    });
  }, 4200);

  /* 快捷充值金额 */
  document.querySelectorAll("[data-amount]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var form = btn.closest("form");
      var input = form ? form.querySelector('input[name="amount"]') : null;
      if (input) input.value = btn.getAttribute("data-amount");
      var row = btn.closest(".quick-amounts");
      if (row) {
        row.querySelectorAll("[data-amount]").forEach(function (b) { b.classList.remove("on"); });
      }
      btn.classList.add("on");
    });
  });

  /* 语言切换 */
  document.querySelectorAll("[data-setlang]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var lang = btn.getAttribute("data-setlang") || "zh";
      document.cookie = "nc_lang=" + lang + ";path=/;max-age=31536000;samesite=Lax";
      location.reload();
    });
  });

  /* 页面切换加载进度条 */
  var progress = document.getElementById("ncProgress");
  if (progress) {
    document.addEventListener("click", function (ev) {
      var a = ev.target.closest ? ev.target.closest("a[href]") : null;
      if (!a) return;
      var href = a.getAttribute("href") || "";
      if (a.target === "_blank" || href.charAt(0) === "#" || href.indexOf("javascript") === 0) return;
      if (a.origin !== location.origin) return;
      progress.classList.remove("done");
      progress.classList.add("go");
    });
    window.addEventListener("load", function () {
      if (progress.classList.contains("go")) {
        progress.classList.add("done");
        setTimeout(function () { progress.classList.remove("go", "done"); }, 600);
      }
    });
  }

  /* 统一确认弹窗（替代浏览器原生 confirm） */
  var cfmMask = document.getElementById("ncConfirm");
  if (cfmMask) {
    var cfmTarget = null;
    function closeCfm() { cfmMask.classList.remove("open"); cfmTarget = null; }
    document.addEventListener("click", function (ev) {
      var el = ev.target.closest ? ev.target.closest("[data-confirm]") : null;
      if (!el) return;
      /* 表单确认只在点击提交按钮时触发；表单内下拉/输入等交互不拦截 */
      if (el.tagName === "FORM") {
        var submitBtn = ev.target.closest ? ev.target.closest('button[type="submit"], input[type="submit"]') : null;
        if (!submitBtn || !el.contains(submitBtn)) return;
      }
      ev.preventDefault();
      ev.stopPropagation();
      cfmTarget = el;
      var msg = document.getElementById("cfmMsg");
      if (msg) msg.textContent = el.getAttribute("data-confirm") || "确认执行该操作？";
      cfmMask.classList.add("open");
    }, true);
    document.getElementById("cfmCancel").addEventListener("click", closeCfm);
    cfmMask.addEventListener("click", function (ev) { if (ev.target === cfmMask) closeCfm(); });
    document.addEventListener("keydown", function (ev) { if (ev.key === "Escape" && cfmMask.classList.contains("open")) closeCfm(); });
    document.getElementById("cfmOk").addEventListener("click", function () {
      var t = cfmTarget;
      closeCfm();
      if (!t) return;
      if (t.tagName === "FORM") { t.submit(); return; }
      var form = t.closest ? t.closest("form") : null;
      if (form) { form.submit(); return; }
      var href = t.getAttribute("href");
      if (href && href.charAt(0) !== "#") { location.href = href; }
    });
  }

  /* 汉堡菜单（移动端） */
  var hamburger = document.getElementById("navHamburger");
  var mobilePanel = document.getElementById("mobileNavPanel");
  var ICON_MENU = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="18" y2="18"/></svg>';
  var ICON_X = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
  if (hamburger && mobilePanel) {
    hamburger.addEventListener("click", function () {
      var open = mobilePanel.classList.toggle("open");
      hamburger.innerHTML = open ? ICON_X : ICON_MENU;
    });
    mobilePanel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        mobilePanel.classList.remove("open");
        hamburger.innerHTML = ICON_MENU;
      });
    });
  }

  /* 管理后台汉堡菜单 */
  var admHam = document.getElementById("admHamburger");
  var admPanel = document.getElementById("admMobilePanel");
  if (admHam && admPanel) {
    admHam.addEventListener("click", function () {
      var open = admPanel.classList.toggle("open");
      admHam.innerHTML = open ? ICON_X : ICON_MENU;
    });
    admPanel.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        admPanel.classList.remove("open");
        admHam.innerHTML = ICON_MENU;
      });
    });
  }

  /* 工单：Markdown 预览 + 图片上传 */
  var mdSource = document.getElementById("mdSource");
  if (mdSource) {
    var mdPreview = document.getElementById("mdPreview");
    function mdRender(src) {
      var esc = src.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
      esc = esc.replace(/```([\s\S]*?)```/g, function (m, c) { return "\n<pre>" + c.trim() + "</pre>\n"; });
      esc = esc.replace(/`([^`\n]+)`/g, "<code>$1</code>");
      esc = esc.replace(/\*\*([^*\n]+)\*\*/g, "<b>$1</b>");
      return esc;
    }
    document.querySelectorAll("[data-mdtab]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        document.querySelectorAll("[data-mdtab]").forEach(function (b) { b.classList.remove("on"); });
        btn.classList.add("on");
        var isPreview = btn.getAttribute("data-mdtab") === "preview";
        mdPreview.style.display = isPreview ? "" : "none";
        mdSource.style.display = isPreview ? "none" : "";
        if (isPreview) mdPreview.innerHTML = mdRender(mdSource.value) || "<span style='color:var(--text-faint)'>暂无内容</span>";
      });
    });

    var upBox = document.getElementById("upBox");
    var imgInput = document.getElementById("imgInput");
    var upThumbs = document.getElementById("upThumbs");
    var imagesInput = document.getElementById("imagesInput");
    var uploaded = [];
    function syncImages() { imagesInput.value = JSON.stringify(uploaded); }
    function renderThumbs() {
      upThumbs.innerHTML = "";
      uploaded.forEach(function (name) {
        var d = document.createElement("div");
        d.className = "th";
        var img = document.createElement("img");
        img.src = "/dashboard/tickets/tmp-img?f=" + encodeURIComponent(name);
        var x = document.createElement("button");
        x.type = "button"; x.textContent = "×";
        x.onclick = function () {
          uploaded = uploaded.filter(function (n) { return n !== name; });
          fetch("/dashboard/tickets/tmp-del?f=" + encodeURIComponent(name), { method: "POST" });
          syncImages(); renderThumbs();
        };
        d.appendChild(img); d.appendChild(x);
        upThumbs.appendChild(d);
      });
    }
    function uploadFiles(list) {
      Array.from(list).forEach(function (f) {
        if (uploaded.length >= 5) { alert("最多上传 5 张图片"); return; }
        if (f.size > 5 * 1024 * 1024) { alert("每张图片不超过 5 MB"); return; }
        var fd = new FormData();
        fd.append("file", f);
        fetch("/dashboard/tickets/upload", { method: "POST", body: fd, credentials: "include" })
          .then(function (r) { return r.json(); })
          .then(function (j) {
            if (j.ok) { uploaded.push(j.name); syncImages(); renderThumbs(); }
            else alert(j.msg || "上传失败");
          });
      });
    }
    var upBox2 = document.getElementById("upBox");
    if (upBox2 && imgInput) {
      upBox2.addEventListener("click", function () { imgInput.click(); });
      imgInput.addEventListener("change", function () { uploadFiles(imgInput.files); imgInput.value = ""; });
      upBox2.addEventListener("dragover", function (e) { e.preventDefault(); upBox2.classList.add("drag"); });
      upBox2.addEventListener("dragleave", function () { upBox2.classList.remove("drag"); });
      upBox2.addEventListener("drop", function (e) { e.preventDefault(); upBox2.classList.remove("drag"); if (e.dataTransfer.files.length) uploadFiles(e.dataTransfer.files); });
      document.addEventListener("paste", function (e) {
        var items = (e.clipboardData || {}).items || [];
        var imgs = [];
        for (var i = 0; i < items.length; i++) {
          if (items[i].type.indexOf("image") === 0) imgs.push(items[i].getAsFile());
        }
        if (imgs.length) uploadFiles(imgs);
      });
    }
  }

  /* 创建实例：套餐(自动开通) / 自定义配置 双路径 */
  var createForm = document.getElementById("createForm");
  if (createForm) {
    var cState = { mode: "monthly", cpu: 1, ram: 1, disk: 10, months: 1, cycle: "monthly", plan: null };
    var CP = {
      base: parseFloat(createForm.getAttribute("data-base")) || 23,
      cpu: parseFloat(createForm.getAttribute("data-cpu-p")) || 10,
      ram: parseFloat(createForm.getAttribute("data-ram-p")) || 12,
      disk: parseFloat(createForm.getAttribute("data-disk-p")) || 0.6,
      balance: parseFloat(createForm.getAttribute("data-balance")) || 0
    };
    var monthsRow = document.getElementById("monthsRow");
    var planCycle = document.getElementById("planCycle");
    var CYCLE_NAMES = { monthly: "月付", quarterly: "季付", semiannually: "半年付", annually: "年付" };
    function billing_cycle_months_js(c) { return c === "quarterly" ? 3 : c === "semiannually" ? 6 : c === "annually" ? 12 : 1; }
    function ramTxt(mb) { return mb >= 1024 ? (Math.round(mb / 1024 * 10) / 10) + " GB" : mb + " MB"; }
    var monthsInput = document.getElementById("monthsInput");
    var osSel = document.getElementById("osSelect");
    var osGrid = document.getElementById("osGrid");
    var productMode = document.getElementById("planSection") ? "plan" : "custom";
    var PLAN_IMG_LABELS = { debian11: "Debian 11", debian12: "Debian 12" };
    var allOses = [];
    try { allOses = JSON.parse(createForm.getAttribute("data-oses") || "[]") || []; } catch (e) { allOses = []; }

    /* 系统镜像下拉：套餐路径只列套餐支持的镜像，自定义路径列出全部镜像 */
    var OS_CHEVRON = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
    function osLogoSVG(name) {
      var k = (name || "").toLowerCase();
      function wrap(inner) { return '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">' + inner + '</svg>'; }
      if (k.indexOf("ubuntu") >= 0) return wrap('<circle cx="12" cy="12" r="7" fill="none" stroke="#E95420" stroke-width="2.4"/><circle cx="12" cy="3.6" r="2.1" fill="#E95420"/><circle cx="4.4" cy="16.2" r="2.1" fill="#E95420"/><circle cx="19.6" cy="16.2" r="2.1" fill="#E95420"/>');
      if (k.indexOf("debian") >= 0) return wrap('<path d="M12 2.8c-4.4 2.6-7 5.8-6 10 .8 3.5 4.2 6 8 5.4-3.2-.5-5.6-2.6-5.7-5.4-.1-3 2.5-6 6.8-7-1-.9-2.1-1.9-3.1-3z" fill="#D70A53"/>');
      if (k.indexOf("alma") >= 0) return wrap('<circle cx="12" cy="12" r="10" fill="#1D3D8F"/><path d="M12 4.6l6 13h-12z" fill="#fff"/>');
      if (k.indexOf("rocky") >= 0) return wrap('<circle cx="12" cy="12" r="10" fill="#10B981"/><path d="M5.5 15.5l4.5-6.5 3 3.4 2.2-2.4 3.3 5.5z" fill="#fff"/>');
      if (k.indexOf("windows") >= 0 || k.indexOf("win serv") >= 0 || k.indexOf("winserver") >= 0) return wrap('<path fill="#0078D4" d="M3 5.4l7.6-1.1v7.2H3zM11.6 4.1L21 2.8v8.7h-9.4zM3 12.7h7.6v7.2L3 18.8zM11.6 12.7H21v8.5l-9.4-1.3z"/>');
      if (k.indexOf("docker") >= 0) return wrap('<g fill="#2496ED"><rect x="4" y="10" width="3" height="3"/><rect x="8" y="10" width="3" height="3"/><rect x="12" y="10" width="3" height="3"/><rect x="8" y="6" width="3" height="3"/></g><path d="M2 14.5h19.4c-.5 3.2-3 5.7-9.6 5.7-6.2 0-9.3-2.6-9.8-5.7z" fill="#2496ED"/>');
      if (k.indexOf("宝塔") >= 0 || k.indexOf("bt") >= 0) return wrap('<rect x="3" y="3" width="18" height="18" rx="4.5" fill="#20a53a"/><rect x="7" y="7" width="10" height="4.2" rx="1" fill="#fff"/><rect x="7" y="12.8" width="10" height="4.2" rx="1" fill="#fff"/>');
      return wrap('<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><text x="12" y="15.5" text-anchor="middle" font-size="9.5" font-weight="700" fill="currentColor">' + (name || "?").charAt(0) + '</text>');
    }
    function escHtml(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
    function fillOs() {
      if (!osGrid || !osSel) return;
      var prev = osSel.value;
      var list = (productMode === "plan" && cState.plan && (cState.plan.images || []).length)
        ? cState.plan.images : allOses;
      if (list.indexOf(prev) < 0) prev = list.length ? list[0] : "";
      osSel.value = prev;
      var curLabel = prev ? (PLAN_IMG_LABELS[prev] || prev) : "";
      var opts = "";
      list.forEach(function (key) {
        var label = PLAN_IMG_LABELS[key] || key;
        opts += '<button type="button" class="os-opt' + (key === prev ? " on" : "") + '" data-os="' + escHtml(key) + '">' +
          '<span class="os-logo">' + osLogoSVG(key) + '</span>' +
          '<span class="os-name">' + escHtml(label) + '</span>' +
          (key === prev ? '<span class="os-ck">✓</span>' : '') +
          '</button>';
      });
      osGrid.innerHTML =
        '<button type="button" class="os-dd-btn" id="osDdBtn" aria-haspopup="listbox">' +
          '<span class="os-dd-cur">' +
            (prev
              ? '<span class="os-logo">' + osLogoSVG(prev) + '</span><span class="os-name">' + escHtml(curLabel) + '</span>'
              : '<span class="os-name" style="color:var(--text-faint);font-weight:400;">选择系统镜像</span>') +
          '</span>' +
          '<span class="os-dd-caret">' + OS_CHEVRON + '</span>' +
        '</button>' +
        '<div class="os-dd-menu" id="osDdMenu" hidden>' + opts + '</div>';
      var btn = document.getElementById("osDdBtn");
      var menu = document.getElementById("osDdMenu");
      btn.addEventListener("click", function (e) {
        e.stopPropagation();
        menu.hidden = !menu.hidden;
        btn.classList.toggle("open", !menu.hidden);
      });
      menu.querySelectorAll(".os-opt").forEach(function (o) {
        o.addEventListener("click", function (e) {
          e.stopPropagation();
          osSel.value = o.getAttribute("data-os");
          fillOs();
          calc();
        });
      });
    }
    /* 点击页面其他位置关闭镜像下拉 */
    document.addEventListener("click", function () {
      document.querySelectorAll(".os-dd-menu").forEach(function (m) { m.hidden = true; });
      document.querySelectorAll(".os-dd-btn.open").forEach(function (b) { b.classList.remove("open"); });
    });
    /* 同一时刻只提交一个 months 输入框 */
    function syncMonthsEnabled() {
      if (planCycle) planCycle.disabled = !(productMode === "plan" && cState.mode === "monthly");
      if (monthsInput) monthsInput.disabled = !(productMode === "custom" && cState.mode === "monthly");
    }

    function cFmt(n) { return Number(n).toFixed(2); }
    function syncCfg(key, val) {
      cState[key] = val;
      document.getElementById(key + "Input").value = val;
      document.getElementById(key + "Show").value = val;
      document.getElementById(key + "Range").value = val;
    }
    function calc() {
      if (productMode === "plan" && cState.plan) {
        var p = cState.plan;
        var cp = p.cycle_prices || {};
        var noHourly = !(p.hourly > 0);
        if (cState.mode === "hourly" && noHourly) {
          cState.mode = "monthly";
          document.getElementById("modeInput").value = "monthly";
          document.querySelectorAll(".mode-card").forEach(function (c) { c.classList.toggle("on", c.getAttribute("data-mode") === "monthly"); });
          syncMonthsEnabled();
          var tip = document.getElementById("hourlyUnsupportedTip");
          if (tip) {
            tip.style.display = "";
            clearTimeout(tip._timer);
            tip._timer = setTimeout(function () { tip.style.display = "none"; }, 5000);
          }
        }
        var total = cState.mode === "monthly" ? (cp[cState.cycle] != null ? cp[cState.cycle] : Math.round(p.monthly * billing_cycle_months_js(cState.cycle) * 100) / 100) : p.hourly;
        document.getElementById("payPlanAmount").innerHTML = cState.mode === "monthly" ? "¥" + cFmt(total) : "¥" + Number(p.hourly).toFixed(4) + "<small> /时</small>";
        document.getElementById("payPlanMonths").textContent = cState.mode === "monthly" ? (CYCLE_NAMES[cState.cycle] || cState.cycle) : "按小时计费";
        document.getElementById("sumPlanImage").textContent = osSel && osSel.value ? (PLAN_IMG_LABELS[osSel.value] || osSel.value) : "-";
        document.getElementById("sumPlanMonthly").textContent = "¥" + cFmt(p.monthly) + "/月";
        var need = cState.mode === "monthly" ? total : Math.max(5, Math.round(p.hourly * 24 * 100) / 100);
        /* 填写优惠码时折扣以服务端核算为准，前端不用原价拦截提交 */
        var couponEl = document.getElementById("couponInput");
        var hasCoupon = couponEl && couponEl.value.replace(/\s/g, "") !== "";
        var ins = !hasCoupon && cState.mode === "monthly" && CP.balance < need;
        if (cState.mode !== "monthly" && !hasCoupon) ins = CP.balance < need;
        document.getElementById("planInsufficient").style.display = ins ? "" : "none";
        if (ins) {
          var insMsg = document.getElementById("planInsufficientMsg");
          if (insMsg) {
            insMsg.textContent = cState.mode === "monthly"
              ? "余额不足以支付所选时长（需 ¥" + cFmt(total) + "），当前余额 ¥" + cFmt(CP.balance) + "，请先充值。"
              : "按量计费要求余额至少可运行 24 小时（约 ¥" + cFmt(need) + "），当前余额 ¥" + cFmt(CP.balance) + "，请先充值或调低套餐时价。";
          }
        }
        document.getElementById("planSubmit").style.display = ins ? "none" : "";
        var pmr = document.getElementById("planMonthsRow");
        if (pmr) pmr.style.display = cState.mode === "monthly" ? "" : "none";
        var pch = document.getElementById("planCycleHourly");
        if (pch) pch.style.display = cState.mode === "monthly" ? "none" : "";
        if (planCycle) planCycle.style.display = cState.mode === "monthly" ? "" : "none";
      } else {
        var monthly = CP.base + Math.max(0, cState.cpu - 1) * CP.cpu + Math.max(0, cState.ram - 1) * CP.ram + Math.max(0, cState.disk - 10) * CP.disk;
        monthly = Math.round(monthly * 100) / 100;
        var hourly = Math.round(monthly / 720 * 10000) / 10000;
        var modeEl = document.getElementById("sumMode");
        var specEl = document.getElementById("sumSpec");
        var totEl = document.getElementById("sumTotal");
        if (modeEl) modeEl.textContent = cState.mode === "monthly" ? "包年包月" : "按量计费";
        if (specEl) specEl.textContent = cState.cpu + " vCPU · " + cState.ram + " GB · " + cState.disk + " GB";
        if (totEl) totEl.innerHTML = cState.mode === "monthly" ? "¥" + cFmt(monthly * cState.months) : "¥" + Number(hourly).toFixed(4) + "<small> /时</small>";
        if (monthsRow) monthsRow.style.display = cState.mode === "monthly" ? "" : "none";
      }
    }
    ["cpu", "ram", "disk"].forEach(function (key) {
      var range = document.getElementById(key + "Range");
      if (!range) return;
      range.addEventListener("input", function () { syncCfg(key, parseInt(range.value, 10) || 1); calc(); });
    });
    document.querySelectorAll("[data-step]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var key = btn.getAttribute("data-step");
        var d = parseInt(btn.getAttribute("data-d"), 10);
        var range = document.getElementById(key + "Range");
        var min = parseInt(range.min, 10), max = parseInt(range.max, 10), step = parseInt(range.step, 10) || 1;
        var val = Math.min(max, Math.max(min, (parseInt(range.value, 10) || min) + d * step));
        syncCfg(key, val);
        calc();
      });
    });
    if (planCycle) planCycle.addEventListener("change", function () {
      cState.cycle = planCycle.value;
      calc();
    });
    if (monthsInput) monthsInput.addEventListener("change", function () {
      cState.months = Math.max(1, Math.min(36, parseInt(monthsInput.value, 10) || 1));
      monthsInput.value = cState.months;
      calc();
    });
    if (osSel) osSel.addEventListener("change", calc);
    var couponInputEl = document.getElementById("couponInput");
    if (couponInputEl) couponInputEl.addEventListener("input", calc);
    document.querySelectorAll(".mode-card").forEach(function (card) {
      card.addEventListener("click", function () {
        document.querySelectorAll(".mode-card").forEach(function (c) { c.classList.remove("on"); });
        card.classList.add("on");
        cState.mode = card.getAttribute("data-mode") || "monthly";
        document.getElementById("modeInput").value = cState.mode;
        syncMonthsEnabled();
        calc();
      });
    });

    /* 产品路径切换：套餐 / 自定义 */
    function setProd(mode) {
      productMode = mode;
      var planSec = document.getElementById("planSection");
      var cusSec = document.getElementById("customSection");
      var planBox = document.getElementById("sumPlanBox");
      var cusBox = document.getElementById("sumCustomBox");
      if (planSec) planSec.style.display = mode === "plan" ? "" : "none";
      if (cusSec) cusSec.style.display = mode === "plan" ? "none" : "";
      if (planBox) planBox.style.display = mode === "plan" ? "" : "none";
      if (cusBox) cusBox.style.display = mode === "plan" ? "none" : "";
      document.getElementById("planIdInput").disabled = mode !== "plan";
      ["cpu", "ram", "disk"].forEach(function (k) {
        var inp = document.getElementById(k + "Input");
        if (inp) inp.disabled = mode !== "custom";
      });
      fillOs();
      syncMonthsEnabled();
      calc();
    }
    document.querySelectorAll("[data-prod]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        document.querySelectorAll("[data-prod]").forEach(function (b) { b.classList.remove("on"); });
        btn.classList.add("on");
        setProd(btn.getAttribute("data-prod"));
      });
    });

    var planOpts = document.querySelectorAll(".plan-pick .opt");
    planOpts.forEach(function (opt) {
      opt.addEventListener("click", function () {
        if (opt.getAttribute("data-soldout") === "1") return; /* 已售罄禁止选择 */
        planOpts.forEach(function (o) { o.classList.remove("on"); });
        opt.classList.add("on");
        try { cState.plan = JSON.parse(opt.getAttribute("data-plan") || "null"); } catch (e) { cState.plan = null; }
        document.getElementById("planIdInput").value = cState.plan ? cState.plan.id : "";
        if (cState.plan) {
          fillOs();
          document.getElementById("sumPlanName").textContent = cState.plan.name;
          document.getElementById("sumPlanSpec").textContent = cState.plan.cpu + " 核 · " + ramTxt(cState.plan.ram_mb) + " 内存 · " + cState.plan.disk_gb + " GB NVMe";
          document.getElementById("sumPlanPorts").textContent = cState.plan.port_count + " 个（共享 IP）";
          document.getElementById("sumPlanBandwidth").textContent = cState.plan.bandwidth_mbps + " Mbps";
          var pd = document.getElementById("sumPlanDesc");
          if (pd) {
            var ptxt = (cState.plan.desc || "").trim();
            pd.textContent = ptxt;
            pd.style.display = ptxt ? "" : "none";
          }
        document.getElementById("sumPlanTraffic").textContent = cState.plan.traffic_gb > 0 ? cState.plan.traffic_gb + " GB（双向）" : "不限";
        if (cState.mode === "hourly" && !(cState.plan.hourly > 0)) {
          document.querySelectorAll(".mode-card").forEach(function (c) { c.classList.toggle("on", c.getAttribute("data-mode") === "monthly"); });
          cState.mode = "monthly";
          document.getElementById("modeInput").value = "monthly";
          syncMonthsEnabled();
          var tip = document.getElementById("hourlyUnsupportedTip");
          if (tip) {
            tip.style.display = "";
            clearTimeout(tip._timer);
            tip._timer = setTimeout(function () { tip.style.display = "none"; }, 5000);
          }
        }
        calc();
        }
      });
    });
    fillOs();
    syncMonthsEnabled();
    var firstAvailOpt = Array.prototype.find.call(planOpts, function (o) { return o.getAttribute("data-soldout") !== "1"; });
    if (firstAvailOpt) firstAvailOpt.click();
    setProd(productMode);
    calc();
  }

  /* 实例详情：ROOT 密码 显隐（小眼睛）与点击复制 */
  var pwText = document.getElementById("rootPw");
  var pwEye = document.getElementById("pwEye");
  var pwTip = document.getElementById("pwTip");
  if (pwText && pwEye) {
    var pwReal = pwText.getAttribute("data-pw") || "";
    var pwShown = false;
    var EYE_ON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
    var EYE_OFF = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><line x1="4" y1="4" x2="20" y2="20"/></svg>';
    var pwTimer = null;
    pwEye.innerHTML = EYE_ON;
    pwEye.addEventListener("click", function () {
      pwShown = !pwShown;
      pwText.textContent = pwShown ? (pwReal || "（开通后生成）") : "●●●●●●●●";
      pwEye.innerHTML = pwShown ? EYE_OFF : EYE_ON;
    });
    pwText.addEventListener("click", function () {
      if (!pwReal) return;
      var ok = function () {
        if (pwTip) {
          pwTip.textContent = "已复制到剪贴板 ✓";
          pwTip.classList.add("ok");
          if (pwTimer) clearTimeout(pwTimer);
          pwTimer = setTimeout(function () {
            pwTip.textContent = "点击密码复制";
            pwTip.classList.remove("ok");
          }, 1600);
        }
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(pwReal).then(ok);
      } else {
        var ta = document.createElement("textarea");
        ta.value = pwReal;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand("copy"); ok(); } catch (e) {}
        document.body.removeChild(ta);
      }
    });
  }

  /* 实例详情：chip 一键复制（IP / SSH 命令等，data-copy 属性） */
  document.addEventListener("click", function (e) {
    var chip = e.target.closest ? e.target.closest("[data-copy]") : null;
    if (!chip) return;
    var val = chip.getAttribute("data-copy");
    if (!val) return;
    var done = function () {
      chip.classList.add("copied");
      setTimeout(function () { chip.classList.remove("copied"); }, 1400);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(val).then(done);
    } else {
      var ta = document.createElement("textarea");
      ta.value = val;
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand("copy"); done(); } catch (err) {}
      document.body.removeChild(ta);
    }
  });

  /* 首页开通演示：实例卡 + 终端逐行动画 */
  var instDemo = document.getElementById("instDemo");
  var provTerm = document.getElementById("provTerm");
  if (instDemo || provTerm) {
    var demoTimers = [];
    function demoLater(fn, ms) { demoTimers.push(setTimeout(fn, ms)); }
    function demoClear() { demoTimers.forEach(clearTimeout); demoTimers = []; }

    /* 实例卡：步骤逐个点亮 + 用时计数 */
    function instRun() {
      instDemo.classList.remove("powered-off");
      var steps = instDemo.querySelectorAll(".id-step");
      var tag = document.getElementById("idState");
      var power = document.getElementById("idPower");
      if (tag) { tag.textContent = "● 运行中"; tag.className = "tag run"; }
      if (power) power.innerHTML = "⏻ 停机";
      steps.forEach(function (s) { s.classList.remove("show"); });
      var timerEl = document.getElementById("idTimer");
      steps.forEach(function (s, i) {
        demoLater(function () { s.classList.add("show"); }, 350 + i * 420);
      });
      var t0 = Date.now() + 350;
      function tick() {
        var e = (Date.now() - t0) / 1000;
        if (e < 0) e = 0;
        if (e > 7.8) e = 7.8;
        if (timerEl) timerEl.textContent = "用时 " + e.toFixed(1) + " 秒";
        if (e < 7.8) demoTimers.push(requestAnimationFrame(tick));
      }
      demoTimers.push(requestAnimationFrame(tick));
    }
    var idPower = document.getElementById("idPower");
    if (idPower) idPower.addEventListener("click", function () {
      var off = instDemo.classList.toggle("powered-off");
      var tag = document.getElementById("idState");
      if (tag) { tag.textContent = off ? "● 已停机" : "● 运行中"; tag.className = off ? "tag stopped" : "tag run"; }
      idPower.innerHTML = off ? "⏻ 开机" : "⏻ 停机";
      var note = document.getElementById("idNote");
      if (note) note.innerHTML = off ? "已停机：CPU、内存、带宽立刻停止计费，只保留磁盘存储费。" : "试着点一下「停机」：CPU、内存、带宽立刻停止计费。";
    });
    var idReplay = document.getElementById("idReplay");
    if (idReplay) idReplay.addEventListener("click", instRun);

    /* 终端：逐行浮现 + 自动循环 */
    function termRun() {
      provTerm.classList.remove("run");
      void provTerm.offsetWidth;
      provTerm.classList.add("run");
      demoLater(function termLoop() {
        termRun();
        demoLater(function () {}, 0);
      }, 9500);
    }
    var termReplay = document.getElementById("termReplay");
    if (termReplay) termReplay.addEventListener("click", function () {
      demoClear();
      termRun();
    });

    /* 进入视口才开始，避免首屏浪费 */
    if ("IntersectionObserver" in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          if (en.target === instDemo) instRun();
          if (en.target === provTerm) termRun();
          io.unobserve(en.target);
        });
      }, { threshold: 0.35 });
      if (instDemo) io.observe(instDemo);
      if (provTerm) io.observe(provTerm);
    } else {
      if (instDemo) instRun();
      if (provTerm) termRun();
    }
  }

  /* 图形验证码点击刷新 */
  var captchaImg = document.getElementById("captchaImg");
  if (captchaImg) {
    captchaImg.addEventListener("click", function () {
      captchaImg.src = "/auth/captcha?_=" + Date.now();
    });
  }

  /* 外观分段切换（跟随系统/浅色/深色） */
  document.querySelectorAll("[data-theme-set]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var t = btn.getAttribute("data-theme-set");
      try { localStorage.setItem("nc_theme", t); } catch (e) {}
      if (t === "auto") {
        var dark = window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches;
        document.documentElement.removeAttribute("data-theme");
        if (!dark) document.documentElement.setAttribute("data-theme", "light");
      } else if (t === "light") {
        document.documentElement.setAttribute("data-theme", "light");
      } else {
        document.documentElement.removeAttribute("data-theme");
      }
      document.querySelectorAll("[data-theme-set]").forEach(function (b) { b.classList.toggle("on", b === btn); });
      location.reload();
    });
  });
  (function () {
    var saved = null;
    try { saved = localStorage.getItem("nc_theme"); } catch (e) {}
    var btn = document.querySelector('[data-theme-set="' + (saved || "auto") + '"]');
    document.querySelectorAll("[data-theme-set]").forEach(function (b) { b.classList.toggle("on", b === btn); });
  })();

  /* 顶栏用户/语言下拉菜单 */
  document.querySelectorAll("[data-dropdown]").forEach(function (btn) {
    btn.addEventListener("click", function (ev) {
      ev.stopPropagation();
      var panel = document.getElementById(btn.getAttribute("data-dropdown"));
      if (panel) panel.hidden = !panel.hidden;
    });
  });
  document.addEventListener("click", function (ev) {
    document.querySelectorAll(".user-menu-panel").forEach(function (p) {
      if (!p.hidden && !p.contains(ev.target)) p.hidden = true;
    });
  });

  /* 简易模态框 */
  document.querySelectorAll("[data-modal-open]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var mask = document.getElementById(btn.getAttribute("data-modal-open"));
      if (mask) mask.classList.add("open");
    });
  });
  document.querySelectorAll("[data-modal-close]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var mask = btn.closest(".modal-mask");
      if (mask) mask.classList.remove("open");
    });
  });
  document.querySelectorAll(".modal-mask").forEach(function (mask) {
    mask.addEventListener("click", function (ev) {
      if (ev.target === mask) mask.classList.remove("open");
    });
  });

  /* 套餐表单：切换对接节点后，按节点上报的实际镜像重渲染「可选系统」（图标卡片） */
  document.querySelectorAll(".js-img-field").forEach(function (box) {
    var map = {}, icons = {}, labels = {};
    try { map = JSON.parse(box.getAttribute("data-nodes") || "{}") || {}; } catch (e) { map = {}; }
    try { icons = JSON.parse(box.getAttribute("data-os-icons") || "{}") || {}; } catch (e) { icons = {}; }
    try { labels = JSON.parse(box.getAttribute("data-os-labels") || "{}") || {}; } catch (e) { labels = {}; }
    var serverIco = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01"/><path d="M6 17.5h.01"/></svg>';
    var form = box.closest("form");
    var sel = form ? form.querySelector(".js-node-sel") : null;
    if (sel) {
      sel.addEventListener("change", function () {
        var list = map[sel.value] && map[sel.value].length ? map[sel.value] : ["debian11", "debian12"];
        var html = "";
        list.forEach(function (im) {
          var name = labels[im] || im.charAt(0).toUpperCase() + im.slice(1);
          var ico = icons[im] || serverIco;
          html += '<label class="os-check"><input type="checkbox" name="images[]" value="' + im + '">'
            + '<span class="os-check-ico">' + ico + "</span><span>" + name + "</span></label>";
        });
        box.innerHTML = html;
      });
    }
  });

  /* 首页 Hero：点阵网格 + 鼠标辉光（方格浮现）+ 点击涟漪 */
  var heroFx = document.getElementById("heroFx");
  if (heroFx) {
    var heroBox = heroFx.closest(".hero");
    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var fctx = heroFx.getContext("2d");
    var DOT = 26, GLOW_R = 200, fdpr = Math.min(2, window.devicePixelRatio || 1);
    var fw = 0, fh = 0, fmx = -9e3, fmy = -9e3, ftx = -9e3, fty = -9e3;
    var waves = [];
    var dotRGB = "255,255,255";
    var dotA = 0.05;
    try {
      var cssDot = getComputedStyle(document.documentElement).getPropertyValue("--hero-dot-rgb").trim();
      if (cssDot) dotRGB = cssDot;
      var cssA = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--hero-dot-a"));
      if (!isNaN(cssA) && cssA > 0) dotA = cssA;
    } catch (e) {}
    function fxResize() {
      fw = heroBox.clientWidth; fh = heroBox.clientHeight;
      heroFx.width = Math.round(fw * fdpr);
      heroFx.height = Math.round(fh * fdpr);
      fctx.setTransform(fdpr, 0, 0, fdpr, 0, 0);
    }
    var fxRunning = false;
    function fxDraw(now) {
      fctx.clearRect(0, 0, fw, fh);
      fmx += (ftx - fmx) * 0.16;
      fmy += (fty - fmy) * 0.16;
      var i, j, k;
      for (k = waves.length - 1; k >= 0; k--) {
        if (now - waves[k].t > 900) waves.splice(k, 1);
      }
      var cols = Math.ceil(fw / DOT) + 1, rows = Math.ceil(fh / DOT) + 1;
      for (j = 0; j < rows; j++) {
        for (i = 0; i < cols; i++) {
          var x = i * DOT + DOT / 2, y = j * DOT + DOT / 2;
          var dx = x - fmx, dy = y - fmy;
          var d2 = dx * dx + dy * dy;
          var a = dotA, rad = 1;
          var glow = Math.exp(-d2 / 16200);
          if (glow > 0.02) { a += glow * 0.5; rad += glow * 1.3; }
          for (k = 0; k < waves.length; k++) {
            var age = (now - waves[k].t) / 900;
            var wx = x - waves[k].x, wy = y - waves[k].y;
            var wd = Math.sqrt(wx * wx + wy * wy) - age * 460;
            var band = Math.exp(-(wd * wd) / 1352) * (1 - age);
            if (band > 0.02) { a += band * 0.38; rad += band * 1.2; }
          }
          if (a < 0.012) continue;
          if (a > 0.85) a = 0.85;
          fctx.fillStyle = "rgba(" + dotRGB + "," + a.toFixed(3) + ")";
          fctx.beginPath();
          fctx.arc(x, y, rad, 0, 6.2832);
          fctx.fill();
          if (glow > 0.04) {
            var t = 1 - Math.sqrt(d2) / GLOW_R;
            fctx.strokeStyle = "rgba(" + dotRGB + "," + (t * t * 0.16).toFixed(3) + ")";
            fctx.strokeRect(x - DOT / 2 + 0.5, y - DOT / 2 + 0.5, DOT, DOT);
          }
        }
      }
      if (fxRunning) requestAnimationFrame(fxDraw);
    }
    function fxStart() { if (!fxRunning) { fxRunning = true; requestAnimationFrame(fxDraw); } }
    function fxStop() { fxRunning = false; }
    fxResize();
    window.addEventListener("resize", fxResize);
    if (reduceMotion) {
      fxDraw(performance.now());
    } else {
      heroBox.addEventListener("pointermove", function (e) {
        var b = heroBox.getBoundingClientRect();
        ftx = e.clientX - b.left;
        fty = e.clientY - b.top;
        if (fmx < -8e3) { fmx = ftx; fmy = fty; }
      });
      heroBox.addEventListener("pointerleave", function () { ftx = -9e3; fty = -9e3; });
      heroBox.addEventListener("pointerdown", function (e) {
        var b = heroBox.getBoundingClientRect();
        waves.push({ x: e.clientX - b.left, y: e.clientY - b.top, t: performance.now() });
      });
      if ("IntersectionObserver" in window) {
        new IntersectionObserver(function (entries) {
          if (entries[0].isIntersecting) fxStart(); else fxStop();
        }).observe(heroBox);
      } else {
        fxStart();
      }
    }
  }
})();

/* ---------- 全站页面切换加载特效：顶部进度条（跨页接力） ---------- */
(function () {
  var SS_KEY = "nc_plb_t";
  var bar = null;
  function ensure() {
    if (!bar || !document.body.contains(bar)) {
      bar = document.createElement("div");
      bar.className = "page-load-bar";
      (document.body || document.documentElement).appendChild(bar);
    }
    return bar;
  }
  function show() {
    var b = ensure();
    b.classList.remove("done");
    void b.offsetWidth;
    b.classList.add("go");
    b.style.width = "";
  }
  function done() {
    var b = ensure();
    b.classList.add("done");
    b.style.width = "100%";
    setTimeout(function () {
      b.classList.remove("go", "done");
      b.style.width = "";
    }, 420);
  }
  function mark() {
    try { sessionStorage.setItem(SS_KEY, String(Date.now())); } catch (e) {}
  }
  function markedRecently() {
    try {
      var t = parseInt(sessionStorage.getItem(SS_KEY) || "0", 10);
      return t > 0 && Date.now() - t < 2500;
    } catch (e) { return false; }
  }
  function clearMark() {
    try { sessionStorage.removeItem(SS_KEY); } catch (e) {}
  }
  /* 站内链接点击 → 进度条 + 跨页标记（修饰键新窗口/下载/锚点/外链不拦） */
  document.addEventListener("click", function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target && e.target.closest ? e.target.closest("a[href]") : null;
    if (!a) return;
    var href = a.getAttribute("href") || "";
    if (href === "" || href.charAt(0) === "#" || a.target === "_blank" || a.hasAttribute("download")) return;
    if (/^[a-z]+:\/\//i.test(href) && href.indexOf(location.origin + "/") !== 0) return;
    mark();
    show();
  }, true);
  /* 站内表单提交 → 进度条 + 跨页标记 */
  document.addEventListener("submit", function (e) {
    if (e.defaultPrevented) return;
    var f = e.target;
    if (!f || (f.method || "get").toLowerCase() !== "post") return;
    var action = f.getAttribute("action") || "";
    if (/^[a-z]+:\/\//i.test(action) && action.indexOf(location.origin + "/") !== 0) return;
    mark();
    show();
  }, true);
  /* 新页面加载：2.5 秒内有标记 → 立即显示收尾动画（承接上一页的加载状态） */
  if (markedRecently()) {
    var boot = function () {
      if (!markedRecently()) { clearMark(); return; }
      show();
      setTimeout(function () {
        clearMark();
        done();
      }, 180);
    };
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", boot);
    else boot();
  }
  /* bfcache 返回 → 收尾隐藏 */
  window.addEventListener("pageshow", function (e) { if (e.persisted) done(); });
})();
