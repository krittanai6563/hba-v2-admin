<script setup lang="ts">
import { onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

let logoutTimer: number | undefined

// ฟังก์ชั่นเช็คว่ามีการใช้งานในฟอร์มหรือไม่
const isFormActive = () => {
  const activeElement = document.activeElement;
  return activeElement && (activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'INPUT');
}

const resetTimer = () => {
  if (logoutTimer) clearTimeout(logoutTimer)

  // หากไม่อยู่ในฟอร์ม จะเริ่มนับเวลาใหม่
  if (!isFormActive()) {
    logoutTimer = setTimeout(() => {
      autoLogout()
    }, 3600000) // 1 ชั่วโมง
  }
}

const autoLogout = async () => {
  await fetch('http://localhost/package/backend/logout.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
  })

  localStorage.removeItem('user_id')
  sessionStorage.removeItem('user')
  alert('คุณไม่ได้ใช้งานเป็นเวลา 1 ชั่วโมง ระบบได้ออกจากระบบอัตโนมัติ')
  router.push({ name: 'Login' })
}

const setupActivityListeners = () => {
  ['click', 'mousemove', 'keydown', 'scroll'].forEach(evt => {
    window.addEventListener(evt, resetTimer)
  })
}

const removeActivityListeners = () => {
  ['click', 'mousemove', 'keydown', 'scroll'].forEach(evt => {
    window.removeEventListener(evt, resetTimer)
  })
}

onMounted(() => {
  setupActivityListeners()
  resetTimer() 
})

onBeforeUnmount(() => {
  removeActivityListeners()
  if (logoutTimer) clearTimeout(logoutTimer) 
})
</script>

<template>
  <RouterView />
</template>
