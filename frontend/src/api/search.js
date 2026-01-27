import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Generate or get session ID
const getSessionId = () => {
  let sessionId = sessionStorage.getItem('search_session_id')
  if (!sessionId) {
    sessionId = 'sess_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36)
    sessionStorage.setItem('search_session_id', sessionId)
  }
  return sessionId
}

// Add session ID to all requests
api.interceptors.request.use((config) => {
  config.headers['X-Session-ID'] = getSessionId()
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('Search API Error:', error.response?.data || error.message)
    return Promise.reject(error)
  }
)

export const searchApi = {
  /**
   * Get autocomplete suggestions
   * @param {string} query - Search query (min 1 character)
   */
  async suggest(query) {
    if (!query || query.length < 1) {
      return { suggestions: {}, recent: [] }
    }
    const response = await api.get('/search/suggest', { params: { q: query } })
    return response.data
  },

  /**
   * Perform full search with filters
   * @param {string} query - Search query
   * @param {object} filters - Optional filters (genre, year_from, year_to, price_min, price_max, sort)
   */
  async search(query, filters = {}) {
    const response = await api.get('/search', {
      params: { q: query, ...filters }
    })
    return response.data
  },

  /**
   * Get search history for current session
   */
  async getHistory() {
    const response = await api.get('/search/history')
    return response.data
  },

  /**
   * Clear search history
   */
  async clearHistory() {
    const response = await api.delete('/search/history')
    return response.data
  },

  /**
   * Record when user selects a search result (improves suggestions)
   * @param {string} query - Original search query
   * @param {string|number} selectedId - ID of selected item
   * @param {string} selectedType - Type: vinyl, artist, genre, label
   * @param {string} selectedTitle - Title/name of selected item
   */
  async recordSelection(query, selectedId, selectedType, selectedTitle) {
    const response = await api.post('/search/select', {
      query,
      selected_id: selectedId,
      selected_type: selectedType,
      selected_title: selectedTitle,
    })
    return response.data
  },

  /**
   * Get search statistics
   */
  async getStats() {
    const response = await api.get('/search/stats')
    return response.data
  },
}

export default searchApi
