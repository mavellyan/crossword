<script setup>
import { ref } from 'vue'

const email = ref('')
const password = ref('')

function onSubmit() {
  alert(`Login: ${email.value}`)
}
</script>

<template>
  <h1>Belépés</h1>
  <form @submit.prevent="onSubmit" style="display:grid; gap:12px;">
    <label>Email <input v-model="email" type="email" required /></label>
    <label>Jelszó <input v-model="password" type="password" required /></label>
    <button type="submit">Belépés</button>
  </form>
</template>
