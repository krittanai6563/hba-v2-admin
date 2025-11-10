// src/stores/alert.ts
import { defineStore } from 'pinia';

// 1. กำหนด "หน้าตา" ของ state ที่เราจะเก็บ
interface AlertState {
  show: boolean; // สั่งให้แสดง/ซ่อน
  text: string;  // ข้อความที่จะแสดง
  color: 'success' | 'error' | 'warning' | 'info'; // สีของ Snackbar
  timeout: number; // เวลาที่จะให้แสดง (หน่วย: ms)
}

// 2. สร้าง store ที่ชื่อ 'alert'
export const useAlertStore = defineStore('alert', {
  
  // 3. กำหนดค่าเริ่มต้น
  state: (): AlertState => ({
    show: false,
    text: '',
    color: 'success', // สีเริ่มต้น
    timeout: 3000,    // 3 วินาที
  }),
  
  // 4. กำหนด "คำสั่ง" หรือ "actions" ที่ไฟล์อื่นจะเรียกใช้
  actions: {
    /**
     * สั่งแสดง Snackbar
     * @param text ข้อความที่จะแสดง
     * @param color 'success', 'error', 'warning', 'info'
     * @param timeout เวลา (ms)
     */
    showAlert(text: string, color: AlertState['color'] = 'success', timeout: number = 3000) {
      // เมื่อคำสั่งนี้ถูกเรียก (เช่น จาก LoginForm.vue)
      // มันจะอัปเดตค่าใน state:
      this.text = text;
      this.color = color;
      this.timeout = timeout;
      this.show = true; // สั่งให้แสดง!
    },
    
    // คำสั่งสำหรับซ่อน (เผื่อไว้ใช้กับปุ่ม close)
    hideAlert() {
      this.show = false;
    }
  },
});