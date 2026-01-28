(() => {
  const $ = (id) => document.getElementById(id);

  const nameEl = $("productName");
  const notesEl = $("productNotes");
  const resultEl = $("result");
  const statusEl = $("status");
  const btnGenerate = $("btnGenerate");
  const btnCopy = $("btnCopy");

  const setStatus = (msg, type = "muted") => {
    statusEl.className = `status ${type}`;
    statusEl.textContent = msg || "";
  };

  btnGenerate?.addEventListener("click", async () => {
    const name = nameEl.value.trim();
    const notes = notesEl.value.trim();

    if (!name) {
      setStatus("商品名を入力してください", "danger");
      nameEl.focus();
      return;
    }

    btnGenerate.disabled = true;
    btnCopy.disabled = true;
    setStatus("生成中…", "muted");
    resultEl.value = "";

    try {
      const body = new URLSearchParams();
      body.set("name", name);
      body.set("notes", notes);

      const res = await fetch("api/generate.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body
      });

      const json = await res.json();
      if (!json.ok) {
        setStatus(json.error || "エラーが発生しました", "danger");
        return;
      }

      resultEl.value = json.text || "";
      btnCopy.disabled = !resultEl.value;
      setStatus(`生成完了（${json.generated_at}）`, "success");
    } catch (e) {
      setStatus("通信エラーが発生しました", "danger");
    } finally {
      btnGenerate.disabled = false;
    }
  });

  btnCopy?.addEventListener("click", async () => {
    const text = resultEl.value;
    if (!text) return;

    try {
      await navigator.clipboard.writeText(text);
      setStatus("コピーしました", "success");
    } catch {
      // fallback
      resultEl.select();
      document.execCommand("copy");
      setStatus("コピーしました", "success");
    }
  });
})();
// ===== Drawer (hamburger menu) =====
(() => {
  const drawer = document.getElementById("drawer");
  const overlay = document.getElementById("drawerOverlay");
  const btnOpen = document.getElementById("drawerOpen");
  const btnClose = document.getElementById("drawerClose");

  if (!drawer || !overlay || !btnOpen || !btnClose) return;

  const open = () => {
    drawer.classList.add("is-open");
    overlay.hidden = false;
    overlay.classList.add("is-show");
    drawer.setAttribute("aria-hidden", "false");
    document.body.classList.add("no-scroll");
  };

  const close = () => {
    drawer.classList.remove("is-open");
    overlay.classList.remove("is-show");
    drawer.setAttribute("aria-hidden", "true");
    document.body.classList.remove("no-scroll");
    // アニメ終わってからhidden
    setTimeout(() => { overlay.hidden = true; }, 200);
  };

  btnOpen.addEventListener("click", open);
  btnClose.addEventListener("click", close);
  overlay.addEventListener("click", close);

  // ESCで閉じる
  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && drawer.classList.contains("is-open")) close();
  });
})();
(() => {
  const rakutenEl = document.getElementById("rakutenAppId");
  const geminiEl  = document.getElementById("geminiKey");
  const btn       = document.getElementById("btnTestSave");
  const toggle    = document.getElementById("btnToggleGemini");
  const statusEl  = document.getElementById("status");

  if (!rakutenEl || !geminiEl || !btn || !statusEl) return;

  const setStatus = (msg, type="muted") => {
    statusEl.className = `status ${type}`;
    statusEl.textContent = msg || "";
  };

  toggle?.addEventListener("click", () => {
    geminiEl.type = (geminiEl.type === "password") ? "text" : "password";
  });

  btn.addEventListener("click", async () => {
    const rakuten_app_id = rakutenEl.value.trim();
    const gemini_api_key = geminiEl.value.trim();

    if (!rakuten_app_id || !gemini_api_key) {
      setStatus("両方入力してください", "danger");
      return;
    }

    btn.disabled = true;
    setStatus("テスト中…（成功したら自動保存します）", "muted");

    try {
      const body = new URLSearchParams();
      body.set("rakuten_app_id", rakuten_app_id);
      body.set("gemini_api_key", gemini_api_key);

      const res = await fetch("api/test_save_keys.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body
      });

      const json = await res.json();
      if (!json.ok) {
        setStatus(json.error || "テストに失敗しました", "danger");
        return;
      }

      setStatus(
        `${json.message}（楽天例: ${json.rakuten_sample} / Gemini: ${json.gemini_sample} / model: ${json.model}）`,
        "success"
      );
    } catch (e) {
      setStatus("通信エラーが発生しました", "danger");
    } finally {
      btn.disabled = false;
    }
  });
})();
(() => {
  const btn = document.getElementById("btnFetchRanking");
  const statusEl = document.getElementById("fetchStatus");
  const genreInputs = document.querySelectorAll("input[name='genre_ids[]']");
  const genreSelected = document.getElementById("genreSelected");
  const clearGenresBtn = document.getElementById("btnClearGenres");
  const periodEl = document.getElementById("period");
  const hitsEl = document.getElementById("hits");

  if (!btn || !statusEl) return;

  const setStatus = (msg, type = "muted") => {
    statusEl.className = `status ${type}`;
    statusEl.textContent = msg || "";
  };

const renderSelectedGenres = () => {
  if (!genreSelected) return;

  const selected = [...genreInputs]
    .filter((input) => input.checked)
    .map((input) => ({
      id: input.value,
      label: input.dataset.label || input.value
    }));

  genreSelected.innerHTML = "";

  if (selected.length === 0) {
    const span = document.createElement("span");
    span.className = "chip chip--muted";
    span.textContent = "総合（未選択）";
    genreSelected.appendChild(span);
    return;
  }

  selected.forEach((genre) => {
    const chip = document.createElement("span");
    chip.className = "chip";
    chip.dataset.genreId = genre.id;

    const label = document.createElement("span");
    label.textContent = genre.label;

    const x = document.createElement("button");
    x.type = "button";
    x.className = "chip__x";
    x.textContent = "×";
    x.setAttribute("aria-label", `${genre.label} を解除`);

    chip.appendChild(x);
    chip.appendChild(label);
    genreSelected.appendChild(chip);
  });
};

  genreSelected?.addEventListener("click", (e) => {
    const xBtn = e.target.closest(".chip__x");
    if (!xBtn) return;

    const chip = xBtn.closest(".chip");
    const genreId = chip?.dataset.genreId;
    if (!genreId) return;

    // 対応するチェックを外す
    const target = [...genreInputs].find((i) => i.value === genreId);
    if (target) target.checked = false;

    renderSelectedGenres();
  });

  // genreInputs.forEach((input) => {
  //   input.addEventListener("change", renderSelectedGenres);
  // });

  clearGenresBtn?.addEventListener("click", () => {
    genreInputs.forEach((input) => {
      input.checked = false;
    });
    renderSelectedGenres();
  });

  renderSelectedGenres();

  btn.addEventListener("click", async () => {
    btn.disabled = true;
    setStatus("取得中…", "muted");

    try {
      const body = new URLSearchParams();
      genreInputs.forEach((input) => {
        if (input.checked) {
          body.append("genre_ids[]", input.value);
        }
      });
      if (periodEl?.value) body.set("period", periodEl.value);
      if (hitsEl?.value) body.set("hits", hitsEl.value);

      const res = await fetch("api/fetch_ranking.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
        body
      });

      const json = await res.json();
      if (!json.ok) {
        setStatus(json.error || "取得に失敗しました", "danger");
        return;
      }

      setStatus(`${json.message}（${json.fetched_at}）`, "success");
      setTimeout(() => {
        window.location.reload();
      }, 600);
    } catch (e) {
      setStatus("通信エラーが発生しました", "danger");
    } finally {
      btn.disabled = false;
    }
  });
})();

