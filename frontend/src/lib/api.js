import axios from 'axios'
import { useAuthStore } from '../stores/auth'

export function api() {
  const auth = useAuthStore()
  const client = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL,
    timeout: 15000
  })

  client.interceptors.request.use((config) => {
    if (auth.token) config.headers.Authorization = `Bearer ${auth.token}`
    return config
  })

  return client
}
