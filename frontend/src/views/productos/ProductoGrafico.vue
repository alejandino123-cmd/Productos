<template>
  <section class="grafico-productos" :class="{ oscuro }">
    <header class="grafico-encabezado">
      <div>
        <h2>{{ etiquetaActiva }} por producto</h2>
        <p v-if="recortado" class="grafico-detalle">
          Mostrando los {{ MAX_BARRAS }} más altos de {{ origen.length }} productos
        </p>
      </div>

      <div v-if="metricasDisponibles.length > 1" class="grafico-metricas">
        <Button
          v-for="m in metricasDisponibles"
          :key="m"
          :label="ETIQUETAS[m]"
          size="small"
          :outlined="metricaActiva !== m"
          @click="metrica = m"
        />
      </div>
    </header>

    <Message v-if="hayError" severity="error">
      No se pudieron cargar los productos para el gráfico. Revisa que el backend esté encendido.
    </Message>

    <p v-else-if="cargando" class="grafico-estado">Cargando gráfico…</p>

    <p v-else-if="!origen.length" class="grafico-estado">
      Todavía no hay productos. Crea el primero para ver el gráfico.
    </p>

    <p v-else-if="!metricasDisponibles.length" class="grafico-estado">
      Los productos no tienen precio ni stock para graficar.
    </p>

    <Chart v-else type="bar" :data="datos" :options="opciones" class="lienzo" />
  </section>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Chart from 'primevue/chart'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useProductoService } from '@/service/api'

const props = defineProps({
  // Si el dashboard ya cargó los productos, pásalos aquí y no se repite la petición
  productos: { type: Array, default: null },
})

// Nombres que puede tener cada campo en tu API. Si los tuyos se llaman distinto, agrégalos aquí.
const CAMPOS = {
  nombre: ['name', 'nombre'],
  precio: ['price', 'precio'],
  stock: ['stock', 'cantidad', 'existencias'],
}
const ETIQUETAS = { precio: 'Precio', stock: 'Stock' }
const MAX_BARRAS = 10

const { obtenerProductos } = useProductoService()

const lista = ref([])
const cargando = ref(false)
const hayError = ref(false)
const metrica = ref('precio')
const oscuro = ref(false)
const colores = ref({ barra: '#34d399', texto: '#475569', borde: 'rgba(100, 116, 139, 0.18)' })

const origen = computed(() => props.productos ?? lista.value)

const leer = (producto, claves) => {
  const clave = claves.find((c) => producto[c] !== undefined && producto[c] !== null)
  return clave ? producto[clave] : undefined
}

const esNumero = (valor) => valor !== undefined && valor !== null && valor !== '' && Number.isFinite(Number(valor))

// Solo se ofrecen las métricas que de verdad existen en los productos
const metricasDisponibles = computed(() =>
  ['precio', 'stock'].filter((m) => origen.value.some((p) => esNumero(leer(p, CAMPOS[m])))),
)

const metricaActiva = computed(() =>
  metricasDisponibles.value.includes(metrica.value) ? metrica.value : metricasDisponibles.value[0],
)

const etiquetaActiva = computed(() => ETIQUETAS[metricaActiva.value] ?? 'Productos')

const filasCompletas = computed(() => {
  const m = metricaActiva.value
  if (!m) return []
  return origen.value
    .filter((p) => esNumero(leer(p, CAMPOS[m])))
    .map((p) => ({
      id: p.id,
      nombre: String(leer(p, CAMPOS.nombre) ?? `Producto ${p.id ?? ''}`).trim(),
      valor: Number(leer(p, CAMPOS[m])),
    }))
    .sort((a, b) => b.valor - a.valor)
})

const truncar = (texto, max) => (texto.length > max ? `${texto.slice(0, max - 1).trimEnd()}…` : texto)

// Si dos productos se llaman igual, se agrega su número para distinguirlos en el eje
const filas = computed(() => {
  const visibles = filasCompletas.value.slice(0, MAX_BARRAS)
  const repetidos = new Map()
  visibles.forEach((f) => repetidos.set(f.nombre, (repetidos.get(f.nombre) ?? 0) + 1))

  return visibles.map((f, i) => ({
    ...f,
    etiqueta: truncar(f.nombre, 20) + (repetidos.get(f.nombre) > 1 ? ` #${f.id ?? i + 1}` : ''),
  }))
})
const recortado = computed(() => filasCompletas.value.length > MAX_BARRAS)

