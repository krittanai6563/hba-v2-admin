<script setup lang="ts">
import { onMounted, ref } from 'vue'; // ⭐️ NEW: Import ref และ onMounted
import { useRoute } from 'vue-router'; // ⭐️ NEW: Import useRoute เพื่ออ่าน Query Parameter

import Logo from '@/layouts/full/logo/DarkLogo.vue';
/* Login form */
import LoginForm from '@/components/auth/LoginForm.vue';

// ⭐️ NEW: State สำหรับ Snackbar ในหน้า Login
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const route = useRoute(); // ⭐️ NEW: เรียกใช้ useRoute

onMounted(() => {
    // ⭐️ โค้ดตรวจสอบ Query Parameter 'loggedOut'
    if (route.query.loggedOut === 'success') {
        snackbarText.value = 'ออกจากระบบสำเร็จ!';
        snackbarColor.value = 'success';
        snackbar.value = true;
        
        // **[หมายเหตุ]:** คุณอาจต้องการลบ query parameter ออกจาก URL หลังจากแสดงแล้ว
        // หากต้องการลบ query ออก ให้ใช้โค้ดดังนี้ (ต้อง import useRouter ด้วย):
        // 
        // import { useRoute, useRouter } from 'vue-router'; 
        // const router = useRouter(); 
        // const query = { ...route.query };
        // delete query.loggedOut;
        // router.replace({ query }); 
        
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