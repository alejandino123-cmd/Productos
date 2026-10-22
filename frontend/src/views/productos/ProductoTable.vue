<template>
  <div class="tabla-productos">
    <!--Botones de Exportación Profesionales -->
    <div class="acciones-tabla mb-4 flex justify-end gap-2">
      <Button 
        label="Exportar CSV" 
        icon="pi pi-file-excel" 
        severity="success" 
        raised 
        @click="exportarCSV" 
      />
      <Button 
        label="Exportar PDF" 
        icon="pi pi-file-pdf" 
        severity="danger" 
        raised 
        @click="exportarPDF" 
      />
    </div>
    <br>
  
    <Card>
      <template #title>Lista de Productos</template>

      <template #content>
        <!-- ✅ DIV con ID para PDF -->
        <div id="tabla-productos-pdf" class="contenedor-tabla">
          <DataTable
            :value="productosPaginados"
            :loading="cargando"
            paginator
            :rows="filasPorPagina"
            :rowsPerPageOptions="[5, 10, 20, 50]"
            :totalRecords="productos.length"-
            tableStyle="min-width: 50rem"
            class="p-datatable-sm"
          >
            <Column field="id" header="ID" style="width: 10%"></Column>
            <!-- Cambiado a field="name" -->
            <Column field="name" header="Nombre" style="width: 25%"></Column>
            <!-- Cambiado a field="description" -->
            <Column field="description" header="Descripción" style="width: 30%">
              <template #body="datosFila">
                {{ datosFila.data.description || 'Sin descripción' }}
              </template>
            </Column>
            <!-- Cambiado a field="price" y usando datosFila.data.price -->
            <Column field="price" header="Precio" style="width: 15%">
              <template #body="datosFila">
                {{ formatearPrecio(datosFila.data.price) }}
              </template>
            </Column>

            <template #empty>
              <div class="estado-vacio">
                <i class="pi pi-inbox" style="font-size: 2rem"></i>
                <p>No se encontraron productos</p>
              </div>
            </template>

            <template #loading>
              <div class="estado-cargando">
                <i class="pi pi-spinner pi-spin" style="font-size: 2rem"></i>
                <p>Cargando productos...</p>
              </div>
            </template>
          </DataTable>
        </div>

        <div class="informacion-tabla">
          <span class="p-text-secondary">
            Mostrando {{ mostrarDesde }} a {{ mostrarHasta }} de {{ productos.length }} productos
          </span>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useProductoService } from '@/service/api'
import jsPDF from 'jspdf'
import autoTable from 'jspdf-autotable'

const { obtenerProductos } = useProductoService()

// Variables reactivas
const productos = ref([])
const cargando = ref(false)
const filasPorPagina = ref(5)

// Computed properties
const productosPaginados = computed(() => productos.value)
const mostrarDesde = computed(() => (productos.value.length ? 1 : 0))
const mostrarHasta = computed(() => productos.value.length)

// Métodos de formato
const formatearPrecio = (precio) => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
  }).format(precio || 0)
}

// Cargar productos desde la API
const cargarProductos = async () => {
  cargando.value = true
  try {
    const respuesta = await obtenerProductos()
    if (Array.isArray(respuesta)) {
      productos.value = respuesta
    } else if (respuesta && Array.isArray(respuesta.data)) {
      productos.value = respuesta.data
    } else {
      productos.value = []
    }
  } catch (error) {
    console.error('Error al cargar productos:', error)
    productos.value = []
  } finally {
    cargando.value = false
  }
}

//  Exportar a PDF de forma profesional
const exportarPDF = () => {
  const doc = new jsPDF()
  
  // Título del reporte
  doc.setFontSize(18)
  doc.text('Reporte de Inventario - ULS', 14, 20)
  
  doc.setFontSize(11)
  doc.setTextColor(100)
  doc.text(`Fecha de emisión: ${new Date().toLocaleDateString()}`, 14, 28)

  // Mapear datos para la tabla del PDF
  const columnas = ['ID', 'Nombre', 'Descripción', 'Precio', 'Stock']
  const filas = productos.value.map(p => [
    p.id,
    p.name,
    p.description || 'Sin descripción',
    `$${p.price}`,
    p.stock
  ])

  // Usando autoTable importado explícitamente
  autoTable(doc, {
    head: [columnas],
    body: filas,
    startY: 35,
    theme: 'grid',
    headStyles: { fillColor: [41, 128, 185] },
  })

  doc.save('reporte_productos.pdf')
}

// Exportar a CSV
const exportarCSV = () => {
  if (!productos.value.length) return

  const cabeceras = ['ID,Nombre,Descripcion,Precio,Stock']
  const filas = productos.value.map(p => 
    `"${p.id}","${p.name}","${p.description || ''}","${p.price}","${p.stock}"`
  )

  const contenidoCSV = [cabeceras, ...filas].join('\n')
  const blob = new Blob([contenidoCSV], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', 'productos_inventario.csv')
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

// Ciclo de vida
onMounted(() => {
  cargarProductos()
})

defineExpose({ cargarProductos })
</script>

<style scoped>
.acciones-tabla {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
}
.contenedor-tabla {
  background: white;
  padding: 1rem;
  border-radius: 6px;
}
</style>