const formatoPrecio = (n, decimales = 2) =>
  new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: decimales,
    maximumFractionDigits: decimales,
  }).format(n)

const formatoValor = (n, decimales = 2) =>
  metricaActiva.value === 'precio'
    ? formatoPrecio(n, decimales)
    : new Intl.NumberFormat('en-US').format(n)

// En el eje se omiten los centavos cuando los valores son grandes
const decimalesEje = computed(() => (Math.max(0, ...filas.value.map((f) => f.valor)) >= 10 ? 0 : 2))

const datos = computed(() => ({
  labels: filas.value.map((f) => f.etiqueta),
  datasets: [
    {
      label: etiquetaActiva.value,
      data: filas.value.map((f) => f.valor),
      backgroundColor: colores.value.barra,
      borderRadius: 6,
      maxBarThickness: 56,
    },
  ],
}))

const opciones = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
    tooltip: {
      callbacks: {
        // En el tooltip se ve el nombre completo, aunque en el eje esté recortado
        title: (items) => filas.value[items[0].dataIndex]?.nombre ?? '',
        label: (ctx) => ` ${formatoValor(ctx.parsed.y)}`,
      },
    },
  },
  scales: {
    x: {
      ticks: { color: colores.value.texto },
      grid: { display: false },
      border: { color: colores.value.borde },
    },
    y: {
      beginAtZero: true,
      ticks: { color: colores.value.texto, callback: (v) => formatoValor(v, decimalesEje.value) },
      grid: { color: colores.value.borde },
      border: { display: false },
    },
  },
}))

// Detecta si el fondo de la página es oscuro, para elegir colores con buen contraste
const fondoEsOscuro = () => {
  for (const el of [document.body, document.documentElement]) {
    const n = getComputedStyle(el).backgroundColor.match(/[\d.]+/g)?.map(Number)
    if (!n || n.length < 3) continue
    if (n.length === 4 && n[3] === 0) continue // transparente
    return 0.299 * n[0] + 0.587 * n[1] + 0.114 * n[2] < 128
  }
  return false
}

// Toma los colores del tema de PrimeVue para que el gráfico combine con el resto
const cssVar = (nombres, respaldo) => {
  const estilos = getComputedStyle(document.documentElement)
  for (const nombre of nombres) {
    const valor = estilos.getPropertyValue(nombre).trim()
    if (valor) return valor
  }
  return respaldo
}

const cargarProductos = async () => {
  cargando.value = true
  hayError.value = false
  try {
    const respuesta = await obtenerProductos()
    // La API puede devolver [ ... ] o { data: [ ... ] }
    lista.value = Array.isArray(respuesta) ? respuesta : (respuesta?.data ?? [])
  } catch {
    hayError.value = true
  } finally {
    cargando.value = false
  }
}

onMounted(() => {
  oscuro.value = fondoEsOscuro()
  colores.value = {
    barra: cssVar(['--p-primary-color', '--primary-color'], colores.value.barra),
    texto: oscuro.value ? '#cbd5e1' : '#475569',
    borde: oscuro.value ? 'rgba(148, 163, 184, 0.2)' : 'rgba(100, 116, 139, 0.18)',
  }

  if (props.productos === null) {
    cargarProductos()
  }
})
</script>

<style scoped>
/* Misma apariencia que la tarjeta del encabezado: fondo blanco, sombra suave y sin borde */
.grafico-productos {
  margin-bottom: 1.5rem;
  padding: 1.25rem 1.5rem;
  background: #fff;
  color: #1e293b;
  border-radius: 0.75rem;
  box-shadow: 0 1px 8px rgba(15, 23, 42, 0.08);
}

.grafico-productos.oscuro {
  background: #18181b;
  color: #e4e4e7;
  box-shadow: none;
  border: 1px solid #27272a;
}

.grafico-encabezado {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.grafico-encabezado h2 {
  margin: 0;
  font-size: 1.15rem;
}

.grafico-detalle {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: #64748b;
}

.oscuro .grafico-detalle {
  color: #a1a1aa;
}

.grafico-metricas {
  display: flex;
  gap: 0.5rem;
}

.grafico-estado {
  margin: 0;
  padding: 2rem 0;
  text-align: center;
  color: #64748b;
}

.lienzo {
  height: 320px;
}
</style>