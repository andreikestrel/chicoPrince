<script setup lang="ts">
import ApiStatus from './components/ApiStatus.vue'
</script>

<template>
  <main>
    <header>
      <a class="brand" href="/" aria-label="Chico Prince, início">CHICO PRINCE<span>®</span></a>
      <span class="tag">Em construção</span>
    </header>
    <section class="intro" aria-labelledby="title">
      <p class="eyebrow">O começo de uma boa história</p>
      <h1 id="title">Nosso catálogo<br />está a caminho.</h1>
      <p class="description">Estamos preparando um espaço para você descobrir seus próximos favoritos.</p>
    </section>
    <ApiStatus />
    <footer>Chico Prince · Ambiente de desenvolvimento</footer>
  </main>
</template>
