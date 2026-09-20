<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useI18n } from "vue-i18n";

import {
  createImportBatch,
  createMappingProfile,
  listMappingProfiles,
} from "@/api/imports";
import { ApiError } from "@/types/api";
import type { ImportBatch, ImportEntityType, ImportMappingProfile } from "@/types/import";

const emit = defineEmits<{
  created: [batch: ImportBatch];
}>();

const { t } = useI18n();

const ENTITY_TYPES: ImportEntityType[] = [
  "artist",
  "artwork",
  "holder",
  "archive_item",
];

const entityType = ref<ImportEntityType>("artist");
const file = ref<File | null>(null);
const fileInputKey = ref(0);

const profiles = ref<ImportMappingProfile[]>([]);
const selectedProfileId = ref("");

interface ColumnMapRow {
  header: string;
  field: string;
}

const columnMapRows = ref<ColumnMapRow[]>([{ header: "", field: "" }]);

const saveAsProfile = ref(false);
const profileName = ref("");

const submitting = ref(false);
const error = ref<unknown>(null);

const usingProfile = computed(() => selectedProfileId.value !== "");

async function loadProfiles(): Promise<void> {
  try {
    const response = await listMappingProfiles(entityType.value);
    profiles.value = response.data;
  } catch {
    profiles.value = [];
  }
}

watch(
  entityType,
  () => {
    selectedProfileId.value = "";
    void loadProfiles();
  },
  { immediate: true },
);

function addColumnRow(): void {
  columnMapRows.value.push({ header: "", field: "" });
}

function removeColumnRow(index: number): void {
  columnMapRows.value.splice(index, 1);
  if (columnMapRows.value.length === 0) addColumnRow();
}

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement;
  file.value = input.files?.[0] ?? null;
}

function resetFile(): void {
  file.value = null;
  fileInputKey.value += 1;
}

function buildColumnMap(): Record<string, string> {
  const map: Record<string, string> = {};
  for (const row of columnMapRows.value) {
    const header = row.header.trim();
    const field = row.field.trim();
    if (header !== "" && field !== "") map[header] = field;
  }
  return map;
}

const fieldError = computed(() => {
  if (!(error.value instanceof ApiError)) return null;
  return (
    error.value.fieldErrors.file?.[0] ??
    error.value.fieldErrors.entity_type?.[0] ??
    error.value.fieldErrors.column_map?.[0] ??
    null
  );
});

async function submit(): Promise<void> {
  if (!file.value) return;
  error.value = null;
  submitting.value = true;
  try {
    const columnMap = usingProfile.value ? undefined : buildColumnMap();
    const response = await createImportBatch({
      entityType: entityType.value,
      file: file.value,
      mappingProfileId: usingProfile.value ? selectedProfileId.value : undefined,
      columnMap,
    });

    if (!usingProfile.value && saveAsProfile.value && profileName.value.trim() !== "") {
      try {
        await createMappingProfile({
          entity_type: entityType.value,
          name: profileName.value.trim(),
          column_map: columnMap ?? {},
        });
      } catch {
        // The import itself succeeded; a failed profile save shouldn't block that.
      }
    }

    resetFile();
    columnMapRows.value = [{ header: "", field: "" }];
    saveAsProfile.value = false;
    profileName.value = "";
    emit("created", response.data);
  } catch (err) {
    error.value = err;
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <form
    class="space-y-4 rounded-lg border border-line bg-surface p-4"
    @submit.prevent="submit"
  >
    <h2 class="text-lg font-semibold text-ink">{{ t("imports.upload.title") }}</h2>

    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap">
      <label class="block">
        <span class="mb-1 block text-xs font-medium text-ink-muted">{{
          t("imports.upload.entityType")
        }}</span>
        <select
          v-model="entityType"
          class="rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
        >
          <option v-for="type in ENTITY_TYPES" :key="type" :value="type">
            {{ t(`imports.entityType.${type}`) }}
          </option>
        </select>
      </label>

      <label class="block flex-1 min-w-56">
        <span class="mb-1 block text-xs font-medium text-ink-muted">{{
          t("imports.upload.file")
        }}</span>
        <input
          :key="fileInputKey"
          type="file"
          accept=".csv,.txt,.xlsx,.xls"
          class="block w-full text-sm text-ink file:me-3 file:rounded-md file:border-0 file:bg-neutral-soft file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink"
          @change="onFileChange"
        />
        <span class="mt-1 block text-xs text-ink-muted">{{
          t("imports.upload.fileHelp")
        }}</span>
      </label>
    </div>

    <div>
      <label class="block max-w-sm">
        <span class="mb-1 block text-xs font-medium text-ink-muted">{{
          t("imports.upload.mappingProfile")
        }}</span>
        <select
          v-model="selectedProfileId"
          class="w-full rounded-md border border-line bg-surface px-3 py-2 text-sm text-ink focus:border-accent focus:outline-none"
        >
          <option value="">{{ t("imports.upload.mappingProfileNone") }}</option>
          <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
            {{ profile.name }}
          </option>
        </select>
      </label>
    </div>

    <div v-if="!usingProfile" class="space-y-2">
      <p class="text-xs font-medium text-ink-muted">
        {{ t("imports.upload.columnMap") }}
      </p>
      <p class="text-xs text-ink-muted">{{ t("imports.upload.columnMapHelp") }}</p>

      <div
        v-for="(row, index) in columnMapRows"
        :key="index"
        class="flex flex-wrap items-end gap-2"
      >
        <label class="block flex-1 min-w-40">
          <span class="sr-only">{{ t("imports.upload.columnHeader") }}</span>
          <input
            v-model="row.header"
            type="text"
            :placeholder="t('imports.upload.columnHeader')"
            class="w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
          />
        </label>
        <label class="block flex-1 min-w-40">
          <span class="sr-only">{{ t("imports.upload.systemField") }}</span>
          <input
            v-model="row.field"
            type="text"
            :placeholder="t('imports.upload.systemField')"
            class="w-full rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
          />
        </label>
        <button
          type="button"
          class="rounded-md border border-line px-2 py-1.5 text-xs text-ink-muted hover:text-ink"
          @click="removeColumnRow(index)"
        >
          {{ t("imports.upload.removeRow") }}
        </button>
      </div>

      <button
        type="button"
        class="rounded-md border border-line px-3 py-1.5 text-xs font-medium text-ink hover:bg-neutral-soft"
        @click="addColumnRow"
      >
        {{ t("imports.upload.addRow") }}
      </button>

      <div class="flex flex-wrap items-center gap-3 pt-2">
        <label class="flex items-center gap-2 text-sm text-ink">
          <input v-model="saveAsProfile" type="checkbox" class="size-4" />
          {{ t("imports.upload.saveAsProfile") }}
        </label>
        <input
          v-if="saveAsProfile"
          v-model="profileName"
          type="text"
          :placeholder="t('imports.upload.profileName')"
          class="rounded-md border border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-accent focus:outline-none"
        />
      </div>
    </div>

    <p v-if="fieldError" class="text-sm text-danger">{{ fieldError }}</p>
    <p v-else-if="error" class="text-sm text-danger">
      {{ error instanceof Error ? error.message : t("errors.generic") }}
    </p>

    <button
      type="submit"
      class="rounded-md bg-accent px-4 py-2 text-sm font-medium text-surface hover:bg-accent-strong disabled:cursor-not-allowed disabled:opacity-50"
      :disabled="submitting || !file"
    >
      {{ submitting ? t("imports.upload.submitting") : t("imports.upload.submit") }}
    </button>
  </form>
</template>
