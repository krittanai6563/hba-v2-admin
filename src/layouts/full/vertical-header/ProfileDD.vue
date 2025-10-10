<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import defaultAvatar from '@/assets/images/users/img-logo_0.png';  // ใช้ import แทน require

const router = useRouter();
const profileImageUrl = ref<string>(defaultAvatar);

function loadUserData() {
  try {
    const userData = sessionStorage.getItem('user') || localStorage.getItem('user');
    if (userData) {
      const user = JSON.parse(userData);
      profileImageUrl.value = user?.profile_image || defaultAvatar;
    } else {
      profileImageUrl.value = defaultAvatar;
    }
  } catch {
    profileImageUrl.value = defaultAvatar;
  }
}

onMounted(() => {
  loadUserData();
  window.addEventListener('storage', loadUserData);
});

onUnmounted(() => {
  window.removeEventListener('storage', loadUserData);
});

const logout = async () => {
  await fetch('https://uat.hba-sales.org/backend/logout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
  });
  localStorage.removeItem('user_id');
  sessionStorage.removeItem('user');
  router.push({ name: 'Login' });
};
</script>

<template>
    <!-- ---------------------------------------------- -->
    <!-- notifications DD -->
    <!-- ---------------------------------------------- -->
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
                <!-- <v-list-item value="item1" color="primary" >
                    <template v-slot:prepend>
                        <UserIcon stroke-width="1.5" size="20"/>
                    </template>
                    <v-list-item-title class="pl-4 text-body-1">My Profile</v-list-item-title>
                </v-list-item> -->
                <!-- <v-list-item value="item2" color="primary">
                    <template v-slot:prepend>
                        <MailIcon stroke-width="1.5" size="20"/>
                    </template>
                    <v-list-item-title  class="pl-4 text-body-1">My Account</v-list-item-title>
                </v-list-item> -->
                <!-- <v-list-item value="item3" color="primary"> 
                    <template v-slot:prepend>
                        <ListCheckIcon stroke-width="1.5"  size="20"/>
                    </template>
                    <v-list-item-title class="pl-4 text-body-1">My Task</v-list-item-title>
                </v-list-item> -->
            </v-list>
            <div class="pt-4 pb-4 px-5 text-center">
                <v-btn  @click="logout" color="primary" variant="outlined" block>Logout</v-btn>
            </div>
        </v-sheet>
    </v-menu>
</template>
