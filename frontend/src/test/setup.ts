import { afterEach } from "vitest";

afterEach(() => {
  localStorage.clear();
  document.documentElement.lang = "ar";
  document.documentElement.dir = "rtl";
  document.title = "";
});
