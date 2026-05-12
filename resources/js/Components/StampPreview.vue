<script setup>
/**
 * StampPreview.vue
 *
 * Changes vs original:
 * ─── 1. DRAG FIX ────────────────────────────────────────────────────────────
 *   startStampDrag / startEsignDrag now EMIT events instead of mutating props.
 *   New events:  @stamp-drag { index, x, y }  |  @esign-drag { x, y }
 *
 * ─── 2. BACKGROUND IMAGE ────────────────────────────────────────────────────
 *   New optional prop: backgroundImage (string URL).
 *   Pass the path to your rasterised template PNG (see instructions below).
 *
 *   HOW TO GENERATE THE IMAGES:
 *   Run this once on your server / locally:
 *     pdftoppm -r 150 -png storage/app/Template_sample.pdf public/images/template_page
 *   This produces:
 *     public/images/template_page-1.png   ← page 1
 *     public/images/template_page-2.png   ← page 2
 *   (Both PNG files are included in this session's outputs for download.)
 *
 *   USAGE in Presets.vue:
 *     
 *   Leave background-image blank (or omit it) for the plain white canvas.
 */

const props = defineProps({
  stamps: {
    type: Array,
    default: () => [],
  },
  esigns: {
    type: Array,
    default: () => [],
  },
  esignActiveIndex: {
    type: Number,
    default: -1,
  },
  activeIndex: {
    type: Number,
    default: -1,
  },
  /**
   * Optional URL to a rasterised page image shown as the canvas background.
   * e.g. "/images/template_page-1.png"
   */
  backgroundImage: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:activeIndex', 'update:esignActiveIndex', 'stamp-drag', 'esign-drag'])

// ── A4 preview constants ──────────────────────────────────────────────────────
const PAGE_W_MM  = 210
const PAGE_H_MM  = 297
const PREVIEW_H  = 320
const PREVIEW_W  = Math.round(PREVIEW_H * (PAGE_W_MM / PAGE_H_MM))
const SCALE      = PREVIEW_H / PAGE_H_MM

function mmToPx(mm) { return (parseFloat(mm) || 0) * SCALE }
function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)) }

const COLOR = {
  red:   { border: '#dc2626', bg: 'rgba(220,38,38,0.08)',  text: '#dc2626' },
  black: { border: '#1f2937', bg: 'rgba(31,41,55,0.06)',   text: '#1f2937' },
}

// ── Drag: stamp ───────────────────────────────────────────────────────────────
function startStampDrag(event, stamp, idx) {
  event.preventDefault()
  event.stopPropagation()
  emit('update:activeIndex', idx)

  const startX = event.clientX
  const startY = event.clientY
  const origX  = parseFloat(stamp.x) || 0
  const origY  = parseFloat(stamp.y) || 0

  function onMove(e) {
    const w = parseFloat(stamp.width)  || 0
    const h = parseFloat(stamp.height) || 0
    emit('stamp-drag', {
      index: idx,
      x: +clamp(origX + (e.clientX - startX) / SCALE, 0, PAGE_W_MM - w).toFixed(2),
      y: +clamp(origY + (e.clientY - startY) / SCALE, 0, PAGE_H_MM - h).toFixed(2),
    })
  }
  function onUp() {
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup',   onUp)
  }
  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup',   onUp)
}

// ── Drag: esign ───────────────────────────────────────────────────────────────
function startEsignDrag(event, esign, idx) {
  event.preventDefault()
  event.stopPropagation()
  emit('update:esignActiveIndex', idx)

  const startX = event.clientX
  const startY = event.clientY
  const origX  = parseFloat(esign.x) || 0
  const origY  = parseFloat(esign.y) || 0

  function onMove(e) {
    const w = parseFloat(esign.width)  || 0
    const h = parseFloat(esign.height) || 0
    emit('esign-drag', {
      index: idx,
      x: +clamp(origX + (e.clientX - startX) / SCALE, 0, PAGE_W_MM - w).toFixed(2),
      y: +clamp(origY + (e.clientY - startY) / SCALE, 0, PAGE_H_MM - h).toFixed(2),
    })
  }
  function onUp() {
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup',   onUp)
  }
  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup',   onUp)
}
</script>

