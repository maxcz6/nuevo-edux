import http from './http'
import type { ApiResponse, Usuario } from '@/types'

export interface LoginPayload {
  email: string
  password: string
}

export interface LoginResponse {
  user: Usuario
  token: string
  token_type: string
}

export const authApi = {
  login: (payload: LoginPayload) =>
    http.post<ApiResponse<LoginResponse>>('/auth/login', payload),
  logout: () => http.post('/auth/logout'),
  me: () => http.get<ApiResponse<Usuario>>('/auth/me'),
  forgotPassword: (email: string) => http.post('/auth/forgot-password', { email }),
  resetPassword: (payload: { email: string; token: string; password: string; password_confirmation: string; institucion_id?: number }) =>
    http.post('/auth/reset-password', payload)
}
