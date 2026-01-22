import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Response interceptor for error handling
api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API Error:', error.response?.data || error.message)
    return Promise.reject(error)
  }
)

export const discogsApi = {
  // Get saved analyses with filters and pagination
  async getSaved(params = {}) {
    const response = await api.get('/discogs/saved', { params })
    return response.data
  },

  // Get available filters
  async getFilters() {
    const response = await api.get('/discogs/filters')
    return response.data
  },

  // Get statistics
  async getStats() {
    const response = await api.get('/discogs/saved/stats')
    return response.data
  },

  // Search releases
  async search(query, params = {}) {
    const response = await api.get('/discogs/search-smart', {
      params: { q: query, ...params }
    })
    return response.data
  },

  // Get release analysis
  async getAnalysis(id) {
    const response = await api.get(`/discogs/releases/${id}/analysis`)
    return response.data
  },

  // Save release to analysis
  async saveRelease(id, data = {}) {
    const response = await api.post(`/discogs/releases/${id}/save`, data)
    return response.data
  },

  // Remove from saved
  async removeSaved(id) {
    const response = await api.delete(`/discogs/saved/${id}`)
    return response.data
  },

  // Get trending vinyls
  async getTrending() {
    const response = await api.get('/vinyl-scorer/trending')
    return response.data
  },

  // Quick score
  async quickScore(id) {
    const response = await api.get(`/vinyl-scorer/quick/${id}`)
    return response.data
  },
}

export default api
