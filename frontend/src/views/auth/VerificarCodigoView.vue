<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Verifica tu correo</h2>
        <p v-if="correoOculto">
          Enviamos un código de 6 dígitos a <strong>{{ correoOculto }}</strong>. Vence en 10 minutos.
        </p>
        <p v-else>Enviamos un código de 6 dígitos a tu correo electrónico. Vence en 10 minutos.</p>
      </div>

      <!-- Solo aparece en desarrollo (npm run dev); en producción no existe -->
      <Message v-if="codigoDebug" severity="warn" class="mensaje-server">
        Modo pruebas. Código generado: <strong>{{ codigoDebug }}</strong>
      </Message>

      <form @submit.prevent="enviarCodigo" class="formulario-login">
        <div class="campo-formulario">
          <label for="codigo">Código de verificación</label>
          <InputText
            id="codigo"
            v-model="codigo"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            placeholder="000000"
            :class="{ 'p-invalid': mensajeError }"
            class="input-completo input-codigo"
            @input="soloNumeros"
          />
        </div>

        <Button type="submit" label="Verificar código" :loading="cargando" class="boton-login" />
      </form>

      <Message v-if="mensajeError" severity="error" class="mensaje-server">
        {{ mensajeError }}
      </Message>

      <div class="pie-verificacion">
        <Button label="Usar otro método" text :disabled="cargando" @click="cambiarMetodo" />
        <Button label="Volver a iniciar sesión" text :disabled="cargando" @click="volverAlLogin" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService } from '@/service/api'

const router = useRouter()
const { verificarCodigo, cancelarVerificacion } = useAuthService()

const codigo = ref('')
const cargando = ref(false)
const mensajeError = ref('')
const email = ref('')
const codigoDebug = ref('')

// ana.perez@correo.com -> a***@correo.com
const correoOculto = computed(() => {
  const [usuario, dominio] = email.value.split('@')
  return usuario && dominio ? `${usuario[0]}***@${dominio}` : ''
})

onMounted(() => {
  email.value = localStorage.getItem('user_email') || ''

  // import.meta.env.DEV es false en el build de producción
  if (import.meta.env.DEV) {
    codigoDebug.value = localStorage.getItem('debug_code') || ''
  }
})

const soloNumeros = () => {
  codigo.value = codigo.value.replace(/\D/g, '')
}

const enviarCodigo = async () => {
  if (codigo.value.length !== 6) {
    mensajeError.value = 'Escribe los 6 dígitos del código.'
    return
  }

  cargando.value = true
  mensajeError.value = ''

  try {
    const resultado = await verificarCodigo(codigo.value)

    if (!resultado.success) {
      mensajeError.value = resultado.error
      return
    }

    router.push({ name: 'AdminDashboard' })
  } finally {
    cargando.value = false
  }
}

const cambiarMetodo = () => {
  router.push({ name: 'elegir-metodo' })
}

// Para empezar de cero hay que iniciar sesión de nuevo
const volverAlLogin = () => {
  cancelarVerificacion()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.input-codigo {
  text-align: center;
  font-size: 1.5rem;
  letter-spacing: 0.5em;
  font-variant-numeric: tabular-nums;
}

.pie-verificacion {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
</style>