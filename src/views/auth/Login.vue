<script setup lang="ts">
import { onMounted, ref } from 'vue';
// ⭐️ NEW: Import useRouter เพิ่มเข้ามาด้วย
import { useRoute, useRouter } from 'vue-router'; 

import Logo from '@/layouts/full/logo/DarkLogo.vue';
/* Login form */
import LoginForm from '@/components/auth/LoginForm.vue';

// ⭐️ State สำหรับ Snackbar ในหน้า Login
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const route = useRoute(); 
const router = useRouter(); // ⭐️ NEW: เรียกใช้ useRouter

onMounted(() => {
    // ⭐️ NEW: คัดลอก Query Parameter เพื่อเตรียมลบออก
    const query = { ...route.query }; 
    let shouldUpdateRoute = false;

    // ⭐️ โค้ดตรวจสอบ Query Parameter 'loggedOut' (โค้ดเดิม)
    if (route.query.loggedOut === 'success') {
        snackbarText.value = 'ออกจากระบบสำเร็จ!';
        snackbarColor.value = 'success';
        snackbar.value = true;
        
        // ลบ query 'loggedOut' ออก
        delete query.loggedOut;
        shouldUpdateRoute = true;
    }
    
    // ⭐️ NEW: โค้ดตรวจสอบ Query Parameter 'loggedIn'
    if (route.query.loggedIn === 'success') {
        snackbarText.value = 'เข้าสู่ระบบสำเร็จ!';
        snackbarColor.value = 'success';
        snackbar.value = true;
        
        // ลบ query 'loggedIn' ออก
        delete query.loggedIn;
        shouldUpdateRoute = true;
    }

    // ลบ query parameter ออกจาก URL (เช่น /auth/login?loggedIn=success จะกลายเป็น /auth/login)
    if (shouldUpdateRoute) {
        router.replace({ query });
    }
});
</script>

<template>
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="top right">
        {{ snackbarText }}
    </v-snackbar>
    
    <div class="authentication">
        <v-container fluid class="pa-3">
            <v-row class="h-screen d-flex justify-center align-center">
                <v-col cols="12" class="d-flex align-center">
                    <v-card rounded="md" elevation="10" class="px-sm-1 px-0 mx-auto" max-width="450">
                        <v-card-item class="pa-sm-8">
                            <div class="d-flex justify-center py-4 mb-3"><Logo /></div>
                            <LoginForm />
                            </v-card-item>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </div>
</template>