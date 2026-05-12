<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import StampPreview from '@/Components/StampPreview.vue'

// ─── Factories ────────────────────────────────────────────────────────────────
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
    label: s.label ?? defaultLabel, sub_label: s.sub_label ?? 'LNU',
    type: s.type ?? 'red', x: s.x ?? 140, y: s.y ?? 250,
    width: s.width ?? 34, height: s.height ?? 16,
    page_rule: s.page_rule ?? 'all', page_number: s.page_number ?? '',
  }))
}

function hydrateEsign(raw) {
  if (!raw) return newEsign()
  return {
    enabled: !!raw.enabled, x: raw.x ?? 10, y: raw.y ?? 270,
    width: raw.width ?? 30, height: raw.height ?? 10,
    page_rule: raw.page_rule ?? 'last', page_number: raw.page_number ?? '',
  }
}

// ─── Props ────────────────────────────────────────────────────────────────────
const props = defineProps({ presets: { type: Array, default: () => [] } })

// ─── UI state ─────────────────────────────────────────────────────────────────
const view             = ref('dashboard') // 'dashboard' | 'create' | 'edit'
const activeFilter     = ref('all')       // 'all' | 'active' | 'inactive'
const activeSidebarNav = ref('all')

const COPY_TABS = [
  { key: 'master',       label: 'Master Copy' },
  { key: 'controlled',   label: 'Controlled Copy' },
  { key: 'uncontrolled', label: 'Uncontrolled Copy' },
]

// ─── Dashboard computed ───────────────────────────────────────────────────────
const filteredPresets = computed(() => {
  let list = props.presets
  if (activeSidebarNav.value === 'archive') return list.filter(p => !p.is_active)
  if (activeFilter.value === 'active')   return list.filter(p =>  p.is_active)
  if (activeFilter.value === 'inactive') return list.filter(p => !p.is_active)
  return list
})

const stats = computed(() => ({
  total:    props.presets.length,
  active:   props.presets.filter(p =>  p.is_active).length,
  inactive: props.presets.filter(p => !p.is_active).length,
}))

const recentPresets = computed(() =>
  [...props.presets]
    .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
    .slice(0, 4)
)

function presetsWithStamps(copyKey) {
  const field = `${copyKey}_stamps`
  return props.presets.filter(p => Array.isArray(p[field]) && p[field].length > 0).length
}

