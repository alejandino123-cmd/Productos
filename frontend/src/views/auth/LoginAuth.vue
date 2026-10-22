<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Iniciar sesión</h2>
        <p>Ingresa tus credenciales para acceder al sistema</p>
      </div>

      <form @submit.prevent="enviarLogin" class="formulario-login">
        <div class="campo-formulario">
          <label for="email">Correo electrónico</label>
          <InputText
            id="email"
            v-model="credenciales.email"
            type="email"
            placeholder="tu@email.com"
            autocomplete="username"
            :class="{ 'p-invalid': errores.email }"
            class="input-completo"
          />
          <small class="mensaje-error" v-if="errores.email">{{ errores.email }}</small>
        </div>

        <div class="campo-formulario">
          <label for="password">Contraseña</label>
          <Password
            id="password"
            v-model="credenciales.password"
            placeholder="Ingresa tu contraseña"
            autocomplete="current-password"
            :feedback="false"
            toggleMask
            :class="{ 'p-invalid': errores.password }"
            class="input-completo password-completo"
          />
          <small class="mensaje-error" v-if="errores.password">{{ errores.password }}</small>
        </div>

        <Button type="submit" label="Iniciar sesión" :loading="cargando" class="boton-login" />
      </form>

      <Message v-if="mensajeError" severity="error" class="mensaje-server">
        {{ mensajeError }}
      </Message>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService } from '@/service/api'

const router = useRouter()
const { login } = useAuthService()

const cargando = ref(false)
const mensajeError = ref('')

const credenciales = reactive({
  email: '',
  password: '',
})

const errores = reactive({
  email: '',
  password: '',
})

const validarFormulario = () => {
  let esValido = true
  errores.email = ''
  errores.password = ''

  if (!credenciales.email.trim()) {
    errores.email = 'El correo electrónico es requerido'
    esValido = false
  } else if (!/\S+@\S+\.\S+/.test(credenciales.email)) {
    errores.email = 'El formato del correo no es válido'
    esValido = false
  }

  if (!credenciales.password) {
    errores.password = 'La contraseña es requerida'
    esValido = false
  } else if (credenciales.password.length < 6) {
    errores.password = 'La contraseña debe tener al menos 6 caracteres'
    esValido = false
  }

  return esValido
}

const enviarLogin = async () => {
  if (!validarFormulario()) return

  cargando.value = true
  mensajeError.value = ''

  try {
    const resultado = await login(credenciales)

    if (!resultado.success) {
      mensajeError.value = resultado.error
      return
    }

    // Superadmin: entra directo. Usuario normal: elige cómo verificar su identidad
    if (resultado.requires2fa) {
      router.push({ name: 'elegir-metodo' })
    } else {
      router.push({ name: 'AdminDashboard' })
    }
  } catch (error) {
    mensajeError.value = 'Ocurrió un error al procesar el inicio de sesión'
  } finally {
    cargando.value = false
  }
}
</script>

<style scoped>
:deep(.p-password) {
  width: 100%;
  position: relative;
}
:deep(.p-password .p-password-input) {
  width: 100%;
  padding-right: 2.5rem;
}
:deep(.p-password-toggle) {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  cursor: pointer;
  z-index: 10;
}
</style>