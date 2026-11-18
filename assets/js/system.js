"use strict";
const menu = document.querySelector(".menu-toggle"),
  sidebar = document.getElementById("app-sidebar");
function closeMenu() {
  document.body.classList.remove("menu-open");
  menu?.setAttribute("aria-expanded", "false");
  if (sidebar) sidebar.inert = matchMedia("(max-width:800px)").matches;
}
menu?.addEventListener("click", () => {
  const open = document.body.classList.toggle("menu-open");
  sidebar.inert = !open;
  menu.setAttribute("aria-expanded", String(open));
});
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeMenu();
});
matchMedia("(max-width:800px)").addEventListener("change", closeMenu);
closeMenu();
const themeButton = document.querySelector(".theme-toggle");
function themeLabel() {
  if (themeButton)
    themeButton.textContent =
      document.documentElement.dataset.theme === "dark"
        ? "Light mode"
        : "Dark mode";
}
themeLabel();
themeButton?.addEventListener("click", () => {
  const theme =
    document.documentElement.dataset.theme === "dark" ? "light" : "dark";
  document.documentElement.dataset.theme = theme;
  try {
    localStorage.setItem("quiz-workspace-theme", theme);
  } catch {}
  themeLabel();
});
const search = document.getElementById("question-search"),
  filter = document.getElementById("category-filter");
function filterQuestions() {
  const term = (search?.value || "").trim().toLowerCase(),
    category = filter?.value || "";
  let visible = 0;
  document.querySelectorAll("[data-question-row]").forEach((row) => {
    const show =
      row.textContent.toLowerCase().includes(term) &&
      (!category || row.dataset.category === category);
    row.hidden = !show;
    if (show) visible++;
  });
  const count = document.getElementById("result-count");
  if (count) count.textContent = visible + " questions";
  const empty = document.getElementById("search-empty");
  if (empty) empty.hidden = visible > 0;
}
search?.addEventListener("input", filterQuestions);
filter?.addEventListener("change", filterQuestions);
document.getElementById("clear-filters")?.addEventListener("click", () => {
  search.value = "";
  filter.value = "";
  filterQuestions();
});
if (search) filterQuestions();
const imageInput = document.getElementById("imageInput"),
  preview = document.getElementById("previewImage");
let previewURL = "";
imageInput?.addEventListener("change", () => {
  if (previewURL) URL.revokeObjectURL(previewURL);
  const file = imageInput.files[0];
  if (file) {
    previewURL = URL.createObjectURL(file);
    preview.src = previewURL;
    preview.hidden = false;
  }
});
