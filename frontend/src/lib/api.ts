import axios from 'axios'

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public code: string,
    public details: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  withCredentials: true,
  timeout: 10000,
  headers: { Accept: 'application/json' },
})

api.interceptors.response.use(response => response, (error: unknown) => {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status || 0
    const body = error.response?.data?.error
    return Promise.reject(new ApiError(
      body?.message || (status === 0 ? 'Não foi possível conectar. Tente novamente.' : 'Não foi possível concluir a operação.'),
      status,
      body?.code || 'connection_error',
      body?.details || {},
    ))
  }
  return Promise.reject(new ApiError('Não foi possível concluir a operação.', 0, 'unexpected_error'))
})

let csrfRequest: Promise<void> | null = null

export async function prepareCsrf(): Promise<void> {
  // O middleware web renova este cookie nas respostas. Axios envia o header.
  // Ausencia exige bootstrap; token expirado continua sendo rejeitado pelo servidor.
  if (document.cookie.split(';').some(cookie => cookie.trim().startsWith('XSRF-TOKEN='))) return
  if (!csrfRequest) csrfRequest = api.get('/csrf-cookie').then(() => undefined).finally(() => { csrfRequest = null })
  return csrfRequest
}
