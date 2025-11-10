<script setup lang="ts">
// ⭐️ [จุดที่ 1] เพิ่ม 'computed' เข้ามาใน import
import { onMounted, onUnmounted, ref, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import defaultAvatar from '@/assets/images/users/img-logo_0.png';  

const router = useRouter();
const profileImageUrl = ref<string>(defaultAvatar);

// --- State สำหรับ Dialogs ---
const profileDialog = ref(false);
const passwordDialog = ref(false);
const confirmChangeDialog = ref(false);

// --- State สำหรับ Snackbar ---
const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

// ⭐️ [จุดที่ 2] เพิ่ม ref สำหรับ VForm
const passwordForm = ref<any>(null); // สำหรับควบคุมการ validate

// --- State สำหรับ Form Fields ---
const userId = ref<string | null>(null);
const fullname = ref('');
const email = ref('');
// (สำหรับ Popup 1)
const selectedFile = ref<File | null>(null);
const imagePreviewUrl = ref<string | null>(null);
// (สำหรับ Popup 2)
const oldPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const showPass = ref(false);

// ⭐️ [จุดที่ 3] เพิ่ม Rules สำหรับฟอร์มรหัสผ่าน
const oldPasswordRules = [
    (v: string) => !!v || 'กรุณากรอกรหัสผ่านเดิม'
];
const newPasswordRules = [
    (v: string) => !!v || 'กรุณากรอกรหัสผ่านใหม่',
    (v: string) => (v && v.length >= 8) || 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร'
];
// ใช้ computed เพื่อให้ rule นี้อัปเดตตาม newPassword.value
const confirmPasswordRules = computed(() => [
    (v: string) => !!v || 'กรุณายืนยันรหัสผ่าน',
    (v: string) => v === newPassword.value || 'รหัสผ่านใหม่ไม่ตรงกัน'
]);


function loadUserData() {
  try {
    const userData = sessionStorage.getItem('user') || localStorage.getItem('user');
    if (userData) {
      const user = JSON.parse(userData);
      profileImageUrl.value = user?.profile_image || defaultAvatar;
      fullname.value = user?.fullname || '';
      email.value = user?.email || '';
      userId.value = user?.id || localStorage.getItem('user_id');
    } else {
      profileImageUrl.value = defaultAvatar;
    }
  } catch {
    profileImageUrl.value = defaultAvatar;
  }
}

watch(selectedFile, (newFile) => {
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }
  if (newFile) {
    imagePreviewUrl.value = URL.createObjectURL(newFile);
  } else {
    imagePreviewUrl.value = null;
  }
});

watch(passwordDialog, (newValue) => {
    if (newValue === false) {
        oldPassword.value = ''; 
        newPassword.value = '';
        confirmPassword.value = '';
        showPass.value = false;
        confirmChangeDialog.value = false;
        passwordForm.value?.resetValidation(); // ⭐️ [แก้ไข] ล้าง error เมื่อปิด
    }
});

watch(profileDialog, (newValue) => {
    if (newValue === false) {
        selectedFile.value = null;
    }
});


onMounted(() => {
  loadUserData();
  window.addEventListener('storage', loadUserData);
});

onUnmounted(() => {
  window.removeEventListener('storage', loadUserData);
  if (imagePreviewUrl.value) {
    URL.revokeObjectURL(imagePreviewUrl.value);
  }
});

const logout = async () => {
  await fetch('https://uat.hba-sales.org/backend/logout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
  });
  localStorage.removeItem('user_id');
  localStorage.removeItem('user');
  sessionStorage.removeItem('user');
  router.push({ name: 'Login' });
};

async function handleProfileUpdate() {
    // (ฟังก์ชันนี้เหมือนเดิม ไม่มีการเปลี่ยนแปลง)
    if (!userId.value) return;
    try {
        const formData = new FormData();
        formData.append('id', userId.value);
        formData.append('fullname', fullname.value);
        formData.append('email', email.value);
        if (selectedFile.value) {
            formData.append('profile_image', selectedFile.value);
        } else {
            profileDialog.value = false;
            return;
        }
        const res = await fetch('https://uat.hba-sales.org/backend/edit_profile.php', {
            method: 'POST',
            body: formData
        });
        const text = await res.text();
        if (!res.ok) throw new Error(text || 'Update failed');
        const data = JSON.parse(text);
        snackbarText.value = data.message || 'อัปเดตรูปโปรไฟล์สำเร็จ!';
        snackbarColor.value = 'success';
        snackbar.value = true;
        profileDialog.value = false;
        if (data.user) {
            const updatedUser = JSON.stringify(data.user);
            sessionStorage.setItem('user', updatedUser); 
            localStorage.setItem('user', updatedUser);   
            loadUserData();
        } else if (data.profile_image_url) {
            const oldUserData = JSON.parse(sessionStorage.getItem('user') || localStorage.getItem('user') || '{}');
            oldUserData.profile_image = data.profile_image_url;
            sessionStorage.setItem('user', JSON.stringify(oldUserData));
            localStorage.setItem('user', JSON.stringify(oldUserData));
            loadUserData();
        } else {
            loadUserData();
        }
    } catch (error: any) {
        snackbarText.value = 'อัปเดตล้มเหลว: ' + error.message;
        snackbarColor.value = 'error';
        snackbar.value = true;
    }
}

