<script setup lang="ts">
import { ref } from 'vue';


const fullname = ref('');
const email = ref('');
const password = ref('');
const message = ref('');
const showMessageBox = ref(false);

async function register() {
 
  message.value = '';
  showMessageBox.value = false;

  try {

    const res = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }, 
      body: JSON.stringify({ 
        fullname: fullname.value,
        email: email.value,
        password: password.value
      })
    });

  
    let data;
    const contentType = res.headers.get('content-type');

    if (contentType && contentType.includes('application/json')) {
      data = await res.json(); 
    } else {
    
      data = { message: await res.text() };
    
      if (res.ok) {
          data.message = "Warning: Backend did not return JSON. Raw response: " + data.message;
      }
    }
    if (res.ok) {
      message.value = data.message || 'Registration successful!'; 
      clearForm(); 
    } else {

      message.value = data.message || 'Registration failed: An unknown error occurred.';
    }
  } catch (error: unknown) { 
   
    if (error instanceof Error) {
      message.value = 'Registration failed: ' + error.message;
    } else {
      message.value = 'Registration failed: An unexpected error occurred.';
    }
  } finally {
 
    showMessageBox.value = true;
  }
}

function clearForm() {
  fullname.value = '';
  email.value = '';
  password.value = '';
}

function closeMessageBox() {
  showMessageBox.value = false;
  message.value = ''; 
}
</script>


<template>
  <form @submit.prevent="register">
    <v-row class="d-flex mb-3">
      <v-col cols="12">
        <v-label class="mb-1">ชื่อบริษัท</v-label>
        <v-text-field id="fullname"v-model="fullname" variant="outlined" hide-details color="primary" ></v-text-field>
      </v-col>
      <v-col cols="12">
        <v-label class="mb-1">อีเมล</v-label>
        <v-text-field  variant="outlined" type="email" hide-details color="primary" id="email"
            v-model="email"></v-text-field>
      </v-col>
      <v-col cols="12">
        <v-label class="mb-1">รหัสผ่าน</v-label>
        <v-text-field  id="password"v-model="password" variant="outlined" type="password" hide-details color="primary"></v-text-field>
      </v-col>
      <v-col cols="12">
        <v-btn type="submit" color="primary" size="large" block flat class="w-100">สมัครสมาชิก</v-btn>
      </v-col>
    </v-row>
  </form>
  <p v-if="message" class="mt-3">{{ message }}</p>
</template>

<style scoped>
.container {
  max-width: 600px;
  margin: auto;
}
p {
  color: red; 
  text-align: center;
}
</style>
