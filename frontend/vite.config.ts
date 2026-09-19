import { fileURLToPath, URL } from "node:url";

import vue from "@vitejs/plugin-vue";
import vueI18n from "@intlify/unplugin-vue-i18n/vite";
import tailwindcss from "@tailwindcss/vite";
import { defineConfig } from "vitest/config";

export default defineConfig({
  plugins: [
    vue(),
    vueI18n({
      include: [
        fileURLToPath(new URL("./src/i18n/locales/**/*.json", import.meta.url)),
      ],
    }),
    tailwindcss(),
  ],
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  server: {
    proxy: {
      "/api": "http://localhost:8000",
      "/sanctum": "http://localhost:8000",
    },
  },
  test: {
    environment: "jsdom",
    globals: false,
    setupFiles: ["./src/test/setup.ts"],
  },
});
