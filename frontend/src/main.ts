import "@fontsource/ibm-plex-sans-arabic/400.css";
import "@fontsource/ibm-plex-sans-arabic/500.css";
import "@fontsource/ibm-plex-sans-arabic/600.css";
import "@fontsource/ibm-plex-sans-arabic/700.css";
import "@fontsource/ibm-plex-sans/400.css";
import "@fontsource/ibm-plex-sans/500.css";
import "@fontsource/ibm-plex-sans/600.css";
import "@fontsource/ibm-plex-sans/700.css";
import "@fontsource/amiri/400.css";
import "@fontsource/amiri/700.css";
import "@fontsource/libre-baskerville/400.css";
import "@fontsource/libre-baskerville/700.css";
import "@/styles/main.css";

import { createApp } from "vue";
import { createPinia, setActivePinia } from "pinia";

import App from "@/App.vue";
import { i18n, setDocumentDirection, DEFAULT_LOCALE } from "@/i18n";
import { router } from "@/router";
import { useAuthStore } from "@/stores/auth";
import { setUnauthorizedHandler } from "@/api/http";

async function bootstrap(): Promise<void> {
  const app = createApp(App);
  const pinia = createPinia();

  app.use(pinia);
  setActivePinia(pinia);
  app.use(i18n);
  app.use(router);

  const auth = useAuthStore();
  setUnauthorizedHandler(() => {
    auth.user = null;
  });

  setDocumentDirection(DEFAULT_LOCALE);

  // Resolve the session before mounting so the first paint is auth-aware.
  // The router guard re-checks `initialized` for direct navigation afterwards.
  await auth.fetchUser();

  app.mount("#app");
}

void bootstrap();
