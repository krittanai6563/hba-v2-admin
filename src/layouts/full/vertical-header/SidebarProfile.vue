<script setup lang="ts">

import { ref, onMounted, onUnmounted } from 'vue';



const fullname = ref<string>('Guest'); 


const loadUserData = () => {
  try {
    const userData = sessionStorage.getItem('user');
    if (userData) {

      const user: { fullname?: string } = JSON.parse(userData);
      if (user && user.fullname ) {
        fullname.value = `${user.fullname} `;
      } else {
        fullname.value = 'Guest';
      }
    }
  } catch (error) {
    console.error('Error parsing user data:', error);
    fullname.value = 'Guest';
  }
};


onMounted(() => {
  loadUserData();
 
  window.addEventListener('storage', loadUserData);
});


onUnmounted(() => {
  window.removeEventListener('storage', loadUserData);
});

// ไม่ต้อง return { fullname } อีกต่อไป เพราะทุกสิ่งที่ประกาศใน <script setup>
// จะถูก expose ออกไปให้ template ใช้งานได้โดยอัตโนมัติ
// and is therefore left as is, assuming it's for reference or another component.
// const SideProfile = [
//     {
//         id:1,
//         color: 'primary',
//         title: 'My Profile'
//     },
//     {
//         id:2,
//         color: 'secondary',
//         title: 'My Notes'
//     },
//     {
//         id:3,
//         color: 'success',
//         title: 'Inbox'
//     },
//     {
//         id:4,
//         color: 'warning',
//         title: 'Account Settings'
//     },
//     {
//         id:5,
//         color: 'error',
//         title: 'Logout'
//     }
// ];
</script>

<template>
  
    <v-menu open-on-click>
        <template v-slot:activator="{ props }">
            <div class="d-flex justify-space-between align-center w-100 cursor-pointer" v-bind="props">
                <h5 class="font-weight-medium text-15 text-surface"> {{ fullname }}</h5>
                </div>
        </template>

        <!-- The commented-out v-sheet and its content are left as is,
             assuming they are for future use or part of a larger design. -->
        <!-- <v-sheet rounded="md" width="230" elevation="10">
            <perfect-scrollbar style="height: calc(100vh - 240px); max-height: 240px">
                <v-list class="py-0 theme-list mt-3" lines="two">
                    <v-list-item v-for="item in SideProfile" :key="item.id" class="py-1 custom-text-primary cursor-pointer" >
                        <div class="d-flex ga-3 align-center">
                            <h6 class="text-body-1 heading custom-title">{{ item.title }}</h6>
                        </div>
                    </v-list-item>
                </v-list>
            </perfect-scrollbar>
            <v-divider class="mt-2 pb-5"></v-divider>
            <div class="pb-6 px-5 text-center">
                <v-btn color="primary" class="w-100" rounded="pill" flat block to="">View Profile</v-btn>
            </div>
        </v-sheet> -->
    </v-menu>
</template>

<style scoped>
/* Add any specific styles for your sidebar profile component here */
</style>
