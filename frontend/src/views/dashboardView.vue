<template>
  <div class="dashboard">
    <div class="encabezado">
      <h1>Panel principal</h1>
      <Button label="Salir" @click="cerrarSesion" severity="danger" />
    </div>

    <div class="contenido">
      <!-- <ProductoNuevo /> -->
      <ProductoGrafico />
      <ProductoTable />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService } from '@/service/api'
import ProductoTable from './productos/ProductoTable.vue'
import ProductoGrafico from './productos/ProductoGrafico.vue'
// import ProductoNuevo from './productos/ProductoNuevo.vue'

const router = useRouter()
const { logout, getUserData } = useAuthService()

const usuario = ref(null)
const token = ref('')
onMounted(() => {
  usuario.value = getUserData()
  token.value = localStorage.getItem('auth_token')
})

const cerrarSesion = async () => {
  await logout()
  router.push('/login')
}
</script>