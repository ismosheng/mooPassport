import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getCurrentUser, login, logout } from '../api/auth.js'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)

  async function signIn(credentials) {
    const response = await login(credentials)
    user.value = response.data.data.user
    return user.value
  }

  async function loadCurrentUser() {
    const response = await getCurrentUser()
    user.value = response.data.data.user
    return user.value
  }

  async function signOut() {
    await logout()
    user.value = null
  }

  return { user, signIn, loadCurrentUser, signOut }
})
