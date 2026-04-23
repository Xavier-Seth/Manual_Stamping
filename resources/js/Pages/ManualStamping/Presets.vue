<script setup>
/**
 * Presets.vue
 *
 * ─── WHAT CHANGED ──────────────────────────────────────────────────────────
 * 1. Imported StampPreview component.
 * 2. Removed the old inline drag helpers (startStampDrag / startEsignDrag)
 *    from this file — they lived here only to support the now-deleted inline
 *    preview markup.
 * 3. Replaced every inline preview <div> block with <StampPreview>, wiring:
 *      @stamp-drag  → writes { x, y } back into the correct reactive array
 *      @esign-drag  → writes { x, y } back into the reactive esign object
 *      @update:active-index → keeps the highlighted-stamp index in sync
 * 4. Removed now-unused PREVIEW_W / PREVIEW_H / SCALE / mmToPx / clamp /
 *    STAMP_COLORS constants (they live inside StampPreview).
 *
 * ─── FIX: BACKGROUND IMAGE NOT SHOWING ────────────────────────────────────
 * Both <StampPreview> usages (create + edit) were missing the
 * background-image prop entirely — the component defaulted to '' which
 * evaluates to 'none' in the style binding, so the canvas stayed white.
 * Fixed by passing background-image="/images/template_page1.png" to both.
 */

import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import StampPreview from '@/Components/StampPreview.vue' // adjust path as needed

// ─────────────────────────────────────────────────────────────────────────────
// Factories  — FIX: wrap each stamp in reactive() so Vue tracks mutations
// ─────────────────────────────────────────────────────────────────────────────
function newStamp(overrides = {}) {
  return reactive({
    label: 'MASTER COPY', sub_label: 'LNU', type: 'red',
    x: 140, y: 250, width: 34, height: 16,
    page_rule: 'all', page_number: '',
    ...overrides,
  })
}

function newEsign() {
  return { enabled: false, x: 10, y: 270, width: 30, height: 10, page_rule: 'last', page_number: '' }
}

function hydrateStamps(raw, defaultLabel = 'MASTER COPY') {
  if (!Array.isArray(raw) || raw.length === 0) return [newStamp({ label: defaultLabel })]
  return raw.map(s => newStamp({
    label:       s.label       ?? defaultLabel,
    sub_label:   s.sub_label   ?? 'LNU',
    type:        s.type        ?? 'red',
    x:           s.x           ?? 140,
    y:           s.y           ?? 250,
    width:       s.width       ?? 34,
    height:      s.height      ?? 16,
    page_rule:   s.page_rule   ?? 'all',
    page_number: s.page_number ?? '',
  }))
}

