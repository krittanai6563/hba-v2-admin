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

</script>

<template>
  
    <v-menu open-on-click>
        <template v-slot:activator="{ props }">
            <div class="d-flex justify-space-between align-center w-100 cursor-pointer" v-bind="props">
                <h5 class="font-weight-medium text-15 text-surface"> {{ fullname }}</h5>
                </div>
        </template>

    </v-menu>
</template>

<style scoped>

</style>
