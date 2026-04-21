<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

const file = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
const isDragging = ref(false)
const dragDepth = ref(0)
const errorMessage = ref('')
const successMessage = ref('')

const hasFile = computed(() => file.value !== null)

const formatFileSize = (bytes) => {
  if (!Number.isFinite(bytes) || bytes <= 0) {
    return '0 KB'
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`
}

const isPdfFile = (selectedFile) => {
  if (!selectedFile) {
    return false
  }

  return selectedFile.type === 'application/pdf' || /\.pdf$/i.test(selectedFile.name)
}

const clearMessages = () => {
  errorMessage.value = ''
  successMessage.value = ''
}

const setSelectedFile = (selectedFile) => {
  clearMessages()

  if (!selectedFile) {
    file.value = null
    return
  }

  if (!isPdfFile(selectedFile)) {
    file.value = null
    errorMessage.value = 'Please choose a PDF file.'
    return
  }

  file.value = selectedFile
}

const openFilePicker = () => {
  if (uploading.value) {
    return
  }

  fileInput.value?.click()
}

const handleFileChange = (event) => {
  setSelectedFile(event.target.files?.[0] ?? null)
  event.target.value = ''
}

const handleDragEnter = () => {
  if (uploading.value) {
    return
  }

  dragDepth.value += 1
  isDragging.value = true
}

const handleDragOver = (event) => {
  if (uploading.value) {
    return
  }

  event.dataTransfer.dropEffect = 'copy'
  isDragging.value = true
}

const handleDragLeave = () => {
  if (uploading.value) {
    return
  }

  dragDepth.value = Math.max(0, dragDepth.value - 1)

  if (dragDepth.value === 0) {
    isDragging.value = false
  }
}

const handleDrop = (event) => {
  if (uploading.value) {
    return
  }

  dragDepth.value = 0
  isDragging.value = false
  setSelectedFile(event.dataTransfer?.files?.[0] ?? null)
}

const resetDropState = () => {
  dragDepth.value = 0
  isDragging.value = false
}

const getCookie = (name) => {
  const row = document.cookie
    .split('; ')
    .find((cookieEntry) => cookieEntry.startsWith(`${name}=`))

  return row ? row.substring(name.length + 1) : null
}

const getCsrfToken = () => {
  const xsrfToken = getCookie('XSRF-TOKEN')

  if (xsrfToken) {
    return decodeURIComponent(xsrfToken)
  }

  const metaToken = document
    .querySelector('meta[name="csrf-token"]')
    ?.getAttribute('content')

  return metaToken ?? null
}

const getDownloadFilename = (response) => {
  const disposition = response.headers.get('content-disposition') ?? ''
  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)

  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1])
  }

  const plainMatch = disposition.match(/filename="?([^"]+)"?/i)

  return plainMatch?.[1] ?? 'stamped_copies.zip'
}

const getUploadErrorMessage = async (response) => {
  if (response.status === 413) {
    return 'The selected file is too large.'
  }

  if (response.status === 419) {
    return 'Your session expired. Refresh the page and try again.'
  }

  const contentType = response.headers.get('content-type') ?? ''

  if (contentType.includes('application/json')) {
    const payload = await response.json().catch(() => null)
    const firstError = Object.values(payload?.errors ?? {}).flat()[0]

    return firstError ?? payload?.message ?? 'Upload failed.'
  }

  if (response.status === 422) {
    return 'The selected file is invalid.'
  }

  const fallbackText = await response.text().catch(() => '')

  if (fallbackText) {
    return fallbackText
  }

  return `Upload failed with HTTP ${response.status}.`
}

const startDownload = (blob, filename) => {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  window.setTimeout(() => {
    window.URL.revokeObjectURL(url)
  }, 0)
}

const handleUpload = async () => {
  if (!file.value || uploading.value) {
    if (!file.value) {
      errorMessage.value = 'Please choose a PDF file first.'
    }

    return
  }

  clearMessages()
  uploading.value = true

  const formData = new FormData()
  formData.append('file', file.value)

  const headers = {
    Accept: 'application/zip, application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }

  const csrfToken = getCsrfToken()

  if (csrfToken) {
    headers['X-XSRF-TOKEN'] = csrfToken
  }

  try {
    const response = await fetch('/upload', {
      method: 'POST',
      body: formData,
      headers,
      credentials: 'same-origin',
    })

    if (!response.ok) {
      throw new Error(await getUploadErrorMessage(response))
    }

    const blob = await response.blob()
    startDownload(blob, getDownloadFilename(response))
    successMessage.value = 'Your stamped ZIP download has started.'
  } catch (error) {
    errorMessage.value = error instanceof Error
      ? error.message
      : 'Upload failed. Please try again.'
  } finally {
    uploading.value = false
    resetDropState()
  }
}
</script>

<template>
  <Head title="QMS Manual Stamping" />

  <div class="min-h-screen bg-stone-100 px-4 py-8 text-stone-900 sm:px-6 lg:px-8">
    <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-xl items-center justify-center">
      <section class="w-full rounded-lg border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="space-y-2">
          <p class="text-sm font-medium uppercase tracking-[0.18em] text-emerald-700">
            PDF Stamping
          </p>

          <h1 class="text-3xl font-semibold tracking-tight text-stone-950">
            QMS Manual Stamping
          </h1>

          <p class="text-sm leading-6 text-stone-600">
            Drop a PDF into the upload area or browse from your device. The stamped outputs
            will be generated and downloaded as a ZIP file.
          </p>
        </div>

        <form
          class="mt-8 space-y-5"
          @submit.prevent="handleUpload"
        >
          <input
            ref="fileInput"
            type="file"
            accept="application/pdf"
            class="hidden"
            @change="handleFileChange"
          >

          <div
            role="button"
            tabindex="0"
            :class="[
              'group relative flex min-h-56 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 text-center transition',
              isDragging
                ? 'border-emerald-500 bg-emerald-50'
                : 'border-stone-300 bg-stone-50 hover:border-emerald-400 hover:bg-emerald-50/60',
              uploading ? 'cursor-not-allowed opacity-70' : '',
            ]"
            @click="openFilePicker"
            @keydown.enter.prevent="openFilePicker"
            @keydown.space.prevent="openFilePicker"
            @dragenter.prevent="handleDragEnter"
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
          >
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-white text-emerald-600 shadow-sm ring-1 ring-stone-200">
              <svg
                class="h-7 w-7"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <path d="M12 16V4" />
                <path d="m7 9 5-5 5 5" />
                <path d="M4 16.5v1A2.5 2.5 0 0 0 6.5 20h11a2.5 2.5 0 0 0 2.5-2.5v-1" />
              </svg>
            </div>

            <div class="mt-5 space-y-2">
              <p class="text-base font-medium text-stone-900">
                {{ isDragging ? 'Drop your PDF here' : 'Drag and drop your PDF here' }}
              </p>

              <p class="text-sm text-stone-500">
                or click to browse
              </p>
            </div>

            <p class="mt-4 text-xs font-medium uppercase tracking-[0.16em] text-stone-400">
              PDF only
            </p>
          </div>

          <div
            v-if="hasFile"
            class="flex items-center justify-between gap-4 rounded-lg border border-stone-200 bg-stone-50 px-4 py-3"
          >
            <div class="min-w-0">
              <p class="text-xs font-medium uppercase tracking-[0.16em] text-stone-500">
                Selected File
              </p>

              <p class="truncate text-sm font-medium text-stone-900">
                {{ file.name }}
              </p>

              <p class="text-xs text-stone-500">
                {{ formatFileSize(file.size) }}
              </p>
            </div>

            <button
              type="button"
              class="shrink-0 rounded-md border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-400 hover:bg-white disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="uploading"
              @click="openFilePicker"
            >
              Replace
            </button>
          </div>

          <div
            v-if="successMessage"
            class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
          >
            {{ successMessage }}
          </div>

          <div
            v-if="errorMessage"
            class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
          >
            {{ errorMessage }}
          </div>

          <button
            type="submit"
            :disabled="uploading || !hasFile"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-stone-300"
          >
            <svg
              v-if="uploading"
              class="h-4 w-4 animate-spin"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              />
              <path
                class="opacity-90"
                fill="currentColor"
                d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"
              />
            </svg>

            <svg
              v-else
              class="h-4 w-4"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              aria-hidden="true"
            >
              <path d="M12 16V4" />
              <path d="m7 9 5-5 5 5" />
              <path d="M4 16.5v1A2.5 2.5 0 0 0 6.5 20h11a2.5 2.5 0 0 0 2.5-2.5v-1" />
            </svg>

            <span>
              {{ uploading ? 'Processing PDF...' : 'Generate Stamped ZIP' }}
            </span>
          </button>
        </form>
      </section>
    </div>
  </div>
</template>