// ⭐️ [จุดที่ 4] แก้ไขฟังก์ชันนี้ให้ใช้การ validate()
async function promptChangePassword() {
    if (!passwordForm.value) return; 

    // 1. ตรวจสอบฟอร์มทั้งหมด (รหัสเดิม, รหัสใหม่, ยืนยัน)
    const { valid } = await passwordForm.value.validate();

    // 2. ถ้าฟอร์ม (รวม rules ทั้งหมด) ผ่าน
    if (valid) {
        // ค่อยเปิดหน้าต่างยืนยัน
        confirmChangeDialog.value = true;
    }
    // ถ้าไม่ผ่าน (invalid), Vuetify จะแสดง error message ใต้ช่องอัตโนมัติ
    // (เราไม่จำเป็นต้องใช้ snackbar แจ้งเตือน error อีกต่อไป)
}


async function handleChangePassword() {
    // (ฟังก์ชันนี้เหมือนเดิม ไม่มีการเปลี่ยนแปลง)
    if (!userId.value) return;
    confirmChangeDialog.value = false;
    try {
        const formData = new FormData();
        formData.append('id', userId.value);
        formData.append('fullname', fullname.value);
        formData.append('email', email.value);
        formData.append('old_password', oldPassword.value);
        formData.append('password', newPassword.value);
        const res = await fetch('https://uat.hba-sales.org/backend/edit_profile.php', {
            method: 'POST',
            body: formData
        });
        const text = await res.text();
        if (!res.ok) {
            try {
                const data = JSON.parse(text);
                throw new Error(data.message || 'Update failed');
            } catch {
                throw new Error(text || 'Update failed');
            }
        }
        const data = JSON.parse(text);
        snackbarText.value = data.message || 'เปลี่ยนรหัสผ่านสำเร็จ!';
        snackbarColor.value = 'success';
        snackbar.value = true;
        passwordDialog.value = false;
    } catch (error: any) {
        snackbarText.value = 'อัปเดตล้มเหลว: ' + error.message;
        snackbarColor.value = 'error';
        snackbar.value = true;
    }
}
</script>

