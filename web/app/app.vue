<template>
    <NuxtLayout>
        <NuxtPage />
    </NuxtLayout>
</template>

<script setup lang="ts">
// La sesión se pide una vez al arrancar, en el servidor, para que la primera
// pantalla ya sepa si hay que pedir el inicio de sesión o no.
const { cargar } = useSesion()
await cargar()
</script>