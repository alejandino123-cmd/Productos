import axios from 'axios'

const apiBaseUrl = import.meta.env.VITE_API_URL

if (!apiBaseUrl) {
  console.error('La variable de entorno no está definida, verifica tu archivo .env')
}

const api = axios.create({
  baseURL: apiBaseUrl,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

// Todo lo que se guarda en el navegador durante el inicio de sesión
const CLAVES_SESION = [
  'auth_token',
  'user_data',
  'is_superadmin',
  'user_email',
  'debug_code',
  'login_ticket',
]

const limpiarSesion = () => {
  CLAVES_SESION.forEach((clave) => localStorage.removeItem(clave))
}

// El boleto temporal que entrega el login a un usuario normal viaja en este header
const cabeceraTicket = () => ({
  headers: { 'X-Login-Ticket': localStorage.getItem('login_ticket') ?? '' },
})

// Verificación completa (por correo o por QR): se guarda la sesión y se borra el boleto
const guardarSesionNormal = (data) => {
  localStorage.setItem('auth_token', data.token)
  localStorage.setItem('user_data', JSON.stringify(data.user))
  localStorage.setItem('is_superadmin', 'false')
  localStorage.removeItem('login_ticket')
  localStorage.removeItem('user_email')
  localStorage.removeItem('debug_code')
}

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  },
)

// Interceptor para manejar errores de autenticación
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      limpiarSesion()
      // Evita un bucle de recargas si ya estamos en el login
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    return Promise.reject(error)
  },
)

// Servicios para auth
export const useAuthService = () => {
  /**
   * Paso 1 del inicio de sesión.
   * - Superadmin: la API devuelve token y se guarda la sesión.
   * - Usuario normal: la API devuelve requires_2fa y un ticket (sin token). Se guarda el ticket
   *   para el paso 2 (correo o QR) y NO se guarda ninguna sesión todavía.
   */
  const login = async (credentials) => {
    try {
      const { data } = await api.post('/login', credentials)

      if (data.requires_2fa) {
        // Todavía no hay sesión: solo se guarda el boleto temporal para elegir el método
        localStorage.setItem('login_ticket', data.ticket)
        localStorage.setItem('user_email', data.email ?? credentials.email)
        return { success: true, requires2fa: true, data }
      }

      localStorage.setItem('auth_token', data.token)
      localStorage.setItem('user_data', JSON.stringify(data.user))
      localStorage.setItem('is_superadmin', data.is_superadmin ? 'true' : 'false')
      return { success: true, requires2fa: false, data }
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.errors?.email?.[0] ??
          error.response?.data?.message ??
          'No se pudo conectar con el servidor',
      }
    }
  }

  /**
   * Paso 2, opción A: pide que se envíe el código de 6 dígitos al correo.
   */
  const enviarCodigoCorreo = async () => {
    if (!localStorage.getItem('login_ticket')) {
      return {
        success: false,
        error: 'La verificación expiró. Inicia sesión de nuevo.',
      }
    }

    try {
      const { data } = await api.post('/2fa/email/send', {}, cabeceraTicket())

      // Solo en desarrollo: permite ver el código sin abrir el correo
      if (import.meta.env.DEV && data.debug_code) {
        localStorage.setItem('debug_code', data.debug_code)
      }
      return { success: true, data }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message ?? 'No se pudo enviar el código',
      }
    }
  }

  /**
   * Paso 2, opción A: confirma el código recibido por correo.
   */
  const verificarCodigo = async (codigo) => {
    if (!localStorage.getItem('login_ticket')) {
      return {
        success: false,
        error: 'La verificación expiró. Inicia sesión de nuevo.',
      }
    }

    try {
      const { data } = await api.post('/verify-code', { code: codigo }, cabeceraTicket())
      guardarSesionNormal(data)
      return { success: true, data }
    } catch (error) {
      return {
        success: false,
        error:
          error.response?.data?.message ??
          error.response?.data?.errors?.code?.[0] ??
          'No se pudo conectar con el servidor',
      }
    }
  }

  // Abandonar la verificación sin completar el inicio de sesión
  const cancelarVerificacion = () => {
    localStorage.removeItem('login_ticket')
    localStorage.removeItem('user_email')
    localStorage.removeItem('debug_code')
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch (error) {
      console.error('Error', error)
    } finally {
      limpiarSesion()
    }
  }

  const isAuthenticated = () => {
    return !!localStorage.getItem('auth_token')
  }

  const getUserData = () => {
    const userData = localStorage.getItem('user_data')
    return userData ? JSON.parse(userData) : null
  }

  const getCurrentUser = async () => {
    try {
      const response = await api.get('/user')
      return { success: true, data: response.data }
    } catch (error) {
      return { success: false, error: error.response?.data }
    }
  }

  return {
    login,
    enviarCodigoCorreo,
    verificarCodigo,
    cancelarVerificacion,
    logout,
    getCurrentUser,
    isAuthenticated,
    getUserData,
  }
}

// Servicios para productos
export const useProductoService = () => {
  const obtenerProductos = async () => {
    try {
      const response = await api.get('/products')
      return response.data
    } catch (error) {
      console.error('Error al obtener productos:', error)
      throw error
    }
  }

  return { obtenerProductos }
}

// Servicios para el QR (Paso 2, opción B: aplicación autenticadora)
export const useTwoFactorService = () => {
  const generarQr = async () => {
    try {
      const response = await api.get('/2fa/generate', cabeceraTicket())
      return { success: true, data: response.data }
    } catch (error) {
      console.error('Error al generar código QR:', error)
      return {
        success: false,
        error: error.response?.data?.message || 'Error al obtener el código QR',
      }
    }
  }

  const verificarQr = async (codigo) => {
    try {
      const { data } = await api.post(
        '/2fa/verify',
        { one_time_password: codigo },
        cabeceraTicket(),
      )
      guardarSesionNormal(data)
      return { success: true, data }
    } catch (error) {
      return {
        success: false,
        error: error.response?.data?.message || 'Código de verificación incorrecto',
      }
    }
  }

  return { generarQr, verificarQr }
}

export default api