<script setup lang="ts">
import CompletenessDashboardPage from "@/pages/CompletenessDashboardPage.vue";
import EditorDashboardPage from "@/pages/EditorDashboardPage.vue";
import { useAuthStore } from "@/stores/auth";

// Role-aware dashboard: editors get the workspace; everyone else keeps the
// completeness/gaps view (also reachable at /dashboard/completeness).
const auth = useAuthStore();
</script>

<template>
  <EditorDashboardPage v-if="auth.hasRole('editor')" />
  <CompletenessDashboardPage v-else />
</template>
