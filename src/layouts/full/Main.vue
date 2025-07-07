<script setup lang="ts">
import { onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';

import { getSidebarItems, type Menu } from './vertical-sidebar/sidebarItem';
import NavGroup from './vertical-sidebar/NavGroup/index.vue';
import NavItem from './vertical-sidebar/NavItem/index.vue';
import NavCollapse from './vertical-sidebar/NavCollapse/NavCollapse.vue';
import { Icon } from '@iconify/vue';
import Logo from './logo/Logo.vue';

import ProfileDD from './vertical-header/ProfileDD.vue';
import SidebarProfile from './vertical-header/SidebarProfile.vue';
import { useDisplay } from 'vuetify/lib/framework.mjs';

import defaultAvatar from '@/assets/images/users/img-logo_0.png';

const user = ref<{ fullname: string; role: string; profile_image?: string } | null>(null);
const profileImageUrl = ref<string>(defaultAvatar);
const fullname = ref('Guest');
const sidebarMenu = shallowRef<Menu[]>([]);

// ใช้ useDisplay เพื่อเข้าถึง break point ของ Vuetify
const { mdAndDown } = useDisplay();

const sDrawer = ref(!mdAndDown.value);

function loadUserData() {
    try {
        const userData = sessionStorage.getItem('user') || localStorage.getItem('user');
        if (userData) {
            const parsedUser = JSON.parse(userData);
            user.value = parsedUser;

            fullname.value = parsedUser?.fullname || 'Guest';

            if (parsedUser?.profile_image && parsedUser.profile_image.trim() !== '') {
                const pi = decodeURIComponent(parsedUser.profile_image);
                if (/^https?:\/\//.test(pi)) {
                    profileImageUrl.value = pi;
                } else {
                    profileImageUrl.value = 'https://c44d-2405-9800-b861-96e-d38-cc71-74cd-d0c1.ngrok-free.app/package/backend/' + pi;
                }
            } else {
                profileImageUrl.value = defaultAvatar;
            }

            sidebarMenu.value = getSidebarItems(parsedUser?.role || 'user');
        } else {
            user.value = null;
            fullname.value = 'Guest';
            profileImageUrl.value = defaultAvatar;
            sidebarMenu.value = getSidebarItems('user');
        }
    } catch (error) {
        console.error(error);
        user.value = null;
        fullname.value = 'Guest';
        profileImageUrl.value = defaultAvatar;
        sidebarMenu.value = getSidebarItems('user');
    }
}

onMounted(() => {
    loadUserData();
    window.addEventListener('storage', loadUserData);
});

onUnmounted(() => {
    window.removeEventListener('storage', loadUserData);
});

watch(mdAndDown, (val) => {
    sDrawer.value = !val; 

});
</script>

<template>
  <v-app-bar elevation="0" height="68" class="top bg-primary px-6">
    <div class="d-flex align-center justify-space-between w-100">
      <div class="d-flex ga-lg-16 ga-3 align-center">
        <Logo class="d-sm-flex d-none" />
        <v-btn class="hidden-lg-and-up" @click="sDrawer = !sDrawer" rounded="pill" height="40" width="40">
          <Icon icon="solar:list-bold" height="22" />
        </v-btn>
      </div>
      <div>
        <v-btn class="mr-2 bg-success" rounded="pill" href="#" target="_blank">
          <Icon icon="solar:home-2-linear" height="20" width="20" class="mr-2" /> กลับสู่หน้าหลัก
        </v-btn>
        <ProfileDD />
      </div>
    </div>
  </v-app-bar>

  <v-main>
 <v-navigation-drawer 
  left 
  elevation="0" 
  class="leftSidebar top-header" 
  :width="300" 
  v-model="sDrawer" 
  :temporary="mdAndDown"  
  :permanent="!mdAndDown" 
  :app="true" 
>
  <perfect-scrollbar class="scrollnavbar">
    <div class="profile">
      <div class="profile-img py-10 px-3 d-flex align-center">
        <v-avatar size="50">
          <img :src="profileImageUrl" width="50" alt="User Profile"  style="object-fit: contain; border-radius: 50%;" />
        </v-avatar>
      </div>
      <div class="profile-name d-flex align-center px-3">
        <SidebarProfile />
      </div>
    </div>

    <v-list class="px-4 py-2">
      <template v-for="(item, i) in sidebarMenu" :key="i">
        <NavGroup v-if="item.header" :item="item" :key="item.title" />
        <NavCollapse v-else-if="item.children" class="leftPadding" :item="item" :level="0" />
        <NavItem v-else class="leftPadding" :item="item" />
      </template>
    </v-list>
  </perfect-scrollbar>
</v-navigation-drawer>



    <v-container class="page-wrapper">
      <div class="maxWidth">
        <RouterView />
      </div>
    </v-container>
  </v-main>
</template>