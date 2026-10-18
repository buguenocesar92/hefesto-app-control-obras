<template>
    <div class="cascaron">
        <header class="barra">
            <NuxtLink to="/obras" class="marca">
                Control de Obras
            </NuxtLink>

            <nav v-if="usuario" class="enlaces">
                <NuxtLink to="/obras">Obras</NuxtLink>
                <NuxtLink to="/administracion">Administracion</NuxtLink>
            </nav>

            <div v-if="usuario" class="persona">
                <span>{{ usuario.nombre }}</span>
                <small>{{ usuario.cargo ?? usuario.rol }}</small>
                <button type="button" @click="cerrarSesion">Salir</button>
            </div>
        </header>

        <main class="contenido">
            <slot />
        </main>

        <footer class="pie">
            <!--
                El aviso legal va en TODAS las pantallas, no en una nota al
                pie de la documentacion: el sistema registra y ordena, y no
                reemplaza el libro de obra ni acredita cumplimiento normativo.
            -->
            <p>
                Este sistema registra y ordena. No reemplaza el libro de obra ni
                acredita cumplimiento normativo.
            </p>
        </footer>
    </div>
</template>

<script setup lang="ts">
const { usuario, cerrarSesion } = useSesion()
</script>

<style scoped>
.cascaron {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
    color: #1c1c1c;
    background: #f5f5f2;
}

.barra {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 0.75rem 1.25rem;
    background: #2b2b2b;
    color: #f5f5f2;
}

.marca {
    font-weight: 700;
    letter-spacing: 0.02em;
    text-decoration: none;
    color: #f5f5f2;
}

.enlaces {
    display: flex;
    gap: 1rem;
    flex: 1;
}

.enlaces a {
    color: #cfcfc7;
    text-decoration: none;
}

.enlaces a:hover {
    color: #ffffff;
}

.persona {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.persona small {
    opacity: 0.7;
}

.persona button {
    background: transparent;
    border: 1px solid #6a6a6a;
    color: #f5f5f2;
    border-radius: 4px;
    padding: 0.2rem 0.6rem;
    cursor: pointer;
}

.contenido {
    flex: 1;
    padding: 1.25rem;
    max-width: 1200px;
    width: 100%;
    margin: 0 auto;
}

.pie {
    border-top: 1px solid #d8d8d2;
    padding: 0.75rem 1.25rem;
    font-size: 0.8rem;
    color: #6a6a60;
}
</style>