(() => {
  const cards = document.querySelectorAll(".item-card");
  if (!cards.length) return;

  document.addEventListener("click", (event) => {
    const toggle = event.target.closest(".item-card__toggle");
    if (!toggle) return;
    const card = toggle.closest(".item-card");
    if (!card) return;

    const isOpen = card.classList.toggle("is-open");
    const details = card.querySelector(".item-card__details");
    if (details) {
      details.hidden = !isOpen;
    }
    toggle.textContent = isOpen ? "閉じる" : "開く";
    toggle.setAttribute("aria-expanded", String(isOpen));
  });
})();

(() => {
  const infoModal = document.getElementById("infoModal");
  const btnInfo = document.getElementById("btnInfo");
  const btnInfoClose = document.getElementById("btnInfoClose");
  const btnInfoOk = document.getElementById("btnInfoOk");

  if (!infoModal || !btnInfo) return;

  const open = () => {
    infoModal.hidden = false;
    infoModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("no-scroll");
  };
  const close = () => {
    infoModal.hidden = true;
    infoModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("no-scroll");
  };

  btnInfo.addEventListener("click", open);
  btnInfoClose?.addEventListener("click", close);
  btnInfoOk?.addEventListener("click", close);

  infoModal.addEventListener("click", (e) => {
    if (e.target === infoModal) close();
  });

  window.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && !infoModal.hidden) close();
  });
})();
