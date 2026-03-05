<template>
  <div class="mx-auto max-w-7xl space-y-2">
    <!-- HEADER -->
    <div>
      <h1 class="text-2xl font-semibold text-gray-900">Daily Charts</h1>
    </div>

    <!-- FILTER CARD -->
    <div class="rounded-xl border bg-white px-4 py-3 shadow-sm">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block whitespace-nowrap text-sm font-medium text-gray-700">From Date</label>
          <input type="date" v-model="from" class="cd-input" />
        </div>

        <div>
          <label class="block whitespace-nowrap text-sm font-medium text-gray-700">To Date</label>
          <input type="date" v-model="to" class="cd-input" />
        </div>

        <div>
          <label class="block whitespace-nowrap text-sm font-medium text-gray-700">Group</label>
          <select v-model="group" class="cd-input cd-select">
            <option value="driver">Driver</option>
            <option value="carrier">Carrier</option>
            <option value="client">Client</option>
            <option value="job">Job Site</option>
          </select>
        </div>

        <div>
          <label class="block whitespace-nowrap text-sm font-medium text-gray-700">Metric</label>
          <select v-model="metric" class="cd-input cd-select">
            <option value="margin_total_sum">Margin Total</option>
            <option value="tons_sum">Tons</option>
          </select>
        </div>

        <div>
          <label class="block whitespace-nowrap text-sm font-medium text-gray-700">Chart Type</label>
          <select v-model="chartMode" class="cd-input cd-select">
            <option value="line">Line</option>
            <option value="stacked">Stacked Bars</option>
          </select>
        </div>

        <div v-if="subLabel" class="whitespace-nowrap pb-1 text-sm text-gray-500">
          Showing: <span class="font-medium text-gray-700">{{ subLabel }}</span>
        </div>
      </div>
    </div>

    <!-- CHART CARD -->
    <div class="rounded-xl border bg-white p-2 shadow-sm">
      <div class="h-[360px]">
        <canvas ref="chartRef"></canvas>
      </div>
    </div>

    <!-- DATA CARD -->
    <div class="overflow-hidden rounded-xl border bg-white shadow-sm">
      <div class="overflow-auto">
        <table class="w-full border-collapse text-xs">
          <thead class="border-b bg-gray-100">
          <tr>
            <th class="px-2 py-1 text-left font-medium">Date</th>

            <th
                v-for="s in series"
                :key="s"
                class="px-2 py-1 text-right font-medium whitespace-nowrap"
            >
              {{ s }}
            </th>

            <th class="px-2 py-1 text-right font-semibold whitespace-nowrap">
              Grand Total
            </th>
          </tr>
          </thead>

          <tbody>
          <tr
              v-for="row in paginatedData"
              :key="row.date"
              class="border-b hover:bg-gray-50"
          >
            <td class="px-2 py-1 whitespace-nowrap">{{ row.date }}</td>

            <td
                v-for="s in series"
                :key="s"
                class="px-2 py-1 text-right whitespace-nowrap"
            >
              {{ formatValue(row[s] || 0) }}
            </td>

            <td class="px-2 py-1 text-right font-semibold whitespace-nowrap">
              {{ formatValue(row.total || 0) }}
            </td>
          </tr>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <div
          v-if="totalPages > 1"
          class="flex items-center justify-between bg-gray-50 px-3 py-2"
      >
        <button
            @click="prevPage"
            :disabled="currentPage === 1"
            class="rounded border bg-white px-3 py-1 disabled:opacity-50"
        >
          Previous
        </button>

        <span class="text-xs text-gray-600">
          Page {{ currentPage }} of {{ totalPages }}
        </span>

        <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="rounded border bg-white px-3 py-1 disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import Chart from 'chart.js/auto'
import { apiDailySummary } from '../../api/reports'

const from = ref('')
const to = ref('')

const group = ref('driver')
const metric = ref('margin_total_sum')
const chartMode = ref('line') // line|stacked

const subLabel = ref('')

const series = ref([])
const tableData = ref([])

const chartRef = ref(null)
let chartInstance = null

// pagination
const currentPage = ref(1)
const perPage = 15
const totalPages = computed(() => Math.ceil(tableData.value.length / perPage))
const paginatedData = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return tableData.value.slice(start, start + perPage)
})

function nextPage() {
  if (currentPage.value < totalPages.value) currentPage.value++
}
function prevPage() {
  if (currentPage.value > 1) currentPage.value--
}

const isMoney = computed(() => metric.value === 'margin_total_sum')

function formatValue(v) {
  const n = Number(v || 0)
  if (isMoney.value) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)
  }
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(n)
}

async function loadReport() {
  if (!from.value || !to.value) return

  const data = await apiDailySummary({
    from: from.value,
    to: to.value,
    group: group.value,
    metric: metric.value,
  })

  if (!data?.ok) return

  const dates = data.dates || []
  const sList = data.series || []
  const matrix = data.matrix || {}

  series.value = sList
  subLabel.value = `${data.group_label} • ${data.metric_label}`

  tableData.value = dates.map(d => {
    const row = { date: d }
    let total = 0

    for (const s of sList) {
      const val = Number(matrix?.[d]?.[s] || 0)
      row[s] = val
      total += val
    }

    row.total = total
    return row
  })

  currentPage.value = 1
  renderChart(dates, sList, matrix)
}

function renderChart(dates, sList, matrix) {
  if (chartInstance) chartInstance.destroy()

  const isStacked = chartMode.value === 'stacked'

  const colors = [
    { bg: 'rgba(30, 64, 175, 0.85)', border: '#1e3a8a' },
    { bg: 'rgba(153, 27, 27, 0.85)', border: '#7f1d1d' },
    { bg: 'rgba(22, 101, 52, 0.85)', border: '#14532d' },
    { bg: 'rgba(146, 64, 14, 0.85)', border: '#78350f' },
    { bg: 'rgba(107, 33, 168, 0.85)', border: '#581c87' },
    { bg: 'rgba(3, 105, 161, 0.85)', border: '#075985' },
    { bg: 'rgba(17, 24, 39, 0.85)', border: '#111827' },
  ]

  const datasets = sList.map((name, i) => {
    const c = colors[i % colors.length]

    const ds = {
      label: name,
      data: dates.map(d => Number(matrix?.[d]?.[name] || 0)),
      backgroundColor: c.bg,
      borderColor: c.border,
      borderWidth: 2,
      tension: 0.3,
      fill: false,
    }

    // ✅ ONLY set stack when chart is stacked bars
    if (isStacked) ds.stack = 'stack1'

    return ds
  })

  chartInstance = new Chart(chartRef.value, {
    type: isStacked ? 'bar' : 'line',
    data: { labels: dates, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { labels: { color: '#374151', font: { weight: '500' } } },
        tooltip: {
          callbacks: {
            label: (ctx) => `${ctx.dataset.label}: ${formatValue(ctx.raw)}`
          }
        }
      },
      scales: {
        x: { stacked: isStacked },
        y: { stacked: isStacked, beginAtZero: true },
      },
    },
  })
}

// auto load (no Generate button)
let t = null
function scheduleLoad() {
  if (t) clearTimeout(t)
  t = setTimeout(() => loadReport(), 250)
}

watch([from, to, group, metric], scheduleLoad)

watch(chartMode, () => {
  if (!tableData.value.length) return

  const dates = tableData.value.map(r => r.date)
  const sList = series.value

  const matrix = {}
  for (const row of tableData.value) {
    matrix[row.date] = {}
    for (const s of sList) matrix[row.date][s] = row[s] || 0
  }

  renderChart(dates, sList, matrix)
})
</script>