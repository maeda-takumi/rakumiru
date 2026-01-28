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

  const parentInputs = document.querySelectorAll("input[name='parent_genre_id']");
  const genreSelected = document.getElementById("genreSelected");
  const clearGenresBtn = document.getElementById("btnClearGenres");

  const periodEl = document.getElementById("period");
  const hitsEl = document.getElementById("hits");

  const childContainers = document.querySelectorAll(".genre-children[data-parent-id]");

  if (!btn || !statusEl) return;

  const setStatus = (msg, type = "muted") => {
    statusEl.className = `status ${type}`;
    statusEl.textContent = msg || "";
  };

  const escapeHtml = (str) => String(str)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");

  const getChecked = (name) => document.querySelector(`input[name="${name}"]:checked`);
  const setContainerPlaceholder = (container) => {
    container.innerHTML = `
      <ul class="genre-tree">
        <li class="genre-tree__item">
          <span class="muted">親ジャンルを選ぶと表示されます</span>
        </li>
      </ul>
    `;
  };
  const getChildContainer = (parentId) =>
    document.querySelector(`.genre-children[data-parent-id="${parentId}"]`);
  const toggleChildContainers = (activeParentId) => {
    childContainers.forEach((container) => {
      const isActive = container.dataset.parentId === activeParentId;
      container.classList.toggle("is-open", isActive);
    });
  };

  // 親が変わったら子をDBから読み込む
  async function loadChildren(parentId) {
    const childWrap = getChildContainer(parentId);
    if (!childWrap) return;

    childWrap.innerHTML = `
      <ul class="genre-tree">
        <li class="genre-tree__item">
          <span class="muted">読み込み中…</span>
        </li>
      </ul>
    `;

    try {
      // ★さっき作ると言ったAPIのパスに合わせてください
      // 例: /rakumiru/tools/genre_children_api.php
      const url = `/rakumiru/tools/genre_children_api.php?parent=${encodeURIComponent(parentId)}`;
      const res = await fetch(url, { cache: "no-store" });
      const json = await res.json();

      if (!json.ok) throw new Error(json.error || "failed");

      const items = json.items || [];
      if (!items.length) {
        childWrap.innerHTML = `
          <ul class="genre-tree">
            <li class="genre-tree__item">
              <span class="muted">子ジャンルがありません</span>
            </li>
          </ul>
        `;
        return;
      }

      childWrap.innerHTML = `
        <ul class="genre-tree">
          ${items.map(it => `
            <li class="genre-tree__item">
              <label class="genre-option">
                <input type="radio"
                       name="child_genre_id"
                       value="${escapeHtml(it.id)}"
                       data-label="${escapeHtml(it.label)}">
                <span>${escapeHtml(it.label)}</span>
              </label>
            </li>
          `).join("")}
        </ul>
      `;

      // 子を選んだら表示更新
      childWrap.querySelectorAll(`input[name="child_genre_id"]`).forEach((el) => {
        el.addEventListener("change", renderSelectedGenres);
      });

    } catch (e) {
      childWrap.innerHTML = `
        <ul class="genre-tree">
          <li class="genre-tree__item">
            <span class="muted">読み込み失敗：${escapeHtml(e.message || String(e))}</span>
          </li>
        </ul>
      `;
    }
  }

  // 表示（親/子のどちらが選ばれているか）
  const renderSelectedGenres = () => {
    if (!genreSelected) return;

    const parent = getChecked("parent_genre_id");
    const child  = getChecked("child_genre_id");

    genreSelected.innerHTML = "";

    // 子が選ばれていれば子優先、なければ親
    if (child) {
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.dataset.genreId = child.value;

      const pLabel = parent?.dataset.label || parent?.value || "";
      const cLabel = child.dataset.label || child.value;
      chip.textContent = `${pLabel} > ${cLabel}`;

      genreSelected.appendChild(chip);
      return;
    }

    if (parent) {
      const chip = document.createElement("span");
      chip.className = "chip";
      chip.dataset.genreId = parent.value;
      chip.textContent = parent.dataset.label || parent.value;
      genreSelected.appendChild(chip);
      return;
    }

    const span = document.createElement("span");
    span.className = "chip chip--muted";
    span.textContent = "総合（未選択）";
    genreSelected.appendChild(span);
  };

  // 親ジャンル変更 → 子ジャンルロード（＆子選択解除）
  parentInputs.forEach((input) => {
    input.addEventListener("change", async () => {
      // 親変わったら子の選択は消える
      document.querySelectorAll(`input[name="child_genre_id"]`).forEach(i => i.checked = false);
      toggleChildContainers(input.value);
      await loadChildren(input.value);
      renderSelectedGenres();
    });
  });

  clearGenresBtn?.addEventListener("click", () => {
    parentInputs.forEach((i) => { i.checked = false; });
    document.querySelectorAll(`input[name="child_genre_id"]`).forEach(i => i.checked = false);
    childContainers.forEach((container) => {
      container.classList.remove("is-open");
      setContainerPlaceholder(container);
    });
    renderSelectedGenres();
  });

  renderSelectedGenres();
  window.renderSelectedGenres = renderSelectedGenres;

  btn.addEventListener("click", async () => {
    btn.disabled = true;
    setStatus("取得中…", "muted");

    try {
      const body = new URLSearchParams();

      const parent = getChecked("parent_genre_id");
      const child  = getChecked("child_genre_id");

      // ★送信する genre_id は「子があれば子」なければ「親」
      if (child) body.set("genre_id", child.value);
      else if (parent) body.set("genre_id", parent.value);

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
        const url = new URL(window.location.href);
        url.searchParams.set("refresh", Date.now().toString());
        window.location.href = url.toString();
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

(() => {
  const historyList = document.getElementById("fetchHistoryList");
  const itemsList = document.getElementById("itemsList");
  const searchForm = document.querySelector("form.row");
  const activeLabel = document.getElementById("activeRunLabel");

  if (!historyList || !itemsList) return;

  const setActive = (runId) => {
    historyList.querySelectorAll(".history-list__button").forEach((btn) => {
      btn.classList.toggle("is-active", btn.dataset.runId === String(runId));
    });
  };

  const setActiveLabel = (runId) => {
    if (!activeLabel) return;
    const activeButton = historyList.querySelector(
      `.history-list__button[data-run-id="${runId}"]`
    );
    if (!activeButton) {
      activeLabel.textContent = "取得履歴を選択してください";
      return;
    }
    const label = activeButton.dataset.runLabel || "取得";
    const fetchedAt = activeButton.dataset.fetchedAt || "";
    activeLabel.textContent = fetchedAt ? `${label} ${fetchedAt}` : label;
  };
  const updateHiddenRunId = (runId) => {
    const hidden = searchForm?.querySelector("input[name='run_id']");
    if (hidden) hidden.value = String(runId);
  };

  const fetchItems = async (runId) => {
    const params = new URLSearchParams();
    params.set("run_id", runId);

    const q = searchForm?.querySelector("input[name='q']")?.value ?? "";
    const order = searchForm?.querySelector("select[name='order']")?.value ?? "rank";
    if (q.trim() !== "") params.set("q", q.trim());
    if (order) params.set("order", order);

    itemsList.classList.add("is-loading");
    itemsList.innerHTML = '<p class="muted">読み込み中…</p>';

    try {
      const res = await fetch(`api/fetch_run_items.php?${params.toString()}`, { cache: "no-store" });
      const json = await res.json();
      if (!json.ok) {
        itemsList.innerHTML = `<p class="muted">読み込み失敗：${json.error || "エラーが発生しました"}</p>`;
        return;
      }
      itemsList.innerHTML = json.html || "";
      setActive(runId);
      setActiveLabel(runId);
    } catch (e) {
      itemsList.innerHTML = '<p class="muted">通信エラーが発生しました。</p>';
    } finally {
      itemsList.classList.remove("is-loading");
    }
  };

  historyList.addEventListener("click", (event) => {
    const button = event.target.closest(".history-list__button");
    if (!button) return;
    const runId = button.dataset.runId;
    if (!runId) return;
    fetchItems(runId);
  });

  const initialRunId = historyList.dataset.activeRun;
  if (initialRunId) {
    setActiveLabel(initialRunId);
  }
})();