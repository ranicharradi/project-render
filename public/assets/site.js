(() => {
  const root = document.documentElement;
  const storageKey = "theme";

  function getSavedTheme() {
    try {
      return localStorage.getItem(storageKey);
    } catch {
      return null;
    }
  }

  function saveTheme(value) {
    try {
      localStorage.setItem(storageKey, value);
    } catch {
      // ignore
    }
  }

  function applyTheme(theme) {
    if (theme === "light") {
      root.setAttribute("data-theme", "light");
      return;
    }
    if (theme === "dark") {
      root.setAttribute("data-theme", "dark");
      return;
    }
    root.removeAttribute("data-theme");
  }

  function nextTheme(current) {
    if (current === "light") return "dark";
    if (current === "dark") return "system";
    return "light";
  }

  function labelFor(theme) {
    if (theme === "light") return "Light";
    if (theme === "dark") return "Dark";
    return "System";
  }

  const saved = getSavedTheme();
  applyTheme(saved);

  const button = document.querySelector("[data-theme-toggle]");
  if (!button) return;

  button.textContent = `Theme: ${labelFor(saved)}`;
  button.addEventListener("click", () => {
    const current = getSavedTheme();
    const next = nextTheme(current);
    if (next === "system") {
      try {
        localStorage.removeItem(storageKey);
      } catch {
        // ignore
      }
      applyTheme(null);
      button.textContent = "Theme: System";
      return;
    }
    saveTheme(next);
    applyTheme(next);
    button.textContent = `Theme: ${labelFor(next)}`;
  });
})();
