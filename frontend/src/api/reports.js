import axios from 'axios'

export async function apiDailySummary(params) {
    const r = await axios.get('/api/reports/daily-summary', { params })
    return r.data
}