function hydrateEsign(raw) {
  if (!raw) return newEsign()
  return {
    enabled:     !!raw.enabled,
    x:           raw.x           ?? 10,
    y:           raw.y           ?? 270,
    width:       raw.width       ?? 30,
    height:      raw.height      ?? 10,
    page_rule:   raw.page_rule   ?? 'last',
    page_number: raw.page_number ?? '',
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Props
// ─────────────────────────────────────────────────────────────────────────────
const props = defineProps({ presets: { type: Array, default: () => [] } })

// ─────────────────────────────────────────────────────────────────────────────
// CREATE form
// ─────────────────────────────────────────────────────────────────────────────
const createMeta         = reactive({ name: '', description: '', is_active: true })
const createMasterStamps = ref([newStamp({ label: 'MASTER COPY' })])
const createCtrlStamps   = ref([newStamp({ label: 'CONTROLLED COPY' })])
const createUnctrlStamps = ref([newStamp({ label: 'UNCONTROLLED COPY' })])
const createEsign        = reactive(newEsign())
const createTab          = ref('master')
const createActiveIdx    = ref(0)

const createActiveStamps = computed(() => {
  if (createTab.value === 'master')     return createMasterStamps.value
  if (createTab.value === 'controlled') return createCtrlStamps.value
  return createUnctrlStamps.value
})

function resetCreate() {
  createMeta.name = ''; createMeta.description = ''; createMeta.is_active = true
  createMasterStamps.value = [newStamp({ label: 'MASTER COPY' })]
  createCtrlStamps.value   = [newStamp({ label: 'CONTROLLED COPY' })]
  createUnctrlStamps.value = [newStamp({ label: 'UNCONTROLLED COPY' })]
  Object.assign(createEsign, newEsign())
  createTab.value = 'master'; createActiveIdx.value = 0
}

function addCreateStamp() {
  const arr = createActiveStamps.value
  arr.push(newStamp({ x: 100, y: 20 + arr.length * 20 }))
  createActiveIdx.value = arr.length - 1
}
function removeCreateStamp(i) {
  const arr = createActiveStamps.value
  if (arr.length > 1) { arr.splice(i, 1); createActiveIdx.value = Math.max(0, i - 1) }
}

// FIX: drag handlers — write into the parent's own reactive state
function onCreateStampDrag({ index, x, y }) {
  const stamp = createActiveStamps.value[index]
  if (stamp) { stamp.x = x; stamp.y = y }
}
function onCreateEsignDrag({ x, y }) {
  createEsign.x = x; createEsign.y = y
}

// ─────────────────────────────────────────────────────────────────────────────
// EDIT form
// ─────────────────────────────────────────────────────────────────────────────
const editingId        = ref(null)
const editMeta         = reactive({ name: '', description: '', is_active: true })
const editMasterStamps = ref([newStamp()])
const editCtrlStamps   = ref([newStamp()])
const editUnctrlStamps = ref([newStamp()])
const editEsign        = reactive(newEsign())
const editTab          = ref('master')
const editActiveIdx    = ref(0)

const editActiveStamps = computed(() => {
  if (editTab.value === 'master')     return editMasterStamps.value
  if (editTab.value === 'controlled') return editCtrlStamps.value
  return editUnctrlStamps.value
})

function startEdit(preset) {
  editingId.value         = preset.id
  editMeta.name           = preset.name        ?? ''
  editMeta.description    = preset.description ?? ''
  editMeta.is_active      = !!preset.is_active
  editMasterStamps.value  = hydrateStamps(preset.master_stamps,       'MASTER COPY')
  editCtrlStamps.value    = hydrateStamps(preset.controlled_stamps,   'CONTROLLED COPY')
  editUnctrlStamps.value  = hydrateStamps(preset.uncontrolled_stamps, 'UNCONTROLLED COPY')
  Object.assign(editEsign, hydrateEsign(preset.esign))
  editTab.value = 'master'; editActiveIdx.value = 0
}

function cancelEdit() {
  editingId.value = null
  editMasterStamps.value = [newStamp()]; editCtrlStamps.value = [newStamp()]; editUnctrlStamps.value = [newStamp()]
  Object.assign(editMeta, { name: '', description: '', is_active: true })
  Object.assign(editEsign, newEsign())
}

function addEditStamp() {
  const arr = editActiveStamps.value
  arr.push(newStamp({ x: 100, y: 20 + arr.length * 20 }))
  editActiveIdx.value = arr.length - 1
}
function removeEditStamp(i) {
  const arr = editActiveStamps.value
  if (arr.length > 1) { arr.splice(i, 1); editActiveIdx.value = Math.max(0, i - 1) }
}

// FIX: drag handlers — write into the parent's own reactive state
function onEditStampDrag({ index, x, y }) {
  const stamp = editActiveStamps.value[index]
  if (stamp) { stamp.x = x; stamp.y = y }
}
function onEditEsignDrag({ x, y }) {
  editEsign.x = x; editEsign.y = y
}

// ─────────────────────────────────────────────────────────────────────────────
// Payload builder
// ─────────────────────────────────────────────────────────────────────────────
function normalizeStamps(stamps) {
  return stamps.map(s => ({
    label:       s.label,
    sub_label:   s.sub_label || null,
    type:        s.type,
    x:           Number(s.x),
    y:           Number(s.y),
    width:       Number(s.width),
    height:      Number(s.height),
    page_rule:   s.page_rule,
    page_number: s.page_rule === 'specific' && s.page_number !== '' ? Number(s.page_number) : null,
  }))
}

function buildPayload(meta, masterStamps, ctrlStamps, unctrlStamps, esign) {
  let normalizedEsign = null
  if (esign.enabled) {
    normalizedEsign = {
      enabled:     true,
      x:           esign.x      !== '' ? Number(esign.x)      : null,
      y:           esign.y      !== '' ? Number(esign.y)      : null,
      width:       esign.width  !== '' ? Number(esign.width)  : 30,
      height:      esign.height !== '' ? Number(esign.height) : 10,
      page_rule:   esign.page_rule || 'last',
      page_number: esign.page_rule === 'specific' && esign.page_number !== '' ? Number(esign.page_number) : null,
    }
  }
  return {
    name:                meta.name,
    description:         meta.description || null,
    is_active:           !!meta.is_active,
    master_stamps:       normalizeStamps(masterStamps),
    controlled_stamps:   normalizeStamps(ctrlStamps),
    uncontrolled_stamps: normalizeStamps(unctrlStamps),
    esign:               normalizedEsign,
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Submit
// ─────────────────────────────────────────────────────────────────────────────
const saving = ref(false)

function submitCreate() {
  if (saving.value) return
  saving.value = true
  router.post(
    '/manual-stamping/presets',
    buildPayload(createMeta, createMasterStamps.value, createCtrlStamps.value, createUnctrlStamps.value, createEsign),
    { preserveScroll: true, onFinish: () => { saving.value = false }, onSuccess: () => resetCreate() }
  )
}

function submitEdit() {
  if (saving.value || !editingId.value) return
  saving.value = true
  router.put(
    `/manual-stamping/presets/${editingId.value}`,
    buildPayload(editMeta, editMasterStamps.value, editCtrlStamps.value, editUnctrlStamps.value, editEsign),
    { preserveScroll: true, onFinish: () => { saving.value = false }, onSuccess: () => cancelEdit() }
  )
}

const COPY_TABS = [
  { key: 'master',       label: 'Master Copy' },
  { key: 'controlled',   label: 'Controlled Copy' },
  { key: 'uncontrolled', label: 'Uncontrolled Copy' },
]
</script>

<template>
  <Head title="Stamp Presets" />

  <div class="min-h-screen bg-stone-100 px-4 py-8 text-stone-900 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-7xl space-y-6">

      <!-- Page header -->
      <div class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm font-medium uppercase tracking-[0.18em] text-emerald-700">Manual Stamping</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-stone-950">Stamp Presets</h1>
            <p class="mt-2 text-sm leading-6 text-stone-600">
              Each preset defines independent stamp configurations for the Master, Controlled, and Uncontrolled output documents.
            </p>
          </div>
          <Link href="/" class="shrink-0 rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-400 hover:bg-stone-50">
            ← Back to Upload
          </Link>
        </div>
      </div>

      <!-- Main grid -->
      <div class="grid gap-6 lg:grid-cols-2">

        <!-- ══ CREATE PRESET ══════════════════════════════════════════════════ -->
        <section class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
          <h2 class="text-lg font-semibold text-stone-950">Create Preset</h2>
          <div class="mt-6 space-y-5">

            <div>
              <label class="mb-1 block text-sm font-medium text-stone-700">Preset Name</label>
              <input v-model="createMeta.name" type="text" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm" placeholder="e.g. Standard LNU Stamp">
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-stone-700">Description</label>
              <textarea v-model="createMeta.description" rows="2" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm" placeholder="Optional description" />
            </div>

            <!-- Copy-type tabs -->
            <div class="flex gap-1 rounded-lg bg-stone-100 p-1">
              <button v-for="tab in COPY_TABS" :key="tab.key" type="button"
                class="flex-1 rounded-md px-2 py-1.5 text-xs font-semibold transition"
                :class="createTab === tab.key ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'"
                @click="createTab = tab.key; createActiveIdx = 0">
                {{ tab.label }}
              </button>
            </div>

            <!-- Stamp list for active tab -->
            <div class="space-y-3">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                  {{ COPY_TABS.find(t => t.key === createTab).label }} Stamps
                </span>
                <button type="button"
                  class="flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                  @click="addCreateStamp">＋ Add Stamp</button>
              </div>

              <div v-for="(stamp, i) in createActiveStamps" :key="i"
                class="rounded-lg border p-4 transition-colors cursor-pointer"
                :class="createActiveIdx === i ? 'border-emerald-400 bg-emerald-50/40' : 'border-stone-200 bg-stone-50'"
                @click="createActiveIdx = i">
                <div class="mb-3 flex items-center justify-between">
                  <span class="text-xs font-semibold uppercase tracking-wide text-stone-500">Stamp {{ i + 1 }}</span>
                  <button v-if="createActiveStamps.length > 1" type="button" class="text-xs text-red-500 hover:text-red-700" @click.stop="removeCreateStamp(i)">Remove</button>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">Label</label>
                    <input v-model="stamp.label" type="text" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">Sub-label</label>
                    <input v-model="stamp.sub_label" type="text" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm" placeholder="e.g. LNU">
                  </div>
                  <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs text-stone-600">Color</label>
                    <div class="flex gap-2">
                      <button type="button" class="flex-1 rounded-lg border py-1.5 text-xs font-semibold transition"
                        :class="stamp.type === 'red' ? 'border-red-400 bg-red-50 text-red-700' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                        @click.stop="stamp.type = 'red'">🔴 Red</button>
                      <button type="button" class="flex-1 rounded-lg border py-1.5 text-xs font-semibold transition"
                        :class="stamp.type === 'black' ? 'border-stone-700 bg-stone-100 text-stone-900' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                        @click.stop="stamp.type = 'black'">⬛ Black</button>
                    </div>
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">X (mm)</label>
                    <input v-model="stamp.x" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">Y (mm)</label>
                    <input v-model="stamp.y" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">Width (mm)</label>
                    <input v-model="stamp.width" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">Height (mm)</label>
                    <input v-model="stamp.height" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-xs text-stone-600">Page Rule</label>
                    <select v-model="stamp.page_rule" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                      <option value="all">All pages</option>
                      <option value="first">First page</option>
                      <option value="last">Last page</option>
                      <option value="specific">Specific page</option>
                    </select>
                  </div>
                  <div v-if="stamp.page_rule === 'specific'">
                    <label class="mb-1 block text-xs text-stone-600">Page Number</label>
                    <input v-model="stamp.page_number" type="number" min="1" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                  </div>
                </div>
              </div>
            </div>

            <!-- ── Live Preview (CREATE) ───────────────────────────────────── -->
            <!--
              FIX: background-image="/images/template_page1.png" added.
              This is a static string prop (no colon) — it matches the filename
              you confirmed loads at http://localhost:8000/images/template_page1.png.
              Without this prop the component defaults to '' → style becomes
              backgroundImage:'none' → white canvas.
            -->
            <div class="rounded-lg border border-stone-200 bg-stone-50 p-4">
              <h3 class="mb-1 text-sm font-semibold text-stone-900">
                Live Preview — {{ COPY_TABS.find(t => t.key === createTab).label }}
              </h3>
              <p class="mb-3 text-xs text-stone-500">Drag any box to reposition. X/Y fields update in real time.</p>
              <StampPreview
                :stamps="createActiveStamps"
                :esign="createEsign"
                :active-index="createActiveIdx"
                background-image="/images/template_page1.png"
                @update:active-index="createActiveIdx = $event"
                @stamp-drag="onCreateStampDrag"
                @esign-drag="onCreateEsignDrag"
              />
            </div>

            <!-- E-Sign -->
            <div class="rounded-lg border border-stone-200 p-4">
              <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-stone-900">E-Sign Settings</h3>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-700">
                  <input v-model="createEsign.enabled" type="checkbox"> Enabled
                </label>
              </div>
              <div v-if="createEsign.enabled" class="mt-4 grid gap-3 sm:grid-cols-2">
                <div><label class="mb-1 block text-xs text-stone-600">X (mm)</label><input v-model="createEsign.x" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                <div><label class="mb-1 block text-xs text-stone-600">Y (mm)</label><input v-model="createEsign.y" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                <div><label class="mb-1 block text-xs text-stone-600">Width (mm)</label><input v-model="createEsign.width" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                <div><label class="mb-1 block text-xs text-stone-600">Height (mm)</label><input v-model="createEsign.height" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                <div>
                  <label class="mb-1 block text-xs text-stone-600">Page Rule</label>
                  <select v-model="createEsign.page_rule" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                    <option value="first">First page</option>
                    <option value="last">Last page</option>
                    <option value="specific">Specific page</option>
                  </select>
                </div>
                <div v-if="createEsign.page_rule === 'specific'">
                  <label class="mb-1 block text-xs text-stone-600">Page Number</label>
                  <input v-model="createEsign.page_number" type="number" min="1" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                </div>
              </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-700">
              <input v-model="createMeta.is_active" type="checkbox"> Active preset
            </label>

            <button type="button" :disabled="saving"
              class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-stone-300"
              @click="submitCreate">
              {{ saving ? 'Saving…' : 'Create Preset' }}
            </button>
          </div>
        </section>

        <!-- ══ EXISTING PRESETS ═══════════════════════════════════════════════ -->
        <section class="rounded-lg border border-stone-200 bg-white p-6 shadow-sm">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-stone-950">Existing Presets</h2>
            <span class="text-sm text-stone-500">{{ props.presets.length }} total</span>
          </div>

          <div class="mt-6 space-y-4">
            <div v-if="props.presets.length === 0" class="rounded-lg border border-dashed border-stone-300 p-6 text-center text-sm text-stone-500">
              No presets yet.
            </div>

            <div v-for="preset in props.presets" :key="preset.id" class="rounded-lg border border-stone-200 p-4">

              <!-- ── Read view ─────────────────────────────────────────────── -->
              <template v-if="editingId !== preset.id">
                <div class="flex items-start justify-between gap-4">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="truncate text-base font-semibold text-stone-950">{{ preset.name }}</h3>
                      <span :class="preset.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'" class="rounded-full px-2 py-0.5 text-xs font-medium">
                        {{ preset.is_active ? 'Active' : 'Inactive' }}
                      </span>
                    </div>
                    <p v-if="preset.description" class="mt-1 text-sm text-stone-600">{{ preset.description }}</p>
                    <div class="mt-3 space-y-2">
                      <div v-for="tab in COPY_TABS" :key="tab.key">
                        <p class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                          {{ tab.label }} — {{ (preset[tab.key + '_stamps'] ?? []).length }} stamp(s)
                        </p>
                        <div v-for="(s, si) in (preset[tab.key + '_stamps'] ?? [])" :key="si" class="flex items-center gap-2 text-xs text-stone-600">
                          <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: s.type === 'red' ? '#dc2626' : '#1f2937' }" />
                          <span class="font-medium">{{ s.label }}</span>
                          <span class="text-stone-400">{{ s.sub_label }}</span>
                          <span class="ml-auto text-stone-400">x={{ s.x }}, y={{ s.y }} · {{ s.page_rule }}</span>
                        </div>
                      </div>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">
                      E-Sign: {{ preset.esign?.enabled ? `enabled (${preset.esign.page_rule})` : 'disabled' }}
                    </p>
                  </div>
                  <button type="button"
                    class="shrink-0 rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-400 hover:bg-stone-50"
                    @click="startEdit(preset)">Edit</button>
                </div>
              </template>

              <!-- ── Edit view ─────────────────────────────────────────────── -->
              <template v-else>
                <div class="space-y-4">
                  <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700">Preset Name</label>
                    <input v-model="editMeta.name" type="text" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                  </div>
                  <div>
                    <label class="mb-1 block text-sm font-medium text-stone-700">Description</label>
                    <textarea v-model="editMeta.description" rows="2" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm" />
                  </div>

                  <!-- Copy-type tabs (edit) -->
                  <div class="flex gap-1 rounded-lg bg-stone-100 p-1">
                    <button v-for="tab in COPY_TABS" :key="tab.key" type="button"
                      class="flex-1 rounded-md px-2 py-1.5 text-xs font-semibold transition"
                      :class="editTab === tab.key ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'"
                      @click="editTab = tab.key; editActiveIdx = 0">
                      {{ tab.label }}
                    </button>
                  </div>

                  <!-- Stamp list (edit) -->
                  <div class="space-y-3">
                    <div class="flex items-center justify-between">
                      <span class="text-xs font-semibold uppercase tracking-wide text-stone-500">
                        {{ COPY_TABS.find(t => t.key === editTab).label }} Stamps
                      </span>
                      <button type="button"
                        class="flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100"
                        @click="addEditStamp">＋ Add Stamp</button>
                    </div>

                    <div v-for="(stamp, i) in editActiveStamps" :key="i"
                      class="rounded-lg border p-4 transition-colors cursor-pointer"
                      :class="editActiveIdx === i ? 'border-emerald-400 bg-emerald-50/40' : 'border-stone-200 bg-stone-50'"
                      @click="editActiveIdx = i">
                      <div class="mb-3 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-stone-500">Stamp {{ i + 1 }}</span>
                        <button v-if="editActiveStamps.length > 1" type="button" class="text-xs text-red-500 hover:text-red-700" @click.stop="removeEditStamp(i)">Remove</button>
                      </div>
                      <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">Label</label>
                          <input v-model="stamp.label" type="text" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">Sub-label</label>
                          <input v-model="stamp.sub_label" type="text" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                          <label class="mb-1 block text-xs text-stone-600">Color</label>
                          <div class="flex gap-2">
                            <button type="button" class="flex-1 rounded-lg border py-1.5 text-xs font-semibold transition"
                              :class="stamp.type === 'red' ? 'border-red-400 bg-red-50 text-red-700' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                              @click.stop="stamp.type = 'red'">🔴 Red</button>
                            <button type="button" class="flex-1 rounded-lg border py-1.5 text-xs font-semibold transition"
                              :class="stamp.type === 'black' ? 'border-stone-700 bg-stone-100 text-stone-900' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                              @click.stop="stamp.type = 'black'">⬛ Black</button>
                          </div>
                        </div>
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">X (mm)</label>
                          <input v-model="stamp.x" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">Y (mm)</label>
                          <input v-model="stamp.y" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">Width (mm)</label>
                          <input v-model="stamp.width" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">Height (mm)</label>
                          <input v-model="stamp.height" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                        <div>
                          <label class="mb-1 block text-xs text-stone-600">Page Rule</label>
                          <select v-model="stamp.page_rule" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                            <option value="all">All pages</option>
                            <option value="first">First page</option>
                            <option value="last">Last page</option>
                            <option value="specific">Specific page</option>
                          </select>
                        </div>
                        <div v-if="stamp.page_rule === 'specific'">
                          <label class="mb-1 block text-xs text-stone-600">Page Number</label>
                          <input v-model="stamp.page_number" type="number" min="1" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- ── Live Preview (EDIT) ──────────────────────────────── -->
                  <!--
                    FIX: background-image="/images/template_page1.png" added.
                    Same fix as the create form above — the prop was absent,
                    causing the canvas to remain white.
                  -->
                  <div class="rounded-lg border border-stone-200 bg-stone-50 p-4">
                    <h3 class="mb-1 text-sm font-semibold text-stone-900">
                      Live Preview — {{ COPY_TABS.find(t => t.key === editTab).label }}
                    </h3>
                    <p class="mb-3 text-xs text-stone-500">Drag any box to reposition. X/Y fields update in real time.</p>
                    <StampPreview
                      :stamps="editActiveStamps"
                      :esign="editEsign"
                      :active-index="editActiveIdx"
                      background-image="/images/template_page1.png"
                      @update:active-index="editActiveIdx = $event"
                      @stamp-drag="onEditStampDrag"
                      @esign-drag="onEditEsignDrag"
                    />
                  </div>

                  <!-- E-Sign (edit) -->
                  <div class="rounded-lg border border-stone-200 p-4">
                    <div class="flex items-center justify-between">
                      <h3 class="text-sm font-semibold text-stone-900">E-Sign Settings</h3>
                      <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-700">
                        <input v-model="editEsign.enabled" type="checkbox"> Enabled
                      </label>
                    </div>
                    <div v-if="editEsign.enabled" class="mt-4 grid gap-3 sm:grid-cols-2">
                      <div><label class="mb-1 block text-xs text-stone-600">X (mm)</label><input v-model="editEsign.x" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                      <div><label class="mb-1 block text-xs text-stone-600">Y (mm)</label><input v-model="editEsign.y" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                      <div><label class="mb-1 block text-xs text-stone-600">Width (mm)</label><input v-model="editEsign.width" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                      <div><label class="mb-1 block text-xs text-stone-600">Height (mm)</label><input v-model="editEsign.height" type="number" step="0.01" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm"></div>
                      <div>
                        <label class="mb-1 block text-xs text-stone-600">Page Rule</label>
                        <select v-model="editEsign.page_rule" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                          <option value="first">First page</option>
                          <option value="last">Last page</option>
                          <option value="specific">Specific page</option>
                        </select>
                      </div>
                      <div v-if="editEsign.page_rule === 'specific'">
                        <label class="mb-1 block text-xs text-stone-600">Page Number</label>
                        <input v-model="editEsign.page_number" type="number" min="1" class="w-full rounded-lg border border-stone-300 px-2 py-1.5 text-sm">
                      </div>
                    </div>
                  </div>

                  <label class="flex cursor-pointer items-center gap-2 text-sm text-stone-700">
                    <input v-model="editMeta.is_active" type="checkbox"> Active preset
                  </label>

                  <div class="flex flex-wrap gap-3">
                    <button type="button" :disabled="saving"
                      class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:bg-stone-300"
                      @click="submitEdit">{{ saving ? 'Saving…' : 'Save Changes' }}</button>
                    <button type="button" :disabled="saving"
                      class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-400 hover:bg-stone-50"
                      @click="cancelEdit">Cancel</button>
                  </div>
                </div>
              </template>

            </div>
          </div>
        </section>

      </div>
    </div>
  </div>
</template>