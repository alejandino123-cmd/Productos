<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Verifica tu identidad</h2>
        <p>Elige cómo quieres confirmar que eres tú</p>
      </div>

      <div class="opciones-metodo">
        <Button
          label="Recibir código por correo"
          icon="pi pi-envelope"
          :loading="enviando"
          class="boton-login"
          @click="porCorreo"
        />
        <Button
          label="Usar Google Authenticator (QR)"
          icon="pi pi-qrcode"
          severity="secondary"
          :disabled="enviando"
          class="boton-login"
          @click="porQr"
        />
      </div>

      <Message v-if="mensajeError" severity="error" class="mensaje-server">
        {{ mensajeError }}
      </Message>

      <div class="pie-verificacion">
        <Button label="Volver a iniciar sesión" text :disabled="enviando" @click="volverAlLogin" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService } from '@/service/api'

const router = useRouter()
const { enviarCodigoCorreo, cancelarVerificacion } = useAuthService()

const enviando = ref(false)
const mensajeError = ref('')

const porCorreo = async () => {
  enviando.value = true
  mensajeError.value = ''

  try {
    const resultado = await enviarCodigoCorreo()

    if (!resultado.success) {
      mensajeError.value = resultado.error
      return
    }

    router.push({ name: 'verificar-codigo' })
  } finally {
    enviando.value = false
  }
}

const porQr = () => {
  router.push({ name: 'two-factor' })
}

const volverAlLogin = () => {
  cancelarVerificacion()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.opciones-metodo {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.pie-verificacion {
  display: flex;
  justify-content: center;
  margin-top: 0.5rem;
}
</style>