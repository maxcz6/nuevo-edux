import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { isTokenExpired } from '@/utils/tokenSecurity'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  timeout: 15000,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json'
  },
  withCredentials: false
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('modux_token')
  if (token) {
    if (isTokenExpired(token)) {
      const authStore = useAuthStore()
      authStore.logout()
      window.location.href = '/login?reason=expired'
      return Promise.reject(new Error('Sesión expirada.'))
    }
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const isLoginRequest = error.config?.url?.includes('/auth/login')
      if (!isLoginRequest) {
        const authStore = useAuthStore()
        authStore.logout()
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  }
)

export default http
