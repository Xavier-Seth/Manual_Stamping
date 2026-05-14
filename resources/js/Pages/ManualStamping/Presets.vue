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
  return reactive({ x: 10, y: 270, width: 30, height: 10, page_rule: 'last', page_number: '', image: null })
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

function hydrateEsigns(raw) {
  if (!Array.isArray(raw) || raw.length === 0) return []
  return raw.map(e => reactive({
    x:           e.x           ?? 10,
    y:           e.y           ?? 270,
    width:       e.width       ?? 30,
    height:      e.height      ?? 10,
    page_rule:   e.page_rule   ?? 'last',
    page_number: e.page_number ?? '',
    image:       e.image       ?? null,
  }))
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
const createMeta          = reactive({ name: '', description: '', is_active: true })
const createMasterStamps  = ref([newStamp({ label: 'MASTER COPY' })])
const createCtrlStamps    = ref([newStamp({ label: 'CONTROLLED COPY' })])
const createUnctrlStamps  = ref([newStamp({ label: 'UNCONTROLLED COPY' })])
const createEsigns         = ref([newEsign()])
const createEsignActiveIdx = ref(0)
const createTab            = ref('master')
const createActiveIdx      = ref(0)

const createActiveStamps = computed(() => {
  if (createTab.value === 'master')     return createMasterStamps.value
  if (createTab.value === 'controlled') return createCtrlStamps.value
  return createUnctrlStamps.value
})

function resetCreate() {
  createMeta.name = ''; createMeta.description = ''; createMeta.is_active = true
  createMasterStamps.value  = [newStamp({ label: 'MASTER COPY' })]
  createCtrlStamps.value    = [newStamp({ label: 'CONTROLLED COPY' })]
  createUnctrlStamps.value  = [newStamp({ label: 'UNCONTROLLED COPY' })]
  createEsigns.value        = [newEsign()]
  createEsignActiveIdx.value = 0
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

function addCreateEsign() {
  createEsigns.value.push(newEsign())
  createEsignActiveIdx.value = createEsigns.value.length - 1
}
function removeCreateEsign(i) {
  createEsigns.value.splice(i, 1)
  createEsignActiveIdx.value = Math.max(0, Math.min(createEsignActiveIdx.value, createEsigns.value.length - 1))
}
function onCreateEsignDrag({ index, x, y }) {
  const e = createEsigns.value[index]; if (e) { e.x = x; e.y = y }
}

function beginCreate() { resetCreate(); view.value = 'create' }
function cancelCreate() { resetCreate(); view.value = 'dashboard' }

// ─── EDIT form ────────────────────────────────────────────────────────────────
const editingId         = ref(null)
const editMeta          = reactive({ name: '', description: '', is_active: true })
const editMasterStamps  = ref([newStamp()])
const editCtrlStamps    = ref([newStamp()])
const editUnctrlStamps  = ref([newStamp()])
const editEsigns         = ref([])
const editEsignActiveIdx = ref(0)
const editTab            = ref('master')
const editActiveIdx      = ref(0)

const editActiveStamps = computed(() => {
  if (editTab.value === 'master')     return editMasterStamps.value
  if (editTab.value === 'controlled') return editCtrlStamps.value
  return editUnctrlStamps.value
})

function beginEdit(preset) {
  editingId.value         = preset.id
  editMeta.name           = preset.name        ?? ''
  editMeta.description    = preset.description ?? ''
  editMeta.is_active      = !!preset.is_active
  editMasterStamps.value  = hydrateStamps(preset.master_stamps,       'MASTER COPY')
  editCtrlStamps.value    = hydrateStamps(preset.controlled_stamps,   'CONTROLLED COPY')
  editUnctrlStamps.value  = hydrateStamps(preset.uncontrolled_stamps, 'UNCONTROLLED COPY')
  editEsigns.value        = hydrateEsigns(preset.esign)
  editEsignActiveIdx.value = 0
  editTab.value = 'master'; editActiveIdx.value = 0
  view.value = 'edit'
}

function cancelEditAndReturn() {
  editingId.value = null
  editMasterStamps.value = [newStamp()]; editCtrlStamps.value = [newStamp()]; editUnctrlStamps.value = [newStamp()]
  Object.assign(editMeta, { name: '', description: '', is_active: true })
  editEsigns.value        = [newEsign()]
  editEsignActiveIdx.value = 0
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

function addEditEsign() {
  editEsigns.value.push(newEsign())
  editEsignActiveIdx.value = editEsigns.value.length - 1
}
function removeEditEsign(i) {
  editEsigns.value.splice(i, 1)
  editEsignActiveIdx.value = Math.max(0, Math.min(editEsignActiveIdx.value, editEsigns.value.length - 1))
}
function onEditEsignDrag({ index, x, y }) {
  const e = editEsigns.value[index]; if (e) { e.x = x; e.y = y }
}

// ─── Payload ──────────────────────────────────────────────────────────────────
function normalizeStamps(stamps) {
  return stamps.map(s => ({
    label: s.label, sub_label: s.sub_label || null, type: s.type,
    x: Number(s.x), y: Number(s.y), width: Number(s.width), height: Number(s.height),
    page_rule: s.page_rule,
    page_number: s.page_rule === 'specific' && s.page_number !== '' ? Number(s.page_number) : null,
  }))
}

function normalizeEsigns(esigns) {
  return esigns.map(e => ({
    x:           Number(e.x),
    y:           Number(e.y),
    width:       Number(e.width),
    height:      Number(e.height),
    page_rule:   e.page_rule,
    page_number: e.page_rule === 'specific' && e.page_number !== '' ? Number(e.page_number) : null,
    image:       e.image ?? null,
  }))
}

function onEsignImagePick(event, esign) {
  const file = event.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (e) => { esign.image = e.target.result }
  reader.readAsDataURL(file)
  event.target.value = ''
}

function buildPayload(meta, masterStamps, ctrlStamps, unctrlStamps, esigns) {
  return {
    name: meta.name, description: meta.description || null, is_active: !!meta.is_active,
    master_stamps:       normalizeStamps(masterStamps),
    controlled_stamps:   normalizeStamps(ctrlStamps),
    uncontrolled_stamps: normalizeStamps(unctrlStamps),
    esign:               normalizeEsigns(esigns),
  }
}

// ─── Delete / Default ─────────────────────────────────────────────────────────
const deletingId      = ref(null)
const settingDefaultId = ref(null)

function confirmDelete(id) {
  router.delete(`/manual-stamping/presets/${id}`, {
    preserveScroll: true,
    onFinish: () => { deletingId.value = null },
  })
}

function setAsDefault(id) {
  settingDefaultId.value = id
  router.patch(`/manual-stamping/presets/${id}/set-default`, {}, {
    preserveScroll: true,
    onFinish: () => { settingDefaultId.value = null },
  })
}

// ─── Submit ───────────────────────────────────────────────────────────────────
const saving = ref(false)

function submitCreate() {
  if (saving.value) return
  saving.value = true
  router.post(
    '/manual-stamping/presets',
    buildPayload(createMeta, createMasterStamps.value, createCtrlStamps.value, createUnctrlStamps.value, createEsigns.value),
    { preserveScroll: true, onFinish: () => { saving.value = false }, onSuccess: () => cancelCreate() }
  )
}

function submitEdit() {
  if (saving.value || !editingId.value) return
  saving.value = true
  router.put(
    `/manual-stamping/presets/${editingId.value}`,
    buildPayload(editMeta, editMasterStamps.value, editCtrlStamps.value, editUnctrlStamps.value, editEsigns.value),
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
                  <div class="flex items-center gap-1.5 shrink-0">
                    <span v-if="preset.is_default"
                      class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">
                      Default
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold"
                      :class="preset.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500'">
                      {{ preset.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
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
                      <span :class="(preset.esign ?? []).length > 0 ? 'text-emerald-600 font-medium' : 'text-stone-400'">
                        {{ (preset.esign ?? []).length > 0
                          ? `${(preset.esign).length}× · ${(preset.esign)[0].page_rule}`
                          : 'off' }}
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
                      <!-- e-sign boxes -->
                      <div v-for="(esign, ei) in (preset.esign ?? [])" :key="'te' + ei"
                        class="absolute rounded-[1px]"
                        :style="{
                          left:   thumbPx(esign.x) + 'px',
                          top:    thumbPx(esign.y) + 'px',
                          width:  Math.max(thumbPx(esign.width),  4) + 'px',
                          height: Math.max(thumbPx(esign.height), 2) + 'px',
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

                  <!-- Set as Default -->
                  <button v-if="!preset.is_default"
                    class="flex items-center gap-1 text-xs text-stone-400 hover:text-amber-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="settingDefaultId === preset.id"
                    @click="setAsDefault(preset.id)">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                    {{ settingDefaultId === preset.id ? 'Setting…' : 'Set as Default' }}
                  </button>
                  <span v-else class="flex items-center gap-1 text-xs text-amber-600 font-medium">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>
                    Default
                  </span>

                  <!-- Delete -->
                  <div class="ml-auto flex items-center gap-1.5">
                    <template v-if="deletingId === preset.id">
                      <span class="text-xs text-stone-500">Sure?</span>
                      <button class="text-xs font-semibold text-red-600 hover:text-red-800 transition-colors"
                        @click="confirmDelete(preset.id)">
                        Delete
                      </button>
                      <button class="text-xs text-stone-400 hover:text-stone-600 transition-colors"
                        @click="deletingId = null">
                        Cancel
                      </button>
                    </template>
                    <template v-else>
                      <span class="text-[10px] text-stone-400 mr-1">{{ formatRelativeTime(preset.updated_at) }}</span>
                      <button class="flex items-center gap-1 text-xs text-stone-400 hover:text-red-500 transition-colors"
                        @click="deletingId = preset.id">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Delete
                      </button>
                    </template>
                  </div>
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
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-6 pt-5 pb-4">
                  <h3 class="text-sm font-semibold text-stone-900">E-Sign Settings</h3>
                  <button
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                    @click="addCreateEsign">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Add E-Sign
                  </button>
                </div>

                <div v-if="createEsigns.length === 0" class="px-6 pb-5 text-xs text-stone-400">
                  No e-sign boxes. Click "Add E-Sign" to add one.
                </div>

                <div v-else class="px-6 pb-5 space-y-4">
                  <div v-for="(esign, i) in createEsigns" :key="i"
                    class="border rounded-xl p-4 cursor-pointer transition-colors"
                    :class="createEsignActiveIdx === i ? 'border-emerald-400 bg-emerald-50/40' : 'border-stone-200 bg-stone-50/60'"
                    @click="createEsignActiveIdx = i">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-[10px] font-bold uppercase tracking-widest text-stone-400">E-Sign {{ i + 1 }}</span>
                      <button class="text-xs text-red-500 hover:text-red-700 transition-colors" @click.stop="removeCreateEsign(i)">Remove</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">X (mm)</label>
                        <input v-model="esign.x" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Y (mm)</label>
                        <input v-model="esign.y" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Width (mm)</label>
                        <input v-model="esign.width" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Height (mm)</label>
                        <input v-model="esign.height" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page Rule</label>
                        <select v-model="esign.page_rule" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                          <option value="first">First page</option>
                          <option value="last">Last page</option>
                          <option value="specific">Specific page</option>
                        </select>
                      </div>
                      <div v-if="esign.page_rule === 'specific'">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page #</label>
                        <input v-model="esign.page_number" type="number" min="1" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div class="col-span-2 mt-1">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Signature Image</label>
                        <div v-if="esign.image" class="mb-2 flex items-center gap-3">
                          <img :src="esign.image" class="h-10 border border-stone-200 rounded object-contain bg-stone-50 px-1" alt="signature preview" />
                          <button type="button" class="text-xs text-red-500 hover:text-red-700 transition-colors" @click.stop="esign.image = null">
                            Remove image
                          </button>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer w-fit" @click.stop>
                          <input type="file" accept="image/png,image/jpeg,image/jpg" class="hidden"
                            @change="(e) => onEsignImagePick(e, esign)" />
                          <span class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            {{ esign.image ? 'Replace image' : 'Upload signature' }}
                          </span>
                        </label>
                      </div>
                    </div>
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
                    :esigns="createEsigns"
                    :active-index="createActiveIdx"
                    :esign-active-index="createEsignActiveIdx"
                    background-image="/images/template_page1.png"
                    @update:active-index="createActiveIdx = $event"
                    @update:esign-active-index="createEsignActiveIdx = $event"
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
              <div class="bg-white rounded-xl border border-stone-200 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-6 pt-5 pb-4">
                  <h3 class="text-sm font-semibold text-stone-900">E-Sign Settings</h3>
                  <button
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-emerald-700 border border-emerald-300 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors"
                    @click="addEditEsign">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Add E-Sign
                  </button>
                </div>

                <div v-if="editEsigns.length === 0" class="px-6 pb-5 text-xs text-stone-400">
                  No e-sign boxes. Click "Add E-Sign" to add one.
                </div>

                <div v-else class="px-6 pb-5 space-y-4">
                  <div v-for="(esign, i) in editEsigns" :key="i"
                    class="border rounded-xl p-4 cursor-pointer transition-colors"
                    :class="editEsignActiveIdx === i ? 'border-emerald-400 bg-emerald-50/40' : 'border-stone-200 bg-stone-50/60'"
                    @click="editEsignActiveIdx = i">
                    <div class="flex items-center justify-between mb-3">
                      <span class="text-[10px] font-bold uppercase tracking-widest text-stone-400">E-Sign {{ i + 1 }}</span>
                      <button class="text-xs text-red-500 hover:text-red-700 transition-colors" @click.stop="removeEditEsign(i)">Remove</button>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">X (mm)</label>
                        <input v-model="esign.x" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Y (mm)</label>
                        <input v-model="esign.y" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Width (mm)</label>
                        <input v-model="esign.width" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Height (mm)</label>
                        <input v-model="esign.height" type="number" step="0.01" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div>
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page Rule</label>
                        <select v-model="esign.page_rule" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                          <option value="first">First page</option>
                          <option value="last">Last page</option>
                          <option value="specific">Specific page</option>
                        </select>
                      </div>
                      <div v-if="esign.page_rule === 'specific'">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Page #</label>
                        <input v-model="esign.page_number" type="number" min="1" @click.stop class="w-full border border-stone-300 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                      </div>
                      <div class="col-span-2 mt-1">
                        <label class="block text-[11px] font-medium text-stone-500 mb-1">Signature Image</label>
                        <div v-if="esign.image" class="mb-2 flex items-center gap-3">
                          <img :src="esign.image" class="h-10 border border-stone-200 rounded object-contain bg-stone-50 px-1" alt="signature preview" />
                          <button type="button" class="text-xs text-red-500 hover:text-red-700 transition-colors" @click.stop="esign.image = null">
                            Remove image
                          </button>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer w-fit" @click.stop>
                          <input type="file" accept="image/png,image/jpeg,image/jpg" class="hidden"
                            @change="(e) => onEsignImagePick(e, esign)" />
                          <span class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                            </svg>
                            {{ esign.image ? 'Replace image' : 'Upload signature' }}
                          </span>
                        </label>
                      </div>
                    </div>
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
                    :esigns="editEsigns"
                    :active-index="editActiveIdx"
                    :esign-active-index="editEsignActiveIdx"
                    background-image="/images/template_page1.png"
                    @update:active-index="editActiveIdx = $event"
                    @update:esign-active-index="editEsignActiveIdx = $event"
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
