<script setup>
import { ref } from 'vue'
import axios from 'axios'

const file = ref(null)
const uploading = ref(false)
const error = ref('')

const handleFileChange = (event) => {
  file.value = event.target.files?.[0] ?? null
  error.value = ''
}

const getUploadErrorMessage = async (uploadError) => {
  if (!uploadError.response) {
    console.error(uploadError)
    return 'Upload failed. Check the browser console and Laravel log for details.'
  }

  const responseData = uploadError.response.data

  if (responseData instanceof Blob && responseData.type.includes('application/json')) {
    try {
      const payload = JSON.parse(await responseData.text())
      const firstError = Object.values(payload.errors ?? {}).flat()[0]

      return firstError ?? payload.message ?? 'Upload failed.'
    } catch (parseError) {
      console.error(parseError)
    }
  }

  if (uploadError.response.status === 413) {
    return 'The selected file is too large.'
  }

  if (uploadError.response.status === 419) {
    return 'Your session expired. Refresh the page and try again.'
  }

  if (uploadError.response.status === 422) {
    return 'The selected file is invalid.'
  }

  return `Upload failed with HTTP ${uploadError.response.status}.`
}

const getDownloadFilename = (response) => {
  const disposition = response.headers['content-disposition'] ?? ''
  const match = disposition.match(/filename="?([^"]+)"?/)

  return match?.[1] ?? 'stamped_copies.zip'
}

const handleUpload = async () => {
  if (!file.value) {
    error.value = 'Please select a PDF first.'
    return
  }

  const formData = new FormData()
  formData.append('file', file.value)

  uploading.value = true
  error.value = ''

  try {
    const response = await axios.post('/upload', formData, {
      responseType: 'blob',
      headers: {
        Accept: 'application/zip, application/json',
      },
    })

    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')

    link.href = url
    link.download = getDownloadFilename(response)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (uploadError) {
    error.value = await getUploadErrorMessage(uploadError)
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-100">
    <div class="mx-auto max-w-5xl px-4 py-10">
      <div class="rounded-2xl bg-white p-8 shadow">
        <h1 class="mb-2 text-3xl font-bold text-slate-800">
          QMS Manual Stamping
        </h1>

        <p class="mb-6 text-slate-600">
          Upload a manual file here.
        </p>

        <form @submit.prevent="handleUpload" class="space-y-4">
          <input
            type="file"
            accept="application/pdf"
            @change="handleFileChange"
            class="block w-full rounded-lg border border-slate-300 p-3"
          />

          <p v-if="error" class="text-sm font-medium text-red-600">
            {{ error }}
          </p>

          <button
            type="submit"
            :disabled="uploading"
            class="rounded-lg bg-blue-600 px-6 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-400"
          >
            {{ uploading ? 'Uploading...' : 'Upload File' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