function formatRelativeTime(dateStr) {
  if (!dateStr) return ''
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diff < 60)    return 'just now'
  if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`
  return new Date(dateStr).toLocaleDateString()
}

// ─── Thumbnail helpers ────────────────────────────────────────────────────────
const THUMB_W = 76
const THUMB_H = Math.round(THUMB_W * (297 / 210))
const THUMB_S = THUMB_W / 210

function thumbPx(mm) { return (parseFloat(mm) || 0) * THUMB_S }

// ─── CREATE form ──────────────────────────────────────────────────────────────
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
function onCreateStampDrag({ index, x, y }) {
  const s = createActiveStamps.value[index]; if (s) { s.x = x; s.y = y }
}
function onCreateEsignDrag({ x, y }) { createEsign.x = x; createEsign.y = y }

function beginCreate() { resetCreate(); view.value = 'create' }
function cancelCreate() { resetCreate(); view.value = 'dashboard' }

// ─── EDIT form ────────────────────────────────────────────────────────────────
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

function beginEdit(preset) {
  editingId.value        = preset.id
  editMeta.name          = preset.name        ?? ''
  editMeta.description   = preset.description ?? ''
  editMeta.is_active     = !!preset.is_active
  editMasterStamps.value = hydrateStamps(preset.master_stamps,       'MASTER COPY')
  editCtrlStamps.value   = hydrateStamps(preset.controlled_stamps,   'CONTROLLED COPY')
  editUnctrlStamps.value = hydrateStamps(preset.uncontrolled_stamps, 'UNCONTROLLED COPY')
  Object.assign(editEsign, hydrateEsign(preset.esign))
  editTab.value = 'master'; editActiveIdx.value = 0
  view.value = 'edit'
}

function cancelEditAndReturn() {
  editingId.value = null
  editMasterStamps.value = [newStamp()]; editCtrlStamps.value = [newStamp()]; editUnctrlStamps.value = [newStamp()]
  Object.assign(editMeta, { name: '', description: '', is_active: true })
  Object.assign(editEsign, newEsign())
  view.value = 'dashboard'
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
function onEditStampDrag({ index, x, y }) {
  const s = editActiveStamps.value[index]; if (s) { s.x = x; s.y = y }
}
function onEditEsignDrag({ x, y }) { editEsign.x = x; editEsign.y = y }

// ─── Payload ──────────────────────────────────────────────────────────────────
function normalizeStamps(stamps) {
  return stamps.map(s => ({
    label: s.label, sub_label: s.sub_label || null, type: s.type,
    x: Number(s.x), y: Number(s.y), width: Number(s.width), height: Number(s.height),
    page_rule: s.page_rule,
    page_number: s.page_rule === 'specific' && s.page_number !== '' ? Number(s.page_number) : null,
  }))
}

function buildPayload(meta, masterStamps, ctrlStamps, unctrlStamps, esign) {
  let normalizedEsign = null
  if (esign.enabled) {
    normalizedEsign = {
      enabled: true,
      x: esign.x !== '' ? Number(esign.x) : null,
      y: esign.y !== '' ? Number(esign.y) : null,
      width: esign.width !== '' ? Number(esign.width) : 30,
      height: esign.height !== '' ? Number(esign.height) : 10,
      page_rule: esign.page_rule || 'last',
      page_number: esign.page_rule === 'specific' && esign.page_number !== '' ? Number(esign.page_number) : null,
    }
  }
  return {
    name: meta.name, description: meta.description || null, is_active: !!meta.is_active,
    master_stamps: normalizeStamps(masterStamps),
    controlled_stamps: normalizeStamps(ctrlStamps),
    uncontrolled_stamps: normalizeStamps(unctrlStamps),
    esign: normalizedEsign,
  }
}

// ─── Submit ───────────────────────────────────────────────────────────────────
const saving = ref(false)

function submitCreate() {
  if (saving.value) return
  saving.value = true
  router.post(
    '/manual-stamping/presets',
    buildPayload(createMeta, createMasterStamps.value, createCtrlStamps.value, createUnctrlStamps.value, createEsign),
    { preserveScroll: true, onFinish: () => { saving.value = false }, onSuccess: () => cancelCreate() }
  )
}

function submitEdit() {
  if (saving.value || !editingId.value) return
  saving.value = true
  router.put(
    `/manual-stamping/presets/${editingId.value}`,
    buildPayload(editMeta, editMasterStamps.value, editCtrlStamps.value, editUnctrlStamps.value, editEsign),
    { preserveScroll: true, onFinish: () => { saving.value = false }, onSuccess: () => cancelEditAndReturn() }
  )
}
</script>

<template>
  <Head title="Stamp Presets" />

  <div class="flex h-screen overflow-hidden bg-stone-100">

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--  SIDEBAR                                                            -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <aside class="w-60 shrink-0 flex flex-col h-screen bg-stone-900 overflow-hidden">

      <!-- Brand -->
      <div class="px-5 pt-5 pb-4 border-b border-stone-700/60">
        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-400 mb-0.5">Manual Stamping</p>
        <h1 class="text-sm font-bold text-white leading-tight">Stamp Manager</h1>
      </div>

      <!-- Nav -->
      <nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
        <p class="px-2 mb-2 text-[10px] font-bold uppercase tracking-widest text-stone-600">Management</p>

        <button
          class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="activeSidebarNav === 'all' ? 'bg-emerald-600 text-white' : 'text-stone-400 hover:bg-stone-800 hover:text-white'"
          @click="activeSidebarNav = 'all'; activeFilter = 'all'; view = 'dashboard'">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
          All Presets
          <span class="ml-auto text-[10px] px-1.5 py-0.5 rounded-full font-semibold"
            :class="activeSidebarNav === 'all' ? 'bg-emerald-500 text-white' : 'bg-stone-800 text-stone-400'">
            {{ props.presets.length }}
          </span>
        </button>

        <button
          class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="activeSidebarNav === 'archive' ? 'bg-stone-700 text-white' : 'text-stone-400 hover:bg-stone-800 hover:text-white'"
          @click="activeSidebarNav = 'archive'; view = 'dashboard'">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg>
          Archive
          <span v-if="stats.inactive > 0" class="ml-auto text-[10px] px-1.5 py-0.5 rounded-full bg-stone-800 text-stone-400 font-semibold">
            {{ stats.inactive }}
          </span>
        </button>
      </nav>

      <!-- Bottom actions -->
      <div class="px-3 py-4 border-t border-stone-700/60 space-y-1.5">
        <button v-if="view === 'dashboard'"
          class="w-full flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors"
          @click="beginCreate">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
          New Preset
        </button>
        <Link href="/" class="flex items-center gap-2 px-3 py-2 text-stone-400 hover:text-white hover:bg-stone-800 rounded-lg text-sm transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
          Back to Upload
        </Link>
      </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <!--  CONTENT AREA                                                       -->
    <!-- ═══════════════════════════════════════════════════════════════════ -->
    <div class="flex-1 flex overflow-hidden">

      <!-- ─── DASHBOARD VIEW ──────────────────────────────────────────── -->
      <template v-if="view === 'dashboard'">

        <!-- Scrollable main -->
        <div class="flex-1 overflow-y-auto">

          <!-- Sticky header -->
          <div class="sticky top-0 z-10 bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between shadow-sm">
            <div>
              <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 mb-0.5">
                {{ activeSidebarNav === 'archive' ? 'Archive' : 'Manual Stamping' }}
              </p>
              <h2 class="text-lg font-bold text-stone-950">Stamp Presets</h2>
            </div>
            <div class="flex items-center gap-1 bg-stone-100 rounded-lg p-1">
              <button v-for="f in [
                { k: 'all',      label: `All (${stats.total})` },
                { k: 'active',   label: `Active (${stats.active})` },
                { k: 'inactive', label: `Inactive (${stats.inactive})` },
              ]" :key="f.k"
                class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
                :class="activeFilter === f.k ? 'bg-white text-stone-900 shadow-sm' : 'text-stone-500 hover:text-stone-700'"
                @click="activeFilter = f.k">
                {{ f.label }}
              </button>
            </div>
          </div>

          <!-- Cards area -->
          <div class="p-6">

            <!-- Empty state -->
            <div v-if="filteredPresets.length === 0"
              class="flex flex-col items-center justify-center rounded-xl border border-dashed border-stone-300 p-16 text-center">
              <svg class="w-10 h-10 text-stone-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <p class="text-stone-500 text-sm font-medium">
                {{ activeFilter !== 'all' ? `No ${activeFilter} presets` : 'No presets yet' }}
              </p>
              <button v-if="activeFilter !== 'all'" class="mt-3 text-sm text-emerald-600 hover:underline" @click="activeFilter = 'all'">
                Clear filter
              </button>
              <button v-else
                class="mt-4 inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-emerald-700 transition-colors"
                @click="beginCreate">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Create First Preset
              </button>
            </div>

            <!-- Card grid -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div v-for="preset in filteredPresets" :key="preset.id"
                class="bg-white rounded-xl border border-stone-200 shadow-sm hover:shadow-md transition-shadow p-5"
                :class="!preset.is_active ? 'opacity-80' : ''">

                <!-- Card header -->
                <div class="flex items-start justify-between gap-3 mb-1">
                  <h3 class="font-semibold text-stone-950 leading-snug">{{ preset.name }}</h3>
                  <span class="shrink-0 px-2 py-0.5 rounded-full text-[11px] font-semibold"
                    :class="preset.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500'">
                    {{ preset.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
                <p v-if="preset.description" class="text-xs text-stone-500 line-clamp-1 mb-3">{{ preset.description }}</p>
                <p v-else class="mb-3" />

                <div class="border-t border-stone-100 mb-3" />

                <!-- Stamp summary + thumbnail -->
                <div class="flex items-start gap-4">
                  <!-- Summary -->
                  <div class="flex-1 space-y-1.5">
                    <div v-for="tab in COPY_TABS" :key="tab.key" class="flex items-center gap-2 text-[11px]">
                      <span class="w-1.5 h-1.5 rounded-full shrink-0 bg-red-500" />
                      <span class="text-stone-600">{{ tab.label }}</span>
                      <span class="ml-auto text-stone-400 tabular-nums">
                        {{ (preset[tab.key + '_stamps'] ?? []).length }}
                        stamp{{ (preset[tab.key + '_stamps'] ?? []).length !== 1 ? 's' : '' }}
                      </span>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] pt-0.5">
                      <svg class="w-3 h-3 text-stone-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                      <span class="text-stone-400">E-Sign:</span>
                      <span :class="preset.esign?.enabled ? 'text-emerald-600 font-medium' : 'text-stone-400'">
                        {{ preset.esign?.enabled ? `on · ${preset.esign.page_rule}` : 'off' }}
                      </span>
                    </div>
                  </div>

                  <!-- Thumbnail -->
                  <div class="shrink-0 flex flex-col items-center gap-1">
                    <div class="relative overflow-hidden rounded border border-stone-200 bg-white shadow-sm"
                      :style="{ width: THUMB_W + 'px', height: THUMB_H + 'px' }">
                      <!-- doc lines -->
                      <div class="absolute left-2 right-2 top-2 h-[2px] bg-stone-100 rounded" />
                      <div class="absolute left-2 top-5 w-8 h-[2px] bg-stone-100 rounded" />
                      <!-- master stamps -->
                      <div v-for="(stamp, si) in (preset.master_stamps ?? [])" :key="'m' + si"
                        class="absolute rounded-[1px]"
                        :style="{
                          left:   thumbPx(stamp.x) + 'px',
                          top:    thumbPx(stamp.y) + 'px',
                          width:  Math.max(thumbPx(stamp.width),  4) + 'px',
                          height: Math.max(thumbPx(stamp.height), 2) + 'px',
                          border: `1px solid ${stamp.type === 'red' ? '#dc2626' : '#1f2937'}`,
                          backgroundColor: stamp.type === 'red' ? 'rgba(220,38,38,0.12)' : 'rgba(31,41,55,0.08)',
                        }" />
                      <!-- e-sign -->
                      <div v-if="preset.esign?.enabled"
                        class="absolute rounded-[1px]"
                        :style="{
                          left:   thumbPx(preset.esign.x) + 'px',
                          top:    thumbPx(preset.esign.y) + 'px',
                          width:  Math.max(thumbPx(preset.esign.width),  4) + 'px',
                          height: Math.max(thumbPx(preset.esign.height), 2) + 'px',
                          border: '1px solid #1f2937',
                          backgroundColor: 'rgba(31,41,55,0.06)',
                        }" />
                    </div>
                    <p class="text-[9px] text-stone-400">Preview</p>
                  </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-stone-100">
                  <button
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors"
                    @click="beginEdit(preset)">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                    Edit
                  </button>
                  <span class="ml-auto text-[10px] text-stone-400">
                    {{ formatRelativeTime(preset.updated_at) }}
                  </span>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Right stats panel -->
        <div class="w-64 shrink-0 h-full border-l border-stone-200 bg-white overflow-y-auto">
          <div class="p-5 space-y-5">
            <h3 class="font-semibold text-stone-900 text-sm">System Summary</h3>

            <!-- Stats -->
            <div class="space-y-2">
              <div class="bg-emerald-50 rounded-xl p-4">
                <p class="text-3xl font-bold text-stone-950 tabular-nums">{{ stats.total }}</p>
                <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 mt-1">Total Presets</p>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div class="bg-emerald-50 rounded-xl p-3">
                  <p class="text-2xl font-bold text-emerald-700 tabular-nums">{{ stats.active }}</p>
                  <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 mt-0.5">Active</p>
                </div>
                <div class="bg-stone-50 rounded-xl p-3">
                  <p class="text-2xl font-bold text-stone-500 tabular-nums">{{ stats.inactive }}</p>
                  <p class="text-[10px] font-bold uppercase tracking-widest text-stone-500 mt-0.5">Inactive</p>
                </div>
              </div>
            </div>

            <div class="border-t border-stone-100" />

            <!-- Coverage -->
            <div>
              <h4 class="text-xs font-semibold text-stone-700 mb-3">Copy Type Coverage</h4>
              <div class="space-y-3">
                <div v-for="tab in COPY_TABS" :key="tab.key">
                  <div class="flex justify-between text-[11px] mb-1">
                    <span class="text-stone-600">{{ tab.label }}</span>
                    <span class="text-stone-400 tabular-nums">{{ presetsWithStamps(tab.key) }}/{{ stats.total }}</span>
                  </div>
                  <div class="h-1.5 bg-stone-100 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                      :style="{ width: stats.total > 0 ? (presetsWithStamps(tab.key) / stats.total * 100) + '%' : '0%' }" />
                  </div>
                </div>
              </div>
            </div>

            <div class="border-t border-stone-100" />

            <!-- Recently modified -->
            <div>
              <h4 class="text-xs font-semibold text-stone-700 mb-3">Recently Modified</h4>
              <div v-if="recentPresets.length === 0" class="text-xs text-stone-400">No presets yet.</div>
              <div v-else class="space-y-3">
                <div v-for="p in recentPresets" :key="p.id" class="flex items-start gap-2.5">
                  <span class="mt-1.5 w-1.5 h-1.5 rounded-full shrink-0"
                    :class="p.is_active ? 'bg-emerald-500' : 'bg-stone-300'" />
                  <div class="min-w-0">
                    <p class="text-xs font-medium text-stone-800 truncate">{{ p.name }}</p>
                    <p class="text-[10px] text-stone-400">{{ formatRelativeTime(p.updated_at) }}</p>
                  </div>
                  <button class="ml-auto shrink-0 text-[10px] text-stone-400 hover:text-emerald-600 transition-colors" @click="beginEdit(p)">
                    Edit
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>
      </template>

      <!-- ─── CREATE VIEW ──────────────────────────────────────────────── -->
      <template v-else-if="view === 'create'">
        <div class="flex-1 overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 z-10 bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between shadow-sm">
            <div>
              <button class="flex items-center gap-1 text-[11px] text-stone-500 hover:text-stone-700 mb-1 transition-colors" @click="cancelCreate">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Presets
              </button>
              <h2 class="text-lg font-bold text-stone-950">New Preset</h2>
            </div>
            <div class="flex gap-3">
              <button class="px-4 py-2 text-sm font-medium text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors" @click="cancelCreate">
                Cancel
              </button>
              <button :disabled="saving"
                class="px-5 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors disabled:bg-stone-300 disabled:cursor-not-allowed"
                @click="submitCreate">
                {{ saving ? 'Saving…' : 'Create Preset' }}
              </button>
            </div>
          </div>

          <!-- Form + Preview -->
          <div class="p-6 grid grid-cols-12 gap-6">
            <!-- Form (7 cols) -->
            <div class="col-span-7 space-y-5">

              <!-- Details card -->
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-stone-900 mb-4">Preset Details</h3>
                <div class="space-y-4">
                  <div>
                    <label class="block text-xs font-medium text-stone-600 mb-1.5">Preset Name</label>
                    <input v-model="createMeta.name" type="text" placeholder="e.g. Standard LNU Layout"
                      class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-shadow">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-stone-600 mb-1.5">Description</label>
                    <textarea v-model="createMeta.description" rows="2" placeholder="Optional description…"
                      class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-shadow" />
                  </div>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="createMeta.is_active" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-sm text-stone-700">Active preset</span>
                  </label>
                </div>
              </div>

              <!-- Stamps card -->
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b border-stone-200 bg-stone-50">
                  <button v-for="tab in COPY_TABS" :key="tab.key"
                    class="flex-1 py-3.5 text-xs font-semibold transition-colors border-b-2"
                    :class="createTab === tab.key
                      ? 'bg-white text-emerald-700 border-emerald-600'
                      : 'text-stone-500 border-transparent hover:text-stone-700'"
                    @click="createTab = tab.key; createActiveIdx = 0">
                    {{ tab.label }}
                  </button>
                </div>

                <div class="p-6 space-y-4">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-stone-900">
                      {{ COPY_TABS.find(t => t.key === createTab).label }} Stamps
                    </h4>
                    <button
                      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                      @click="addCreateStamp">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                      Add Stamp
                    </button>
                  </div>

                  <div v-for="(stamp, i) in createActiveStamps" :key="i"
                    class="border rounded-xl p-4 cursor-pointer transition-colors"
                    :class="createActiveIdx === i ? 'border-emerald-400 bg-emerald-50/40' : 'border-stone-200 bg-stone-50/60'"
                    @click="createActiveIdx = i">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Stamp {{ i + 1 }}</span>
                      <button v-if="createActiveStamps.length > 1"
                        class="text-xs text-red-500 hover:text-red-700 transition-colors"
                        @click.stop="removeCreateStamp(i)">Remove</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Label</label>
                        <input v-model="stamp.label" type="text" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Sub-label</label>
                        <input v-model="stamp.sub_label" type="text" placeholder="e.g. LNU" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div class="col-span-2">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Color</label>
                        <div class="flex gap-2">
                          <button type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                            :class="stamp.type === 'red' ? 'border-red-400 bg-red-50 text-red-700' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                            @click.stop="stamp.type = 'red'">
                            <span class="w-2 h-2 rounded-full bg-red-500" /> Red
                          </button>
                          <button type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                            :class="stamp.type === 'black' ? 'border-stone-700 bg-stone-100 text-stone-900' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                            @click.stop="stamp.type = 'black'">
                            <span class="w-2 h-2 rounded-full bg-stone-800" /> Black
                          </button>
                        </div>
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">X (mm)</label>
                        <input v-model="stamp.x" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Y (mm)</label>
                        <input v-model="stamp.y" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Width (mm)</label>
                        <input v-model="stamp.width" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Height (mm)</label>
                        <input v-model="stamp.height" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page Rule</label>
                        <select v-model="stamp.page_rule" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                          <option value="all">All pages</option>
                          <option value="first">First page</option>
                          <option value="last">Last page</option>
                          <option value="specific">Specific page</option>
                        </select>
                      </div>
                      <div v-if="stamp.page_rule === 'specific'">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page #</label>
                        <input v-model="stamp.page_number" type="number" min="1" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- E-Sign card -->
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-sm font-semibold text-stone-900">E-Sign Settings</h3>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="createEsign.enabled" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-sm text-stone-700">Enabled</span>
                  </label>
                </div>
                <div v-if="createEsign.enabled" class="grid grid-cols-2 gap-3">
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">X (mm)</label><input v-model="createEsign.x" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">Y (mm)</label><input v-model="createEsign.y" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">Width (mm)</label><input v-model="createEsign.width" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">Height (mm)</label><input v-model="createEsign.height" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div>
                    <label class="block text-[11px] font-medium text-stone-500 mb-1">Page Rule</label>
                    <select v-model="createEsign.page_rule" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      <option value="first">First page</option>
                      <option value="last">Last page</option>
                      <option value="specific">Specific page</option>
                    </select>
                  </div>
                  <div v-if="createEsign.page_rule === 'specific'">
                    <label class="block text-[11px] font-medium text-stone-500 mb-1">Page #</label>
                    <input v-model="createEsign.page_number" type="number" min="1" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                  </div>
                </div>
              </div>

            </div>

            <!-- Preview (5 cols, sticky) -->
            <div class="col-span-5">
              <div class="sticky top-24">
                <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-5">
                  <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-stone-900">Live Canvas Preview</h3>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                      {{ COPY_TABS.find(t => t.key === createTab).label }}
                    </span>
                  </div>
                  <p class="text-[11px] text-stone-400 mb-4">Drag any box to reposition.</p>
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
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- ─── EDIT VIEW ────────────────────────────────────────────────── -->
      <template v-else-if="view === 'edit'">
        <div class="flex-1 overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 z-10 bg-white border-b border-stone-200 px-6 py-4 flex items-center justify-between shadow-sm">
            <div>
              <button class="flex items-center gap-1 text-[11px] text-stone-500 hover:text-stone-700 mb-1 transition-colors" @click="cancelEditAndReturn">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                Back to Presets
              </button>
              <h2 class="text-lg font-bold text-stone-950">Edit Preset Configuration</h2>
            </div>
            <div class="flex gap-3">
              <button class="px-4 py-2 text-sm font-medium text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors" @click="cancelEditAndReturn">
                Cancel
              </button>
              <button :disabled="saving"
                class="px-5 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors disabled:bg-stone-300 disabled:cursor-not-allowed"
                @click="submitEdit">
                {{ saving ? 'Saving…' : 'Save Changes' }}
              </button>
            </div>
          </div>

          <!-- Form + Preview -->
          <div class="p-6 grid grid-cols-12 gap-6">
            <!-- Form (7 cols) -->
            <div class="col-span-7 space-y-5">

              <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-stone-900 mb-4">Preset Details</h3>
                <div class="space-y-4">
                  <div>
                    <label class="block text-xs font-medium text-stone-600 mb-1.5">Preset Name</label>
                    <input v-model="editMeta.name" type="text"
                      class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-stone-600 mb-1.5">Description</label>
                    <textarea v-model="editMeta.description" rows="2"
                      class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                  </div>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="editMeta.is_active" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-sm text-stone-700">Active preset</span>
                  </label>
                </div>
              </div>

              <!-- Stamps card (edit) -->
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="flex border-b border-stone-200 bg-stone-50">
                  <button v-for="tab in COPY_TABS" :key="tab.key"
                    class="flex-1 py-3.5 text-xs font-semibold transition-colors border-b-2"
                    :class="editTab === tab.key
                      ? 'bg-white text-emerald-700 border-emerald-600'
                      : 'text-stone-500 border-transparent hover:text-stone-700'"
                    @click="editTab = tab.key; editActiveIdx = 0">
                    {{ tab.label }}
                  </button>
                </div>

                <div class="p-6 space-y-4">
                  <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-stone-900">
                      {{ COPY_TABS.find(t => t.key === editTab).label }} Stamps
                    </h4>
                    <button
                      class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                      @click="addEditStamp">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                      Add Stamp
                    </button>
                  </div>

                  <div v-for="(stamp, i) in editActiveStamps" :key="i"
                    class="border rounded-xl p-4 cursor-pointer transition-colors"
                    :class="editActiveIdx === i ? 'border-emerald-400 bg-emerald-50/40' : 'border-stone-200 bg-stone-50/60'"
                    @click="editActiveIdx = i">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-[10px] font-bold uppercase tracking-widest text-stone-400">Stamp {{ i + 1 }}</span>
                      <button v-if="editActiveStamps.length > 1"
                        class="text-xs text-red-500 hover:text-red-700 transition-colors"
                        @click.stop="removeEditStamp(i)">Remove</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Label</label>
                        <input v-model="stamp.label" type="text" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Sub-label</label>
                        <input v-model="stamp.sub_label" type="text" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div class="col-span-2">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Color</label>
                        <div class="flex gap-2">
                          <button type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                            :class="stamp.type === 'red' ? 'border-red-400 bg-red-50 text-red-700' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                            @click.stop="stamp.type = 'red'">
                            <span class="w-2 h-2 rounded-full bg-red-500" /> Red
                          </button>
                          <button type="button"
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors"
                            :class="stamp.type === 'black' ? 'border-stone-700 bg-stone-100 text-stone-900' : 'border-stone-300 bg-white text-stone-500 hover:bg-stone-50'"
                            @click.stop="stamp.type = 'black'">
                            <span class="w-2 h-2 rounded-full bg-stone-800" /> Black
                          </button>
                        </div>
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">X (mm)</label>
                        <input v-model="stamp.x" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Y (mm)</label>
                        <input v-model="stamp.y" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Width (mm)</label>
                        <input v-model="stamp.width" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Height (mm)</label>
                        <input v-model="stamp.height" type="number" step="0.01" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page Rule</label>
                        <select v-model="stamp.page_rule" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                          <option value="all">All pages</option>
                          <option value="first">First page</option>
                          <option value="last">Last page</option>
                          <option value="specific">Specific page</option>
                        </select>
                      </div>
                      <div v-if="stamp.page_rule === 'specific'">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page #</label>
                        <input v-model="stamp.page_number" type="number" min="1" @click.stop
                          class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- E-Sign (edit) -->
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                  <h3 class="text-sm font-semibold text-stone-900">E-Sign Settings</h3>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input v-model="editEsign.enabled" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                    <span class="text-sm text-stone-700">Enabled</span>
                  </label>
                </div>
                <div v-if="editEsign.enabled" class="grid grid-cols-2 gap-3">
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">X (mm)</label><input v-model="editEsign.x" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">Y (mm)</label><input v-model="editEsign.y" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">Width (mm)</label><input v-model="editEsign.width" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div><label class="block text-[11px] font-medium text-stone-500 mb-1">Height (mm)</label><input v-model="editEsign.height" type="number" step="0.01" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"></div>
                  <div>
                    <label class="block text-[11px] font-medium text-stone-500 mb-1">Page Rule</label>
                    <select v-model="editEsign.page_rule" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      <option value="first">First page</option>
                      <option value="last">Last page</option>
                      <option value="specific">Specific page</option>
                    </select>
                  </div>
                  <div v-if="editEsign.page_rule === 'specific'">
                    <label class="block text-[11px] font-medium text-stone-500 mb-1">Page #</label>
                    <input v-model="editEsign.page_number" type="number" min="1" class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                  </div>
                </div>
              </div>

            </div>

            <!-- Preview (5 cols, sticky) -->
            <div class="col-span-5">
              <div class="sticky top-24">
                <div class="bg-white rounded-xl border border-stone-200 shadow-sm p-5">
                  <div class="flex items-center justify-between mb-1">
                    <h3 class="text-sm font-semibold text-stone-900">Live Canvas Preview</h3>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                      {{ COPY_TABS.find(t => t.key === editTab).label }}
                    </span>
                  </div>
                  <p class="text-[11px] text-stone-400 mb-4">Drag any box to reposition.</p>
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
              </div>
            </div>
          </div>
        </div>
      </template>

    </div>
  </div>
</template>
