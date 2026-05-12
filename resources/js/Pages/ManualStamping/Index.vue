<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  presets: {
    type: Array,
    default: () => [],
  },
  defaultPresetId: {
    type: Number,
    default: null,
  },
})

const file = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
const isDragging = ref(false)
const dragDepth = ref(0)
const errorMessage = ref('')
const successMessage = ref('')
const selectedPresetId = ref(props.defaultPresetId ? String(props.defaultPresetId) : '')

const hasFile = computed(() => file.value !== null)

const formatFileSize = (bytes) => {
  if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB'
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

const isPdfFile = (f) =>
  f && (f.type === 'application/pdf' || /\.pdf$/i.test(f.name))

const clearMessages = () => {
  errorMessage.value = ''
  successMessage.value = ''
}

const setSelectedFile = (f) => {
  clearMessages()

  if (!f) {
    file.value = null
    return
  }

  if (!isPdfFile(f)) {
    file.value = null
    errorMessage.value = 'Please choose a PDF file.'
    return
  }

  file.value = f
}

const openFilePicker = () => {
  if (!uploading.value) fileInput.value?.click()
}

const handleFileChange = (e) => {
  setSelectedFile(e.target.files?.[0] ?? null)
  e.target.value = ''
}

const handleDragEnter = () => {
  if (uploading.value) return
  dragDepth.value++
  isDragging.value = true
}

const handleDragOver = (e) => {
  if (uploading.value) return
  e.dataTransfer.dropEffect = 'copy'
  isDragging.value = true
}

const handleDragLeave = () => {
  if (uploading.value) return
  dragDepth.value = Math.max(0, dragDepth.value - 1)
  if (dragDepth.value === 0) isDragging.value = false
}

const handleDrop = (e) => {
  if (uploading.value) return
  dragDepth.value = 0
  isDragging.value = false
  setSelectedFile(e.dataTransfer?.files?.[0] ?? null)
}

const resetDropState = () => {
  dragDepth.value = 0
  isDragging.value = false
}

const getCookie = (name) =>
  document.cookie.split('; ').find((c) => c.startsWith(`${name}=`))
    ?.substring(name.length + 1)

const getCsrfToken = () =>
  decodeURIComponent(getCookie('XSRF-TOKEN') ?? '') ||
  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

const getDownloadFilename = (response) => {
  const d = response.headers.get('content-disposition') ?? ''
  return d.match(/filename="?([^"]+)"?/)?.[1] ?? 'stamped.zip'
}

const startDownload = (blob, filename) => {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

const handleUpload = async () => {
  if (!file.value || uploading.value) {
    errorMessage.value = 'Please choose a PDF file first.'
    return
  }

  clearMessages()
  uploading.value = true

  const formData = new FormData()
  formData.append('file', file.value)

  // ✅ PRESET ADDED HERE
  if (selectedPresetId.value) {
    formData.append('preset_id', selectedPresetId.value)
  }

  try {
    const res = await fetch('/upload', {
      method: 'POST',
      body: formData,
      headers: {
        Accept: 'application/zip',
        'X-XSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    if (!res.ok) throw new Error('Upload failed')

    const blob = await res.blob()
    startDownload(blob, getDownloadFilename(res))
    successMessage.value = 'Download started.'
  } catch (e) {
    errorMessage.value = e.message
  } finally {
    uploading.value = false
    resetDropState()
  }
}
</script>

<template>
  <Head title="QMS Manual Stamping" />

  <div class="min-h-screen bg-stone-100 flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white border border-stone-200 rounded-xl shadow-sm">

      <!-- Card header -->
      <div class="px-6 pt-6 pb-4 flex items-start justify-between">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-emerald-600 mb-0.5">Manual Stamping</p>
          <h1 class="text-xl font-bold text-stone-950">QMS Document Stamper</h1>
        </div>
        <a href="/manual-stamping/presets"
          class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-stone-700 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors mt-1">
          Manage Presets
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        </a>
      </div>

      <div class="border-t border-stone-100" />

      <!-- Body -->
      <div class="px-6 py-5 space-y-5">

        <!-- Preset selector -->
        <div>
          <label class="block text-xs font-medium text-stone-600 mb-1.5">Stamp Preset</label>
          <select v-model="selectedPresetId"
            class="w-full border border-stone-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-shadow">
            <option value="">Default Layout</option>
            <option v-for="p in props.presets" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
          </select>
        </div>

        <input ref="fileInput" type="file" accept="application/pdf" class="hidden" @change="handleFileChange">

        <!-- Drag zone -->
        <div
          class="border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors"
          :class="isDragging
            ? 'border-emerald-500 bg-emerald-50'
            : 'border-stone-300 hover:border-stone-400 hover:bg-stone-50'"
          @click="openFilePicker"
          @drop.prevent="handleDrop"
          @dragover.prevent="handleDragOver"
          @dragenter.prevent="handleDragEnter"
          @dragleave="handleDragLeave">

          <svg class="w-10 h-10 mx-auto mb-3 transition-colors" :class="isDragging ? 'text-emerald-400' : 'text-stone-300'"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>

          <template v-if="hasFile">
            <p class="text-sm font-semibold text-stone-800 truncate max-w-xs mx-auto">{{ file.name }}</p>
            <p class="text-xs text-stone-400 mt-1">{{ formatFileSize(file.size) }} · PDF</p>
            <button class="mt-3 text-xs text-stone-400 hover:text-red-500 transition-colors" @click.stop="setSelectedFile(null)">
              Remove file
            </button>
          </template>

          <template v-else>
            <p class="text-sm font-medium text-stone-600">Drop PDF here or click to browse</p>
            <p class="text-xs text-stone-400 mt-1">PDF only · max 20 MB</p>
          </template>
        </div>

        <!-- Messages -->
        <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
        <p v-if="successMessage" class="text-sm text-emerald-600">{{ successMessage }}</p>

        <!-- Generate button -->
        <button
          class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-lg text-sm font-semibold transition-colors disabled:bg-stone-300 disabled:cursor-not-allowed"
          :disabled="uploading"
          @click="handleUpload">
          {{ uploading ? 'Processing…' : 'Generate ZIP' }}
        </button>

      </div>
    </div>
  </div>
</template>