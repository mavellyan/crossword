<script setup>
import { ref } from 'vue'

const name = ref('')
const email = ref('')
const password = ref('')

function onSubmit() {
  alert(`Register: ${name.value}`)
}
</script>

<template>
  <h1>Regisztráció</h1>
  <form @submit.prevent="onSubmit" style="display:grid; gap:12px;">
    <label>Név <input v-model="name" required /></label>
    <label>Email <input v-model="email" type="email" required /></label>
    <label>Jelszó <input v-model="password" type="password" required /></label>
    <button type="submit">Regisztráció</button>
  </form>
</template>
