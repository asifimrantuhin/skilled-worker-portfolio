import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

let isRefreshing = false
let refreshPromise = null

const setAuthHeader = (token) => {
  if (token) {
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`
  } else {
    delete api.defaults.headers.common['Authorization']
  }
}

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => Promise.reject(error)
)

// Response interceptor with refresh flow
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config
    const status = error.response?.status

    if (originalRequest?.url?.includes('/refresh')) {
      return Promise.reject(error)
    }

    if (status === 401 && !originalRequest?._retry) {
      const refreshToken = localStorage.getItem('refresh_token')
      if (refreshToken) {
        if (!isRefreshing) {
          isRefreshing = true
          refreshPromise = api
            .post('/refresh', { refresh_token: refreshToken })
            .then((res) => {
              const { token, refresh_token } = res.data
              localStorage.setItem('token', token)
              localStorage.setItem('refresh_token', refresh_token)
              setAuthHeader(token)
              return token
            })
            .catch((refreshError) => {
              localStorage.removeItem('token')
              localStorage.removeItem('refresh_token')
              window.location.href = '/login'
              return Promise.reject(refreshError)
            })
            .finally(() => {
              isRefreshing = false
            })
        }

        try {
          const newToken = await refreshPromise
          originalRequest._retry = true
          originalRequest.headers.Authorization = `Bearer ${newToken}`
          return api(originalRequest)
        } catch (refreshError) {
          return Promise.reject(refreshError)
        }
      }
    }

    if (status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('refresh_token')
      window.location.href = '/login'
    }

    return Promise.reject(error)
  }
)

export const persistTokens = (token, refreshToken) => {
  localStorage.setItem('token', token)
  localStorage.setItem('refresh_token', refreshToken)
  setAuthHeader(token)
}

export const clearTokens = () => {
  localStorage.removeItem('token')
  localStorage.removeItem('refresh_token')
  setAuthHeader(null)
}

export default api

