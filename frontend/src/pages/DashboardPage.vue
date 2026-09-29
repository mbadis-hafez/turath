<script setup lang="ts">
import CompletenessDashboardPage from "@/pages/CompletenessDashboardPage.vue";
import EditorDashboardPage from "@/pages/EditorDashboardPage.vue";
import ReviewerDashboardPage from "@/pages/ReviewerDashboardPage.vue";
import { useAuthStore } from "@/stores/auth";

// Role-aware dashboard: editors get the editor workspace, reviewers get the
// reviewer workspace; everyone else keeps the completeness/gaps view (also
// reachable at /dashboard/completeness).
const auth = useAuthStore();
</script>

<template>
  <EditorDashboardPage v-if="auth.hasRole('editor')" />
  <ReviewerDashboardPage v-else-if="auth.hasRole('reviewer')" />
  <CompletenessDashboardPage v-else />
</template>
