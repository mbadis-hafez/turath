import { mount } from "@vue/test-utils";
import type { Pinia } from "pinia";
import { createPinia } from "pinia";
import type { Router } from "vue-router";

import { applyLocale, i18n, type AppLocale } from "@/i18n";

interface MountOptions {
  locale?: AppLocale;
  pinia?: Pinia;
  router?: Router;
  props?: Record<string, unknown>;
}

/** Mounts a component with the real i18n instance plus optional pinia/router. */
export function mountWithPlugins(
  component: object,
  options: MountOptions = {},
) {
  const pinia = options.pinia ?? createPinia();
  applyLocale(options.locale ?? "ar");

  const plugins: unknown[][] = [[pinia], [i18n]];
  if (options.router) plugins.push([options.router]);

  return mount(component as never, {
    props: options.props as never,
    global: { plugins: plugins as never },
  });
}
