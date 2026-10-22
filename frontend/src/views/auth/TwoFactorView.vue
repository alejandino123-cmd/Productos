<template>
  <div class="contenedor-qr-view">
    <div class="tarjeta-qr">
      <div class="encabezado-qr">
        <h2>Google Authenticator</h2>
        <p v-if="yaConfigurado">Ingresa el código de 6 dígitos que muestra tu aplicación</p>
        <p v-else>Escanea el código QR con Google Authenticator e ingresa el código que te muestra</p>
      </div>

      <!-- El QR solo aparece la primera vez, cuando todavía no está configurado -->
      <div v-if="!yaConfigurado && qrCodeImage" class="seccion-qr">
        <img :src="qrCodeImage" alt="Código QR 2FA" class="imagen-qr" />
        <p v-if="secretKey" class="clave-secreta">
          Clave manual: <code>{{ secretKey }}</code>
        </p>
      </div>
      <div v-else-if="!yaConfigurado && !mensajeError" class="text-center py-4">
        <p class="text-gray-500 animate-pulse">Cargando código QR...</p>
      </div>

      <form @submit.prevent="verificarCodigoQr" class="formulario-qr">
        <div class="campo-formulario">
          <label for="codigo">Código de verificación</label>
          <InputText
            id="codigo"
            v-model="codigo"
            inputmode="numeric"
            autocomplete="one-time-code"
            placeholder="000000"
            maxlength="6"
            class="input-completo input-codigo"
            @input="soloNumeros"
          />
        </div>

        <Button type="submit" label="Verificar e ingresar" :loading="cargando" class="boton-qr" />
      </form>

      <Message v-if="mensajeError" severity="error" class="mt-3">
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
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService, useTwoFactorService } from '@/service/api'

const router = useRouter()
const { cancelarVerificacion } = useAuthService()
const { generarQr, verificarQr } = useTwoFactorService()

const cargando = ref(false)
const mensajeError = ref('')
const codigo = ref('')
const qrCodeImage = ref('')
const secretKey = ref('')
const yaConfigurado = ref(false)

const soloNumeros = () => {
  codigo.value = codigo.value.replace(/\D/g, '')
}

const cambiarMetodo = () => {
  router.push({ name: 'elegir-metodo' })
}

const volverAlLogin = () => {
  cancelarVerificacion()
  router.push({ name: 'login' })
}

onMounted(async () => {
  const resultado = await generarQr()

  if (!resultado.success) {
    mensajeError.value = resultado.error
    return
  }

  if (resultado.data.already_configured) {
    yaConfigurado.value = true
    return
  }

  qrCodeImage.value = resultado.data.qr_code // ya viene como data:image/svg+xml;base64,...
  secretKey.value = resultado.data.secret
})

const verificarCodigoQr = async () => {
  if (codigo.value.length !== 6) {
    mensajeError.value = 'Escribe los 6 dígitos del código.'
    return
  }

  cargando.value = true
  mensajeError.value = ''

  try {
    const resultado = await verificarQr(codigo.value)

    if (!resultado.success) {
      mensajeError.value = resultado.error
      return
    }

    router.push({ name: 'AdminDashboard' })
  } finally {
    cargando.value = false
  }
}
</script>

<style scoped>
.contenedor-qr-view {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.tarjeta-qr {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
.encabezado-qr {
  text-align: center;
  margin-bottom: 1.5rem;
}
.seccion-qr {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 1.5rem;
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 8px;
}
.imagen-qr {
  width: 180px;
  height: 180px;
  margin-bottom: 0.5rem;
}
.clave-secreta {
  font-size: 0.8rem;
  color: #666;
  word-break: break-all;
}
.campo-formulario {
  margin-bottom: 1rem;
}
.input-completo {
  width: 100%;
}
.input-codigo {
  text-align: center;
  font-size: 1.5rem;
  letter-spacing: 0.5em;
  font-variant-numeric: tabular-nums;
}
.boton-qr {
  width: 100%;
  margin-top: 1rem;
}
.pie-verificacion {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}
</style>