import { computed } from "vue";
import {
  useRoute,
  useRouter,
  type LocationQueryRaw,
  type RouteParamsRaw,
} from "vue-router";

import { isLocale, type AppLocale } from "@/i18n";

/**
 * Builds location objects for named routes, always injecting the current
 * locale param so internal links never hard-code a locale segment.
 */
export function useLocalePath() {
  const route = useRoute();
  const router = useRouter();

  const locale = computed<AppLocale>(() =>
    isLocale(route.params.locale) ? route.params.locale : "ar",
  );

  function localePath(
    name: string,
    params: RouteParamsRaw = {},
    query: LocationQueryRaw = {},
  ) {
    return { name, params: { locale: locale.value, ...params }, query };
  }

  /** Pushes a named route with the current locale injected. */
  async function pushLocalePath(
    name: string,
    params: RouteParamsRaw = {},
    query: LocationQueryRaw = {},
  ) {
    return router.push(localePath(name, params, query));
  }

  return { locale, localePath, pushLocalePath };
}
