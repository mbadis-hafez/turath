import js from "@eslint/js";
import eslintPluginVue from "eslint-plugin-vue";
import tseslint from "typescript-eslint";
import prettier from "eslint-config-prettier";
import globals from "globals";

export default tseslint.config(
  {
    ignores: ["dist", "coverage", "node_modules"],
  },
  {
    languageOptions: {
      globals: { ...globals.browser },
    },
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...eslintPluginVue.configs["flat/recommended"],
  prettier,
  {
    files: ["**/*.vue"],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
      },
    },
    rules: {
      "vue/multi-word-component-names": "off",
    },
  },
);
