import { createRouter, createWebHistory } from 'vue-router'
import LoginAuth from '@/views/auth/LoginAuth.vue'
import DashboardView from '@/views/dashboardView.vue'

// Solo entra si hay sesión completa; si no, va al login
const requiereAutenticacion = () => {
  return localStorage.getItem('auth_token') ? true : '/login'
}

// Si ya hay sesión, no se muestra el login
const redirigirSiAutenticado = () => {
  return localStorage.getItem('auth_token') ? '/dashboard' : true
}

// Las pantallas del segundo paso (elegir método, correo, QR) solo tienen sentido
// justo después del login de un usuario normal, cuando existe el ticket temporal
const requiereVerificacionPendiente = () => {
  if (localStorage.getItem('auth_token')) return '/dashboard'
  if (!localStorage.getItem('login_ticket')) return '/login'
  return true
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/dashboard' },
    {
      path: '/login',
      name: 'login',
      component: LoginAuth,
      beforeEnter: redirigirSiAutenticado,
      meta: { title: 'login' },
    },
    {
      // Usuario normal: elegir entre código por correo o QR
      path: '/elegir-metodo',
      name: 'elegir-metodo',
      component: () => import('@/views/auth/Metodo.vue'),
      beforeEnter: requiereVerificacionPendiente,
      meta: { title: 'Elegir método' },
    },
    {
      // Opción A: código de 6 dígitos que llega al correo
      path: '/verificar-codigo',
      name: 'verificar-codigo',
      component: () => import('@/views/auth/VerificarCodigoView.vue'),
      beforeEnter: requiereVerificacionPendiente,
      meta: { title: 'Verificar código' },
    },
    {
      // Opción B: código QR con Google Authenticator
      path: '/auth/two-factor',
      name: 'two-factor',
      component: () => import('@/views/auth/TwoFactorView.vue'),
      beforeEnter: requiereVerificacionPendiente,
      meta: { title: 'Verificación QR' },
    },
    {
      path: '/dashboard',
      name: 'AdminDashboard',
      component: DashboardView,
      beforeEnter: requiereAutenticacion,
      meta: { title: 'dashboard' },
    },
    // La ruta comodín siempre al final
    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      component: () => import('@/views/NotFound.vue'),
      meta: { title: 'Página No Encontrada' },
    },
  ],
})

export default router