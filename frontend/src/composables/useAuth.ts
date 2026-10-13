import { readonly, ref } from 'vue'
import { api, ApiError, prepareCsrf } from '../lib/api'

interface User { id: number; name: string; email: string }
const user = ref<User | null>(null)
const status = ref<'loading' | 'authenticated' | 'guest' | 'error'>('loading')
const error = ref('')
let pending: Promise<void> | null = null

async function refreshSession(): Promise<void> {
  if (pending) return pending
  status.value = 'loading'
  error.value = ''
  pending = (async () => {
    try {
      const response = await api.get<{ data: User }>('/user')
      user.value = response.data.data
      status.value = 'authenticated'
    } catch (cause) {
      user.value = null
      if (cause instanceof ApiError && cause.status === 401) {
        status.value = 'guest'
      } else {
        status.value = 'error'
        error.value = cause instanceof Error ? cause.message : 'Não foi possível consultar sua sessão.'
      }
    } finally {
      pending = null
    }
  })()
  return pending
}

async function login(email: string, password: string): Promise<void> {
  if (pending) await pending
  await prepareCsrf()
  const response = await api.post<{ data: User }>('/login', { email, password })
  user.value = response.data.data
  status.value = 'authenticated'
  error.value = ''
}

async function logout(): Promise<void> {
  if (pending) await pending
  try {
    await prepareCsrf()
    await api.post('/logout')
  } catch (cause) {
    if (!(cause instanceof ApiError && cause.status === 401)) throw cause
  }
  user.value = null
  status.value = 'guest'
  error.value = ''
}

// Uma sessão compartilhada pelo cabeçalho e pelas views, sem persistir credenciais.
export function useAuth() {
  return { user: readonly(user), status: readonly(status), error: readonly(error), refreshSession, login, logout }
}
