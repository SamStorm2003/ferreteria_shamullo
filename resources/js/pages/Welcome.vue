<template>
    <div class="scroll-smooth bg-white dark:bg-gray-800 font-sans text-gray-800 dark:text-white min-h-screen">
        <header class="fixed top-0 left-0 z-50 w-full bg-gray-900 dark:bg-gray-900 text-white shadow-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <h1 class="text-xl font-bold tracking-wide">Ferretería El Tornillo Feliz</h1>
                <div class="flex items-center space-x-6 text-sm font-medium relative">
                    <transition name="fade-slide">
                        <div v-if="showSearch" ref="searchPanel" tabindex="-1"
                            class="fixed top-0 left-1/2 z-50 w-full max-w-md -translate-x-1/2 rounded-lg bg-gray-900 dark:bg-gray-900/90 px-6 py-3 text-white shadow-lg">
                            <div class="flex items-center mb-4">
                                <input v-model="searchQuery" type="text" placeholder="Buscar productos..."
                                    class="w-full rounded bg-gray-700 dark:bg-gray-700 px-4 py-2 placeholder-gray-300 dark:placeholder-gray-400 focus:ring focus:ring-gray-500 dark:focus:ring-blue-500 focus:outline-none text-white"
                                    @keydown.escape="closeSearch" @input="debounceFetchProductos" autofocus />
                                <button @click="closeSearch" aria-label="Cerrar búsqueda"
                                    class="ml-3 rounded bg-gray-800 dark:bg-gray-800 px-3 py-2 hover:bg-gray-700 dark:hover:bg-gray-700 transition">
                                    ✕
                                </button>
                            </div>
                            <div v-if="filteredProductos.length === 0"
                                class="mt-6 text-center text-gray-400 dark:text-gray-500">
                                No se encontraron productos.
                            </div>
                            <div v-else class="space-y-4 max-h-[60vh] overflow-y-auto">
                                <div v-for="p in filteredProductos" :key="p.idProducto"
                                    class="flex items-center space-x-4 rounded border border-gray-700 dark:border-gray-600 bg-gray-800 dark:bg-gray-800/50 p-4">
                                    <img :src="p.url_imagen ?? '/default-image.jpg'" :alt="p.nombre"
                                        class="h-16 w-24 object-cover rounded-sm grayscale" />
                                    <div>
                                        <h4 class="text-lg font-semibold">{{ p.nombre }}</h4>
                                        <p class="text-sm text-gray-300 dark:text-gray-400">{{ p.descripcion }}</p>
                                        <p class="font-bold text-green-400 dark:text-blue-400">Bs {{
                                            p.stock_almacenes?.length ? p.stock_almacenes[0].precio_venta : 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>
                    <button @click="toggleSearch" aria-label="Abrir búsqueda"
                        class="transition hover:text-gray-400 dark:hover:text-blue-300 focus:outline-none focus:ring-2 focus:ring-white dark:focus:ring-blue-500 rounded">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 fill-current" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M12.9 14.32a8 8 0 1 1 1.414-1.414l4.387 4.386a1 1 0 0 1-1.414 1.415l-4.387-4.387zM14 8a6 6 0 1 0-12 0 6 6 0 0 0 12 0z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                    <a href="#inicio" class="hover:text-gray-400 dark:hover:text-blue-300">Inicio</a>
                    <a href="#productos" class="hover:text-gray-400 dark:hover:text-blue-300">Productos</a>
                    <a href="#servicios" class="hover:text-gray-400 dark:hover:text-blue-300">Servicios</a>
                    <nav class="flex items-center justify-end gap-4">
                        <Link v-if="$page.props.auth?.user" :href="route('dashboard')"
                            class="inline-block rounded-sm border border-[#19140035] dark:border-[#3E3E3A] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] dark:text-[#EDEDEC] hover:border-[#1915014a] dark:hover:border-[#62605b]">
                            Dashboard
                        </Link>
                        <Link v-else :href="route('login')"
                            class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] dark:text-[#EDEDEC] hover:border-[#19140035] dark:hover:border-[#3E3E3A]">
                            Log in
                        </Link>
                    </nav>
                </div>
            </div>
        </header>

        <section id="inicio"
            class="flex min-h-screen items-center justify-center bg-gray-100 dark:bg-gray-900 px-4 sm:px-6 lg:px-8 pt-24 bg-cover bg-center"
            style="background-image: url('https://images.unsplash.com/photo-1580587771525-78b9dba3b914?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80')">
            <div class="space-y-6 text-center bg-white/80 dark:bg-gray-900/80 p-8 rounded-lg">
                <h2 class="text-5xl font-extrabold fade-in text-gray-800 dark:text-white">
                    Construye tu mundo con herramientas de calidad
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-300 delay-200 fade-in">
                    Tu proyecto merece lo mejor. Nosotros te lo damos.
                </p>
                <button
                    class="rounded bg-gray-900 dark:bg-blue-600 px-6 py-3 text-white transition-all delay-400 fade-in hover:bg-gray-800 dark:hover:bg-blue-700">
                    Ver Catálogo
                </button>
            </div>
        </section>

        <section id="productos" class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-20">
            <h3 class="mb-10 text-center text-3xl font-bold fade-in">Productos Destacados</h3>
            <div class="mb-6 flex justify-center space-x-4">
                <button @click="setFilter('recent')"
                    class="rounded bg-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-300">
                    Recientes
                </button>
                <button @click="setFilter('price_low_high')"
                    class="rounded bg-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-300">
                    Precio: Bajo a Alto
                </button>
                <button @click="setFilter('price_high_low')"
                    class="rounded bg-gray-200 px-4 py-2 text-gray-700 hover:bg-gray-300">
                    Precio: Alto a Bajo
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-8">
                <div v-for="p in productos.data" :key="p.idProducto"
                    @click="openModal(p)"
                    class="cursor-pointer transform overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg transition fade-in hover:scale-105">
                    <img :src="p.url_imagen ?? '/default-image.jpg'" :alt="p.nombre" class="h-48 w-full object-cover grayscale" />
                    <div class="space-y-2 p-6">
                        <h4 class="text-xl font-semibold text-gray-800 dark:text-white">{{ p.nombre }}</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ p.descripcion }}</p>
                        <p class="font-bold text-gray-800 dark:text-blue-400">
                            Bs {{ p.stock_almacenes?.length ? p.stock_almacenes[0].precio_venta : 'N/A' }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Categoría: {{ p.categoria?.nombre ?? 'N/A' }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Stock: {{ p.total_stock ?? '0' }} unidades
                        </p>
                        <p v-if="p.promocion" class="text-sm text-green-600 dark:text-green-400">
                            Descuento: {{ p.promocion.descuento }}%
                        </p>
                    </div>
                </div>
            </div>
            <div class="mt-8 flex justify-center space-x-4">
                <button @click="fetchProductos(productos.prev_page_url)" :disabled="!productos.prev_page_url"
                    class="rounded bg-gray-300 px-4 py-2 text-gray-700 disabled:opacity-50">
                    Anterior
                </button>
                <span>Página {{ productos.current_page }} de {{ productos.last_page }}</span>
                <button @click="fetchProductos(productos.next_page_url)" :disabled="!productos.next_page_url"
                    class="rounded bg-gray-300 px-4 py-2 text-gray-700 disabled:opacity-50">
                    Siguiente
                </button>
            </div>

            <transition name="fade">
                <div v-if="selectedProducto" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                        <div class="flex justify-between items-center">
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white">{{ selectedProducto.nombre }}</h3>
                            <button @click="closeModal" class="text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white">
                                ✕
                            </button>
                        </div>
                        <img :src="selectedProducto.url_imagen ?? '/default-image.jpg'" :alt="selectedProducto.nombre"
                            class="w-full h-64 object-cover rounded-lg" />
                        <p class="text-gray-600 dark:text-gray-300">{{ selectedProducto.descripcion }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Código: {{ selectedProducto.codigo }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Marca: {{ selectedProducto.marca ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Categoría: {{ selectedProducto.categoria?.nombre ?? 'N/A' }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Proveedor: {{ selectedProducto.proveedor?.nombre ?? 'N/A' }}</p>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400">
                            Precio: Bs {{ selectedProducto.stock_almacenes?.length ? selectedProducto.stock_almacenes[0].precio_venta : 'N/A' }}
                        </p>
                        <p v-if="selectedProducto.promocion" class="text-green-600 dark:text-green-400">
                            Promoción: {{ selectedProducto.promocion.nombre }} ({{ selectedProducto.promocion.descuento }}% descuento)
                            <br />
                            Válida hasta: {{ new Date(selectedProducto.promocion.fecha_fin).toLocaleDateString() }}
                        </p>
                        <div class="space-y-2">
                            <h4 class="text-lg font-semibold">Disponibilidad por Almacén</h4>
                            <div v-if="selectedProducto.stock_almacenes?.length" v-for="stock in selectedProducto.stock_almacenes" :key="stock.idStock"
                                class="text-sm text-gray-600 dark:text-gray-400">
                                <p>{{ stock.almacen?.nombre ?? 'Desconocido' }}: {{ stock.cantidad }} unidades</p>
                                <p>Costo Unitario: Bs {{ stock.costo_unitario ?? 'N/A' }}</p>
                                <p>Precio Venta: Bs {{ stock.precio_venta ?? 'N/A' }}</p>
                            </div>
                            <p v-else class="text-sm text-gray-600 dark:text-gray-400">No hay stock disponible.</p>
                        </div>
                    </div>
                </div>
            </transition>
        </section>

        <section id="servicios" class="bg-gray-50 dark:bg-gray-900 px-4 sm:px-6 lg:px-8 py-20">
            <h3 class="mb-10 text-center text-3xl font-bold fade-in">Nuestros Servicios</h3>
            <div class="mx-auto grid max-w-6xl gap-6 sm:grid-cols-2 md:grid-cols-3">
                <div class="rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-md fade-in">
                    <h4 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Asesoría técnica</h4>
                    <p class="text-gray-600 dark:text-gray-400">Recibe recomendaciones de expertos según tus necesidades.</p>
                </div>
                <div class="rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-md delay-200 fade-in">
                    <h4 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Entregas rápidas</h4>
                    <p class="text-gray-600 dark:text-gray-400">Recibe tus herramientas sin salir de casa.</p>
                </div>
                <div class="rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-md delay-400 fade-in">
                    <h4 class="mb-2 text-lg font-semibold text-gray-800 dark:text-white">Garantía y soporte</h4>
                    <p class="text-gray-600 dark:text-gray-400">Te acompañamos en cada compra y postventa.</p>
                </div>
            </div>
        </section>

        <section class="bg-gray-100 dark:bg-gray-900 py-20 text-center fade-in">
            <h3 class="mb-4 text-2xl font-bold text-gray-800 dark:text-white">¿Listo para comenzar tu proyecto?</h3>
            <p class="mb-6 text-gray-700 dark:text-gray-300">Explora nuestras herramientas de calidad profesional</p>
            <button class="rounded bg-gray-900 dark:bg-blue-600 px-6 py-3 text-white transition-all hover:bg-gray-800 dark:hover:bg-blue-700">
                Ver Productos
            </button>
        </section>

        <footer id="contacto" class="bg-gray-900 dark:bg-gray-900 py-10 text-center text-sm text-white">
            <p>Calle Principal #123 - La Paz, Bolivia</p>
            <p>📞 715-55555 · ✉️ tornillofeliz@correo.com</p>
            <p class="mt-4">© 2025 El Tornillo Feliz</p>
        </footer>
    </div>
</template>

<script setup lang="ts">
import { onMounted, ref, computed, onBeforeUnmount } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import type { PageProps as InertiaPageProps } from "@inertiajs/core";

interface Categoria {
    idCategoria: number;
    nombre: string;
}

interface Almacen {
    idAlmacen: number;
    nombre: string;
    ubicacion: string;
    fecha_registro: string;
}

interface StockAlmacen {
    idStock: number;
    idProducto: number;
    idAlmacen: number;
    cantidad: number;
    costo_unitario: string;
    precio_venta: string;
    fecha_registro: string;
    deleted_at: string | null;
    created_at: string;
    updated_at: string;
    almacen: Almacen | null;
}

interface Promocion {
    idPromocion: number;
    nombre: string;
    descripcion: string;
    idProducto: number | null;
    descuento: number;
    url_imagen: string | null;
    fecha_inicio: string;
    fecha_fin: string;
    estado: "activa" | "inactiva";
}

interface Proveedor {
    idProveedor: number;
    nombre: string;
}

interface Producto {
    idProducto: number;
    nombre: string;
    codigo: string;
    descripcion: string;
    idCategoria: number | null;
    marca: string | null;
    url_imagen: string | null;
    idProveedor: number | null;
    fecha_ingreso: string;
    fecha_actualizacion: string;
    estado: "activo" | "inactivo";
    stock_almacenes: StockAlmacen[];
    total_stock: number;
    categoria?: Categoria;
    promocion?: Promocion | null;
    proveedor?: Proveedor;
}

interface Pagination {
    data: Producto[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface PageProps extends InertiaPageProps {
    search?: string;
    filter?: string;
    productos: Pagination;
}

const page = usePage<PageProps>();

const showSearch = ref(false);
const searchQuery = ref<string>(page.props.search ?? "");
const filter = ref<string>(page.props.filter ?? "");
const productos = ref<Pagination>(page.props.productos);
const searchPanelRef = ref<HTMLElement | null>(null);
const selectedProducto = ref<Producto | null>(null);

const filteredProductos = computed(() => productos.value.data);

const toggleSearch = () => {
    showSearch.value = !showSearch.value;
    if (!showSearch.value) searchQuery.value = "";
};

const closeSearch = () => {
    showSearch.value = false;
    searchQuery.value = "";
};

const openModal = (producto: Producto) => {
    selectedProducto.value = producto;
};

const closeModal = () => {
    selectedProducto.value = null;
};

const setFilter = (value: string) => {
    filter.value = value;
    fetchProductos();
};

const debounce = <T extends (...args: any[]) => void>(func: T, wait: number) => {
    let timeout: ReturnType<typeof setTimeout> | null = null;
    return (...args: Parameters<T>) => {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => func(...args), wait);
    };
};

const fetchProductos = (url?: string) => {
    const params: { search?: string; filter?: string; page?: string } = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (filter.value) params.filter = filter.value;
    if (url) params.page = new URL(url).searchParams.get("page") ?? "";

    window.location.href = route("welcome", params);
};

const debounceFetchProductos = debounce(fetchProductos, 300);

const onClickOutside = (event: MouseEvent) => {
    const searchPanel = searchPanelRef.value;
    if (showSearch.value && searchPanel && !searchPanel.contains(event.target as Node)) {
        closeSearch();
    }
};

const onKeyDown = (event: KeyboardEvent) => {
    if (event.key === "Escape") {
        if (showSearch.value) closeSearch();
        if (selectedProducto.value) closeModal();
    }
};

onMounted(() => {
    document.addEventListener("click", onClickOutside);
    document.addEventListener("keydown", onKeyDown);

    const elements = document.querySelectorAll(".fade-in");
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("animate-fade-in");
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.1 }
    );
    elements.forEach((el) => observer.observe(el));
});

onBeforeUnmount(() => {
    document.removeEventListener("click", onClickOutside);
    document.removeEventListener("keydown", onKeyDown);
});
</script>

<style scoped>
.fade {
    transition: all 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

body {
    font-family: 'Inter', 'Roboto', sans-serif;
}

.fade-in {
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.6s ease-out;
}

.animate-fade-in {
    opacity: 1 !important;
    transform: translateY(0) !important;
}

.delay-200 {
    transition-delay: 0.2s;
}

.delay-400 {
    transition-delay: 0.4s;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.4s ease;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateX(100%);
}

.fade-slide-enter-to {
    opacity: 1;
    transform: translateX(0);
}

.fade-slide-leave-from {
    opacity: 1;
    transform: translateX(0);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateX(100%);
}
</style>