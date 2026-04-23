<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

const props = defineProps({
  presets: {
    type: Array,
    default: () => [],
  },
})

const file = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
const isDragging = ref(false)
const dragDepth = ref(0)
const errorMessage = ref('')
const successMessage = ref('')
const selectedPresetId = ref('')

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

  <div class="min-h-screen bg-stone-100 flex items-center justify-center">
    <div class="w-full max-w-xl bg-white p-6 rounded-lg shadow">

      <h1 class="text-2xl font-bold mb-4">QMS Manual Stamping</h1>
<div class="mt-4 flex justify-end">
  <a
    href="/manual-stamping/presets"
    class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-400 hover:bg-stone-50"
  >
    Manage Presets
  </a>
</div>
      <!-- 🔥 PRESET DROPDOWN -->
      <div class="mb-4">
        <label class="text-sm font-medium">Preset</label>
        <select v-model="selectedPresetId" class="w-full border p-2 rounded">
          <option value="">Default Layout</option>
          <option
            v-for="p in props.presets"
            :key="p.id"
            :value="String(p.id)"
          >
            {{ p.name }}
          </option>
        </select>
      </div>

      <input
        ref="fileInput"
        type="file"
        accept="application/pdf"
        class="hidden"
        @change="handleFileChange"
      >

      <div
        class="border-dashed border-2 p-6 text-center cursor-pointer"
        @click="openFilePicker"
        @drop.prevent="handleDrop"
        @dragover.prevent="handleDragOver"
      >
        Drag PDF here or click to upload
      </div>

      <div v-if="file" class="mt-3 text-sm">
        {{ file.name }} ({{ formatFileSize(file.size) }})
      </div>

      <button
        @click="handleUpload"
        :disabled="uploading"
        class="mt-4 w-full bg-emerald-600 text-white py-2 rounded"
      >
        {{ uploading ? 'Processing...' : 'Generate ZIP' }}
      </button>

      <p v-if="errorMessage" class="text-red-500 mt-2">
        {{ errorMessage }}
      </p>

      <p v-if="successMessage" class="text-green-600 mt-2">
        {{ successMessage }}
      </p>

    </div>
  </div>
</template>