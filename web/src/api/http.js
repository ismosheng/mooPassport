import axios from 'axios'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '',
  timeout: 15000,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const data = error.response?.data
    error.userMessage = data?.message || data?.error_description || '请求失败，请稍后重试。'
    return Promise.reject(error)
  },
)

export default http