<template>
  
  <div class="flex flex-col items-center gap-2">
    
    <!-- A4 page canvas -->
    <div
      class="relative overflow-hidden border border-stone-300 shadow-sm"
      :style="{
        width:  PREVIEW_W + 'px',
        height: PREVIEW_H + 'px',
        backgroundColor:    '#ffffff',
        backgroundImage:    backgroundImage ? `url(${backgroundImage})` : 'none',
        backgroundSize:     '100% 100%',
        backgroundRepeat:   'no-repeat',
        backgroundPosition: 'top left',
      }"
    >
      <!-- Guide lines — only shown when there's no background image -->
      <svg
        v-if="!backgroundImage"
        class="pointer-events-none absolute inset-0"
        :width="PREVIEW_W"
        :height="PREVIEW_H"
        xmlns="http://www.w3.org/2000/svg"
      >
        <line x1="0" :y1="PREVIEW_H/2" :x2="PREVIEW_W" :y2="PREVIEW_H/2"
          stroke="#e7e5e4" stroke-width="0.5" stroke-dasharray="4 4"/>
        <line :x1="PREVIEW_W/2" y1="0" :x2="PREVIEW_W/2" :y2="PREVIEW_H"
          stroke="#e7e5e4" stroke-width="0.5" stroke-dasharray="4 4"/>
      </svg>

      <!-- Stamp boxes -->
      <div
        v-for="(stamp, i) in stamps"
        :key="i"
        class="absolute flex select-none items-center justify-center"
        :style="{
          left:            mmToPx(stamp.x) + 'px',
          top:             mmToPx(stamp.y) + 'px',
          width:           mmToPx(stamp.width) + 'px',
          height:          mmToPx(stamp.height) + 'px',
          border:          `2px solid ${(COLOR[stamp.type] ?? COLOR.red).border}`,
          backgroundColor: (COLOR[stamp.type] ?? COLOR.red).bg,
          cursor:          'grab',
          outline:         activeIndex === i ? '2px solid #10b981' : 'none',
          outlineOffset:   '2px',
          zIndex:          activeIndex === i ? 10 : 1,
        }"
        @mousedown="startStampDrag($event, stamp, i)"
      >
        <span
          class="pointer-events-none text-center font-semibold leading-tight"
          :style="{
            fontSize: '6px',
            color: (COLOR[stamp.type] ?? COLOR.red).text,
          }"
        >
          {{ stamp.label }}<template v-if="stamp.sub_label"><br>{{ stamp.sub_label }}</template>
        </span>
      </div>

      <!-- E-Sign boxes -->
      <div
        v-for="(esign, i) in esigns"
        :key="'e' + i"
        class="absolute flex select-none items-center justify-center"
        :style="{
          left:            mmToPx(esign.x) + 'px',
          top:             mmToPx(esign.y) + 'px',
          width:           mmToPx(esign.width) + 'px',
          height:          mmToPx(esign.height) + 'px',
          border:          '2px solid #1f2937',
          backgroundColor: 'rgba(31,41,55,0.06)',
          outline:         esignActiveIndex === i ? '2px solid #10b981' : 'none',
          outlineOffset:   '2px',
          cursor:          'grab',
          zIndex:          esignActiveIndex === i ? 10 : 5,
        }"
        @mousedown="startEsignDrag($event, esign, i)"
      >
        <span class="pointer-events-none text-[6px] font-medium italic text-stone-700">
          E-SIGN
        </span>
      </div>
    </div>

    <!-- Coordinate readout -->
    <div class="w-full space-y-0.5 text-xs text-stone-500">
      <div
        v-for="(stamp, i) in stamps"
        :key="i"
        :class="activeIndex === i ? 'font-semibold text-stone-700' : ''"
      >
        S{{ i + 1 }} ({{ stamp.type }}): x={{ Number(stamp.x).toFixed(1) }},
        y={{ Number(stamp.y).toFixed(1) }}
        — {{ Number(stamp.width).toFixed(1) }}×{{ Number(stamp.height).toFixed(1) }} mm
      </div>
      <div
        v-for="(esign, i) in esigns"
        :key="'ec' + i"
        :class="esignActiveIndex === i ? 'font-semibold text-stone-700' : ''"
      >
        E{{ i + 1 }}: x={{ Number(esign.x || 0).toFixed(1) }},
        y={{ Number(esign.y || 0).toFixed(1) }}
        — {{ Number(esign.width || 0).toFixed(1) }}×{{ Number(esign.height || 0).toFixed(1) }} mm
      </div>
    </div>

    <p class="text-xs text-stone-400">A4 preview — drag to reposition</p>
  </div>
</template>