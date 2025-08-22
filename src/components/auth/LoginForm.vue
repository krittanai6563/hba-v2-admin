<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const checkbox = ref(true);

const email = ref(''); 
const password = ref('');
const errorMessage = ref(''); 

const router = useRouter();

const handleLogin = async () => {
  errorMessage.value = ''; 

  try {
const response = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/login.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email: email.value, password: password.value })
});

const raw = await response.text();
console.log('RAW Response:', raw);

const data = JSON.parse(raw); 

  if (data.message === 'Login successful') {
  sessionStorage.setItem('user', JSON.stringify({
    ...data.user,
    loginTime: Date.now()
  }));

  localStorage.setItem('user_id', data.user.id);
  localStorage.setItem('user_role', data.user.role); 
  await new Promise(resolve => setTimeout(resolve, 300));

  router.push({ name: 'Dashboard' });
}
else {
    
      if (data.message === 'Invalid username' || data.message === 'Invalid credentials') { 
        errorMessage.value = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
      } else if (data.message === 'Incorrect password') {
        errorMessage.value = 'รหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
      } else if (data.message === 'User not found') {
        errorMessage.value = 'ไม่พบชื่อผู้ใช้นี้ในระบบ';
      } else if (data.message === 'Invalid request') {
        errorMessage.value = 'คำขอไม่ถูกต้อง กรุณาตรวจสอบข้อมูลที่กรอก';
      }
      else {
        errorMessage.value = 'เข้าสู่ระบบไม่สำเร็จ: ' + data.message;
      }
    }
  } catch (error) {
    console.error('Error logging in:', error);
    errorMessage.value = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์ กรุณาลองใหม่อีกครั้ง';
  }
};

const isAdmin = (): boolean => {
  return localStorage.getItem('user_role') === 'admin';
};


</script>

<template>
  
  <form @submit.prevent="handleLogin" autocomplete="on">
   
    <v-row class="d-flex mb-3">
      <v-col cols="12">
        <v-label class="mb-1">Email</v-label>
        <v-text-field
  v-model="email"
  id="email"
  autocomplete="username"
  required
  variant="outlined"
  hide-details
  color="primary"
  type="email"
/>
      </v-col>
      <v-col cols="12">
        <v-label class="mb-1">Password</v-label>
      <v-text-field
  v-model="password"
  id="password"
  autocomplete="current-password"
  variant="outlined"
  type="password"
  hide-details
  color="primary"
/>

      </v-col>
      <v-col cols="12" class="pt-0">
        <div class="d-flex flex-wrap align-center ml-n2">
          <v-checkbox v-model="checkbox" color="primary" hide-details>
            <template v-slot:label class="text-body-1">จำรหัสผ่านฉันไว้</template>
          </v-checkbox>
          <div class="ml-sm-auto">
            <RouterLink to="/"
              class="text-primary text-decoration-none text-body-1 opacity-1 font-weight-medium">ลืมรหัสผ่าน</RouterLink>
          </div>
        </div>
      </v-col>
      <v-col cols="12" class="pt-0">
        <v-btn type="submit" color="primary" size="large" block flat  class="w-100">เข้าสู่ระบบ</v-btn>
        <p v-if="errorMessage" class="error mt-2">{{ errorMessage }}</p>
      </v-col>
    </v-row>
  </form>
</template>


<style>
.error {
  color: red;
  font-size: 0.875rem; 
}
</style>
