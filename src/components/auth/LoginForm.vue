<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAlertStore } from '@/stores/alert';

const checkbox = ref(true);
const email = ref(''); 
const password = ref('');
const errorMessage = ref(''); 
const router = useRouter();
const alertStore = useAlertStore();

// --- (ส่วนที่เพิ่ม 1: สำหรับปุ่มแสดงรหัสผ่าน) ---
const showPassword = ref(false);
// --- (จบส่วนที่เพิ่ม 1) ---

const mainAlert = ref(false);
const mainAlertType = ref<'success' | 'error'>('success');
const mainAlertText = ref('');

const forgotDialog = ref(false);
const email_reset = ref('');
const loading_reset = ref(false);
const message_reset = ref(''); 


const handleLogin = async () => {
  mainAlert.value = false;
  errorMessage.value = ''; 
  try {
    const response = await fetch('https://uat.hba-sales.org/backend/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: email.value, password: password.value })
    });
    const raw = await response.text();
    console.log('RAW Response:', raw);
    const data = JSON.parse(raw); 
    if (data.message === 'Login successful') {
      sessionStorage.setItem('user', JSON.stringify({ ...data.user, loginTime: Date.now() }));
      localStorage.setItem('user_id', data.user.id);
      localStorage.setItem('user_role', data.user.role); 
      
      // (ส่วนที่ 1: สั่งยิง ALERT)
      alertStore.showAlert('เข้าสู่ระบบสำเร็จ!', 'success');

      await new Promise(resolve => setTimeout(resolve, 300));

      // (ส่วนที่ 2: Logic การ Redirect)
      const userRole = data.user.role;
      const currentDate = new Date().getDate();

      if (userRole === 'admin' && currentDate < 11) {
        // ถ้าเป็น admin และ ก่อนวันที่ 11 ให้เด้งไปหน้า 'User' (/ui/user)
        router.push({ name: 'User' }); 
      } else {
        // กรณีอื่นๆ ทั้งหมด (admin หลังวันที่ 11, master, user) ให้เด้งไปหน้า 'Dashboard' (/)
        router.push({ name: 'Dashboard' });
      }

    } else {
      
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



const openForgotDialog = () => {
  mainAlert.value = false;
  message_reset.value = '';
  email_reset.value = email.value; 
  forgotDialog.value = true;
};


const handleRequestReset = async () => {
  loading_reset.value = true;
  message_reset.value = ''; 

  try {
    const response = await fetch('https://uat.hba-sales.org/backend/request_password_reset.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
    
      body: JSON.stringify({ email: email_reset.value })
    });
    
    const data = await response.json();

    if (data.status === 'success') {
      forgotDialog.value = false; 
      
      mainAlertType.value = 'success';
      mainAlertText.value = 'เราได้ส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปที่อีเมลของคุณแล้ว';
      mainAlert.value = true;

    } else {
      message_reset.value = data.message || 'ไม่สามารถดำเนินการได้';
    }
  } catch (error) {
    console.error('Error requesting reset:', error);
    message_reset.value = 'เกิดข้อผิดพลาดในการเชื่อมต่อ (อาจเกิดจาก Debugging)';
  } finally {
    loading_reset.value = false;
  }
};
</script>

<template>
  
  <v-alert
    v-model="mainAlert"
    :type="mainAlertType"
    closable
    class="mb-4"
    density="comfortable"
  >
    {{ mainAlertText }}
  </v-alert>

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
          :type="showPassword ? 'text' : 'password'"
          hide-details
          color="primary"
          :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
          @click:append-inner="showPassword = !showPassword"
        />
        </v-col>
      <v-col cols="12" class="pt-0">
        <div class="d-flex flex-wrap align-center ml-n2">
          <v-checkbox v-model="checkbox" color="primary" hide-details>
            <template v-slot:label class="text-body-1">จำรหัสผ่านฉันไว้</template>
          </v-checkbox>
          <div class="ml-sm-auto">
            <a 
              href="#"
              @click.prevent="openForgotDialog"
              class="text-primary text-decoration-none text-body-1 opacity-1 font-weight-medium"
            >
              ลืมรหัสผ่าน
            </a>
          </div>
        </div>
      </v-col>
      <v-col cols="12" class="pt-0">
        <v-btn type="submit" color="primary" size="large" block flat class="w-100">เข้าสู่ระบบ</v-btn>
        <p v-if="errorMessage" class="error mt-2">{{ errorMessage }}</p>
      </v-col>
    </v-row>
  </form>

  <v-dialog v-model="forgotDialog" max-width="500px">
    <v-card>
      <v-card-title class="pa-4">
        <span class="text-h5">ลืมรหัสผ่าน</span>
      </v-card-title>

      <v-card-text class="pa-4">
        <p class="mb-6">
          กรุณากรอกอีเมลที่ใช้ลงทะเบียน
          เราจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้
        </p>
        <v-form @submit.prevent="handleRequestReset">
          <v-row>
            <v-col cols="12">
              <v-label class="mb-1">Email</v-label>
              <v-text-field
                v-model="email_reset"
                required
                variant="outlined"
                type="email"
                color="primary"
                hide-details="auto"
                autofocus
              />
            </v-col>
          </v-row>

          <v-alert
            v-if="message_reset"
            type="error"
            density="compact"
            class="mt-4"
          >
            {{ message_reset }}
          </v-alert>

        </v-form>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn 
          color="grey-darken-1" 
          variant="text" 
          @click="forgotDialog = false"
        >
          ยกเลิก
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          :loading="loading_reset"
          :disabled="loading_reset"
          @click="handleRequestReset"
        >
          ส่งคำขอ
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

</template>


<style>
.error {
  color: red;
  font-size: 0.875rem; 
}
</style>