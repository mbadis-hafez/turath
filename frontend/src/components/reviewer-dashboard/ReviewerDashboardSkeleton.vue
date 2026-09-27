<script setup lang="ts">
// Layout-stable placeholders while the reviewer dashboard loads.
</script>

<template>
  <div class="animate-pulse" data-testid="reviewer-dashboard-skeleton" aria-hidden="true">
    <div class="border-b-2 border-line pb-6">
      <div class="h-8 w-72 max-w-full rounded bg-neutral-soft" />
      <div class="mt-2 h-4 w-56 max-w-full rounded bg-neutral-soft" />
    </div>
    <div class="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div v-for="i in 4" :key="i" class="rounded-lg border border-line bg-surface p-4">
        <div class="h-4 w-20 rounded bg-neutral-soft" />
        <div class="mt-2 h-8 w-10 rounded bg-neutral-soft" />
      </div>
    </div>
    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_20rem]">
      <div class="rounded-lg border border-line bg-surface p-4">
        <div class="h-5 w-40 rounded bg-neutral-soft" />
        <div class="mt-4 space-y-3">
          <div v-for="i in 4" :key="i" class="h-14 rounded bg-neutral-soft" />
        </div>
      </div>
      <div class="rounded-lg border border-line bg-surface p-4">
        <div class="h-5 w-28 rounded bg-neutral-soft" />
        <div class="mt-4 space-y-2">
          <div v-for="i in 4" :key="i" class="h-9 rounded bg-neutral-soft" />
        </div>
      </div>
    </div>
  </div>
</template>
