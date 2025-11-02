<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Logo from '@/layouts/full/logo/Logo.vue';

const route = useRoute();
const router = useRouter();

const token = ref('');
const password = ref('');
const confirmPassword = ref('');
const loading = ref(false);

// Refs สำหรับ VAlert
const alertShow = ref(false);
const alertType = ref<'success' | 'error'>('error');
const alertText = ref('');

// เมื่อหน้าโหลด ให้ดึง token จาก URL
onMounted(() => {
  const urlToken = route.query.token as string;
  if (!urlToken) {
    // ถ้าไม่มี Token ใน URL
    alertText.value = 'ข้อมูลไม่ถูกต้อง กรุณากดลิงก์ในอีเมลอีกครั้ง';
    alertType.value = 'error';
    alertShow.value = true;
    
    // พาผู้ใช้กลับหน้า Login หลังจาก 3 วินาที
    setTimeout(() => {
        router.push('/auth/login');
    }, 3000);

  } else {
    // ถ้ามี Token ให้เก็บไว้ใน ref
    token.value = urlToken;
  }
});

// ฟังก์ชันเมื่อกดยืนยันเปลี่ยนรหัสผ่าน
const handleResetPassword = async () => {
  alertShow.value = false; // ซ่อน Alert เก่าก่อน

  // 1. ตรวจสอบรหัสผ่านตรงกัน (ฝั่ง Frontend)
  if (password.value !== confirmPassword.value) {
    alertText.value = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    alertType.value = 'error';
    alertShow.value = true;
    return;
  }
  if (!token.value) {
    alertText.value = 'ข้อมูลไม่ถูกต้อง';
    alertType.value = 'error';
    alertShow.value = true;
    return;
  }

  loading.value = true;

  try {
    // 2. ส่ง Token และรหัสผ่านใหม่ไปให้ PHP
    const response = await fetch('https://uat.hba-sales.org/backend/reset_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        token: token.value,
        password: password.value,
        confirmPassword: confirmPassword.value
      })
    });

    const data = await response.json();

    // 3. จัดการผลลัพธ์
    if (data.status === 'success') {
      // (สำเร็จ)
      alertText.value = 'ตั้งรหัสผ่านใหม่สำเร็จ กำลังนำคุณกลับไปหน้า Login...';
      alertType.value = 'success';
      alertShow.value = true;

      // กลับไปหน้า Login หลังจาก 3 วินาที
      setTimeout(() => {
        router.push('/auth/login');
      }, 3000);

    } else {
      // (ล้มเหลว เช่น Token หมดอายุ)
      alertText.value = data.message || 'ไม่สามารถรีเซ็ตรหัสผ่านได้ กรุณาเช็คข้อมูลอีกครั้ง';
      alertType.value = 'error';
      alertShow.value = true;
    }
  } catch (error) {
    console.error('Error resetting password:', error);
    alertText.value = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์';
    alertType.value = 'error';
    alertShow.value = true;
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  
    <v-row class="d-flex mb-3">
      <v-col cols="12">
       
            <v-alert
              v-model="alertShow"
              :type="alertType"
              closable
              class="mb-4"
              density="comfortable"
            >
              {{ alertText }}
            </v-alert>

            <form @submit.prevent="handleResetPassword">
              <v-row>
                <v-col cols="12">
                  <v-label class="mb-1">รหัสผ่านใหม่ (New Password)</v-label>
                  <v-text-field
                    v-model="password"
                    required
                    variant="outlined"
                    type="password"
                    color="primary"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12">
                  <v-label class="mb-1">ยืนยันรหัสผ่านใหม่ (Confirm Password)</v-label>
                  <v-text-field
                    v-model="confirmPassword"
                    required
                    variant="outlined"
                    type="password"
                    color="primary"
                    hide-details="auto"
                  />
                </v-col>
                <v-col cols="12">
                  <v-btn color="primary" size="large" block flat class="w-100"
                    type="submit"
                    :loading="loading"
                    :disabled="loading || (alertShow && alertType === 'success')"
                  >
                    ยืนยันการตั้งรหัสผ่านใหม่
                  </v-btn>
                </v-col>
              </v-row>
            </form>
            
            <div class="text-center mt-4">
              <RouterLink
                to="/auth/login"
                class="text-primary text-decoration-none text-body-1 opacity-1 font-weight-medium"
              >
                กลับไปหน้า Login
              </RouterLink>
            </div>

         
       
      </v-col>
    </v-row>
 
</template>