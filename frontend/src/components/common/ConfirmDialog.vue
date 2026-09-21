<script setup lang="ts">
import {
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogOverlay,
  AlertDialogPortal,
  AlertDialogRoot,
  AlertDialogTitle,
} from "reka-ui";

defineProps<{
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel: string;
  busy?: boolean;
  error?: string | null;
}>();

const emit = defineEmits<{
  confirm: [];
  cancel: [];
}>();
</script>

<template>
  <AlertDialogRoot :open="open" @update:open="(value: boolean) => !value && emit('cancel')">
    <AlertDialogPortal to="body">
      <AlertDialogOverlay class="fixed inset-0 z-50 bg-ink/40" />
      <AlertDialogContent
        class="fixed inset-x-4 top-1/2 z-50 -translate-y-1/2 rounded-lg border border-line bg-surface p-6 shadow-lg sm:inset-x-auto sm:start-1/2 sm:w-full sm:max-w-md sm:-translate-x-1/2"
      >
        <AlertDialogTitle class="text-balance text-xl font-semibold text-ink">{{ title }}</AlertDialogTitle>
        <AlertDialogDescription class="mt-2 text-pretty text-sm text-ink-muted">
          {{ description }}
        </AlertDialogDescription>
        <p v-if="error" class="mt-4 text-sm text-danger" role="alert">{{ error }}</p>
        <div class="mt-6 flex flex-wrap justify-end gap-2">
          <AlertDialogCancel data-testid="confirm-dialog-cancel" class="rounded-md border border-ink px-4 py-2 text-sm font-medium text-ink hover:bg-neutral-soft">
            {{ cancelLabel }}
          </AlertDialogCancel>
          <AlertDialogAction
            data-testid="confirm-dialog-confirm"
            class="rounded-md bg-danger px-4 py-2 text-sm font-semibold text-surface hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="busy"
            @click="emit('confirm')"
          >
            {{ busy ? "…" : confirmLabel }}
          </AlertDialogAction>
        </div>
      </AlertDialogContent>
    </AlertDialogPortal>
  </AlertDialogRoot>
</template>