<template>
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="top right">
        {{ snackbarText }}
    </v-snackbar>

    <v-dialog v-model="profileDialog" max-width="600">
        <template v-slot:default="{ isActive }">
            <v-card rounded="lg">
                <v-card-title class="d-flex justify-space-between align-center">
                    <span>ข้อมูลโปรไฟล์</span>
                    <v-btn icon="mdi-close" variant="text" @click="isActive.value = false"></v-btn>
                </v-card-title>
                <v-divider></v-divider>
                
                <v-card-text>
                    <v-row>
                        <v-col cols="12" sm="6">
                            <v-label>ชื่อบริษัท</v-label>
                            <v-text-field v-model="fullname" readonly density="compact" variant="outlined"></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6">
                            <v-label>อีเมล</v-label>
                            <v-text-field v-model="email" readonly density="compact" variant="outlined"></v-text-field>
                        </v-col>
                        <v-divider class="my-2"></v-divider>
                        <v-col cols="12" class="d-flex justify-center align-center py-0">
                            <v-avatar size="100" rounded="lg" color="grey-lighten-3">
                                <v-img 
                                    :src="imagePreviewUrl || profileImageUrl || defaultAvatar" 
                                    alt="Profile Preview"
                                    cover
                                ></v-img>
                            </v-avatar>
                        </v-col>
                        <v-col cols="12">
                            <v-label>อัพโหลดรูปโปรไฟล์ใหม่</v-label>
                            <v-file-input v-model="selectedFile" label="เลือกไฟล์..." accept="image/*" density="compact" variant="outlined"></v-file-input>
                        </v-col>
                    </v-row>
                </v-card-text>

                <v-divider></v-divider>
                <v-card-actions class="pa-4">
                    <v-spacer></v-spacer>
                    <v-btn color="grey" variant="text" @click="isActive.value = false">ยกเลิก</v-btn>
                    <v-btn color="primary" variant="flat" @click="handleProfileUpdate">บันทึก</v-btn>
                </v-card-actions>
            </v-card>
        </template>
    </v-dialog>

    <v-dialog v-model="passwordDialog" max-width="500" persistent>
         <template v-slot:default="{ isActive }">
            <v-card rounded="lg">
                <v-card-title class="d-flex justify-space-between align-center">
                    <span>เปลี่ยนรหัสผ่าน</span>
                    <v-btn icon="mdi-close" variant="text" @click="isActive.value = false"></v-btn>
                </v-card-title>
                <v-divider></v-divider>
                
                <v-card-text>
                    <v-form ref="passwordForm">
                        <v-row>
                            <v-col cols="12">
                                <v-label>รหัสผ่านเดิม</v-label>
                                <v-text-field 
                                    v-model="oldPassword"
                                    :rules="oldPasswordRules" :type="showPass ? 'text' : 'password'"
                                    :append-inner-icon="showPass ? 'mdi-eye' : 'mdi-eye-off'"
                                    @click:append-inner="showPass = !showPass"
                                    density="compact" 
                                    variant="outlined"
                                ></v-text-field>
                            </v-col>

                            <v-col cols="12">
                                <v-label>รหัสผ่านใหม่</v-label>
                                <v-text-field 
                                    v-model="newPassword"
                                    :rules="newPasswordRules" :type="showPass ? 'text' : 'password'"
                                    :append-inner-icon="showPass ? 'mdi-eye' : 'mdi-eye-off'"
                                    @click:append-inner="showPass = !showPass"
                                    density="compact" 
                                    variant="outlined"
                                ></v-text-field>
                            </v-col>
                            <v-col cols="12">
                                <v-label>ยืนยันรหัสผ่านใหม่</v-label>
                                <v-text-field 
                                    v-model="confirmPassword"
                                    :rules="confirmPasswordRules" :type="showPass ? 'text' : 'password'"
                                    density="compact" 
                                    variant="outlined"
                                ></v-text-field>
                            </v-col>
                        </v-row>
                    </v-form>
                    </v-card-text>

                <v-divider></v-divider>
                <v-card-actions class="pa-4">

                    <v-dialog v-model="confirmChangeDialog" max-width="400" persistent>
                        <v-card>
                            <v-card-title class="text-h6">
                                ยืนยันการเปลี่ยนแปลง
                            </v-card-title>
                            <v-card-text>
                                คุณแน่ใจที่จะเปลี่ยนรหัสผ่านใช่หรือไม่?
                            </v-card-text>
                            <v-card-actions>
                                <v-spacer></v-spacer>
                                <v-btn color="grey" text @click="confirmChangeDialog = false">
                                    ยกเลิก
                                </v-btn>
                                <v-btn color="primary" variant="flat" @click="handleChangePassword">
                                    ยืนยัน
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-dialog>

                    <v-spacer></v-spacer>
                    <v-btn color="grey" variant="text" @click="isActive.value = false">ยกเลิก</v-btn>
                    
                    <v-btn color="primary" variant="flat" @click="promptChangePassword">
                        บันทึก
                    </v-btn>
                </v-card-actions>
            </v-card>
        </template>
    </v-dialog>


    <v-menu :close-on-content-click="false">
        <template v-slot:activator="{ props }">
            <v-btn class=" custom-hover-primary" rounded="pill" variant="text" v-bind="props" icon>
                <v-avatar size="35">
                   <img :src="profileImageUrl" height="35" alt="user" />
                </v-avatar>
            </v-btn>
        </template>
        <v-sheet rounded="md" width="200" elevation="10" class="mt-2">
            
            <v-list class="py-0" lines="one" density="compact">
                <v-list-item @click="profileDialog = true" title="ข้อมูลโปรไฟล์" class="py-2">
                     <template v-slot:prepend>
                        <v-icon icon="mdi-account-circle-outline" size="small" class="mr-3"></v-icon>
                     </template>
                </v-list-item>
                
                <v-list-item @click="passwordDialog = true" title="เปลี่ยนรหัสผ่าน" class="py-2">
                     <template v-slot:prepend>
                        <v-icon icon="mdi-lock-outline" size="small" class="mr-3"></v-icon>
                     </template>
                </v-list-item>
            </v-list>

            <v-divider></v-divider>

            <div class="pt-4 pb-4 px-5">
                <v-btn  @click="logout" color="primary" variant="outlined" block style="width: 100%;">Logout</v-btn>
            </div>
        </v-sheet>
    </v-menu>
</template>