<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import MonthContractTable from '@/components/shared/MonthContractTable.vue';
import { Icon } from '@iconify/vue';
import * as XLSX from 'xlsx';

const props = defineProps<{
  monthName: string;
  quarter: number;
}>();

const snackbar = ref(false);
const snackbarText = ref('');
const snackbarColor = ref('success');

const tab = ref(0);

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

const dialogOpen = ref(false);
const step = ref(0);

const currentYear = new Date().getFullYear() + 543;
const selectedBuddhistYear = ref(currentYear);
const yearOptions = Array.from({ length: 2 }, (_, i) => currentYear - i);

const today = new Date();
const currentMonthNumber = today.getMonth() + 1;

interface MonthItem {
  label: string;
  quarter: number;
  monthNumber: number;
  index: number;
}

const monthsList = ref<MonthItem[]>([]);

const isFirstLoad = ref(true);

watch(
  selectedBuddhistYear,
  (newYear) => {
    const gregorianYear = newYear - 543;
    const isCurrentYear = today.getFullYear() === gregorianYear;
    const monthLimit = isCurrentYear ? today.getMonth() + 1 : 12;

    monthsList.value = Array.from({ length: monthLimit }, (_, i) => {
      const date = new Date(gregorianYear, i);
      const month = date.toLocaleString('th-TH', { month: 'long' });
      return {
        label: `${month} ${newYear}`,
        quarter: Math.floor(i / 3) + 1,
        monthNumber: i + 1,
        index: i
      };
    });

    if (isFirstLoad.value && isCurrentYear) {
      setTimeout(() => {
        setActiveMonthPanel(newYear);
        isFirstLoad.value = false;
      }, 0);
    }
  },
  { immediate: true }
);

const panelQ1 = ref<number[]>([]);
const panelQ2 = ref<number[]>([]);
const panelQ3 = ref<number[]>([]);
const panelQ4 = ref<number[]>([]);

const panelModels: Record<number, ReturnType<typeof ref<number[]>>> = {
  1: panelQ1,
  2: panelQ2,
  3: panelQ3,
  4: panelQ4
};

const getPanelModel = (quarter: number): number[] => {
  return panelModels[quarter]?.value ?? [];
};

const updatePanelModel = (quarter: number, val: unknown) => {
  if (typeof val === 'number') {
    panelModels[quarter].value = [val];
  } else if (Array.isArray(val) && val.every((i) => typeof i === 'number')) {
    panelModels[quarter].value = val;
  } else {
    console.warn('Invalid panel value:', val);
  }
};

// (!!!) 1. ปรับปรุงโครงสร้างข้อมูล regionsData
const regionsData = ref([
  {
    name: 'กรุงเทพปริมณฑล',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  },
  {
    name: 'ภาคเหนือ',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  },
  {
    name: 'ภาคตะวันออกเฉียงเหนือ',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  },
  {
    name: 'ภาคกลาง',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  },
  {
    name: 'ภาคตะวันออก',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  },
  {
    name: 'ภาคใต้',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  },
  {
    name: 'ภาคตะวันตก',
    contractStatus: 'has',
    rows: [
      { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false },
      { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, valueDisplay: '0.00', area: 0, areaDisplay: '0.00', errorMessage: '', touched: false }
    ]
  }
]);

function validateRowValue(row: any) {
  const messages: string[] = [];

  // (!!!) Validation ยังคงใช้ row.value (ตัวเลขจริง)
  if (row.touched && row.value !== undefined && row.value !== null && row.value !== 0) {
    let min = 0;
    let max = 0;
    const unit = row.unit;

    if (row.label.includes('ไม่เกิน 2.50 ล้านบาท')) {
      min = 1000000;
      max = unit * 2500000;
    } else if (row.label.includes('2.51 - 5 ล้านบาท')) {
      min = 2.51 * 1000000;
      max = unit * 5000000;
    } else if (row.label.includes('5.01 - 10 ล้านบาท')) {
      min = 5.01 * 1000000;
      max = unit * 10000000;
    } else if (row.label.includes('10.01 - 20 ล้านบาท')) {
      min = 10.01 * 1000000;
      max = unit * 20000000;
    } else if (row.label.includes('20.01 ล้านขึ้นไป')) {
      min = 20.01 * 1000000;
      max = unit * 999999999;
    }

    if (row.value < min) {
      messages.push(`มูลค่ารวมต้องไม่ต่ำกว่า ${min.toLocaleString()} THB `);
    }

    if (row.value > max) {
      messages.push(`มูลค่ารวมไม่สามารถเกิน ${max.toLocaleString()} THB `);
    }
  }

  if (row.touched && row.unit > 50) {
    messages.push('จำนวนหลังไม่เกิน 50 หลัง');
  }

  return messages;
}

function setActiveMonthPanel(year: number) {
  if (year !== currentYear) return;

  const currentQuarter = Math.floor((currentMonthNumber - 1) / 3) + 1;
  const quarterMonths = monthsList.value.filter((m) => m.quarter === currentQuarter);
  const indexInQuarter = quarterMonths.findIndex((m) => m.monthNumber === currentMonthNumber);

  if (indexInQuarter !== -1) {
    panelModels[currentQuarter].value = [indexInQuarter];
  }
}

function validateUnit(row: any) {
  const messages: string[] = [];
  if (row.touched && row.unit > 50) {
    messages.push('จำนวนหลังไม่เกิน 50 หลัง');
  }
  return messages;
}

function validateArea(row: any) {
  const messages: string[] = [];

  // (!!!) Validation ยังคงใช้ row.area (ตัวเลขจริง)
  if (row.touched && row.area !== null && row.area !== undefined && row.area !== 0) {
    if (row.label.includes('ไม่เกิน 2.50 ล้านบาท')) {
      if (row.area < 50 || row.area > 3000) {
        messages.push('พื้นที่ใช้สอยต้องอยู่ระหว่าง 50 - 3,000 ตร.ม.');
      }
    } else if (row.label.includes('2.51 - 5 ล้านบาท')) {
      if (row.area < 100 || row.area > 6000) {
        messages.push('พื้นที่ใช้สอยต้องอยู่ระหว่าง 100 - 6,000 ตร.ม.');
      }
    } else if (row.label.includes('5.01 - 10 ล้านบาท')) {
      if (row.area < 150 || row.area > 12000) {
        messages.push('พื้นที่ใช้สอยต้องอยู่ระหว่าง 150 - 12,000 ตร.ม.');
      }
    } else if (row.label.includes('10.01 - 20 ล้านบาท')) {
      if (row.area < 200 || row.area > 24000) {
        messages.push('พื้นที่ใช้สอยต้องอยู่ระหว่าง 200 - 24,000 ตร.ม.');
      }
    } else if (row.label.includes('20.01 ล้านขึ้นไป')) {
      if (row.area < 40 || row.area > 8000) {
        messages.push('พื้นที่ใช้สอยต้องอยู่ระหว่าง 40 - 8,000 ตร.ม.');
      }
    }
  }

  return messages;
}

// (!!!) เพิ่มฟังก์ชันใหม่นี้เข้าไป
function isDataValid() {
  // .every() คือ "ทุกภาคต้องถูกต้อง"
  return regionsData.value.every((region) => {
    
    // ถ้าเลือก 'ไม่มียอด' ถือว่าถูกต้อง
    if (region.contractStatus === 'none') {
      return true;
    }

    // ถ้าเลือก 'มียอด' ต้องเช็กทุกแถว
    // .every() คือ "ทุกแถวต้องถูกต้อง"
    return region.rows.every((row) => {
      
      // --- 1. ตรวจสอบความถูกต้องของ "จำนวนหลัง" ---
      // (เหมือนใน validateUnit)
      if (row.unit > 50) return false;

      // --- 2. ตรวจสอบความถูกต้องของ "พื้นที่ใช้สอย" ---
      // (เหมือนใน validateArea แต่ไม่เช็ก touched)
      // เราจะเช็กเฉพาะแถวที่มีการกรอกข้อมูลเท่านั้น
      if (row.area > 0) {
        if (row.label.includes('ไม่เกิน 2.50 ล้านบาท') && (row.area < 50 || row.area > 3000)) return false;
        if (row.label.includes('2.51 - 5 ล้านบาท') && (row.area < 100 || row.area > 6000)) return false;
        if (row.label.includes('5.01 - 10 ล้านบาท') && (row.area < 150 || row.area > 12000)) return false;
        if (row.label.includes('10.01 - 20 ล้านบาท') && (row.area < 200 || row.area > 24000)) return false;
        if (row.label.includes('20.01 ล้านขึ้นไป') && (row.area < 40 || row.area > 8000)) return false;
      }

      // --- 3. ตรวจสอบความถูกต้องของ "มูลค่ารวม" ---
      // (เหมือนใน validateRowValue แต่ไม่เช็ก touched)
      if (row.value > 0) {
        let min = 0;
        let max = 0;
        const unit = row.unit;

        if (row.label.includes('ไม่เกิน 2.50 ล้านบาท')) { min = 1000000; max = unit * 2500000; }
        else if (row.label.includes('2.51 - 5 ล้านบาท')) { min = 2.51 * 1000000; max = unit * 5000000; }
        else if (row.label.includes('5.01 - 10 ล้านบาท')) { min = 5.01 * 1000000; max = unit * 10000000; }
        else if (row.label.includes('10.01 - 20 ล้านบาท')) { min = 10.01 * 1000000; max = unit * 20000000; }
        else if (row.label.includes('20.01 ล้านขึ้นไป')) { min = 20.01 * 1000000; max = unit * 999999999; }

        if (row.value < min) return false;
        if (row.value > max) return false;
      }
      
      // --- 4. ตรวจสอบความสัมพันธ์ ---
      // ถ้ากรอกจำนวนหลัง (unit > 0) แต่ไม่กรอก มูลค่า หรือ พื้นที่
      if (row.unit > 0 && (row.value === 0 || row.area === 0)) {
        return false;
      }

      // ถ้าผ่านทุกด่าน แถวนี้ถูกต้อง
      return true;
    });
  });
}

const selectedMonth = ref<MonthItem>({ label: '', quarter: 0, monthNumber: 0, index: 0 });

// (!!!) 1. แก้ไข handleOpenDialog
const handleOpenDialog = (month: MonthItem) => {
  selectedMonth.value = month;
  step.value = 0;
  dialogOpen.value = true;

  regionsData.value.forEach((region) => {
    
    // (!!!) เพิ่มบรรทัดนี้เข้าไป
    // ตั้งค่าเริ่มต้นให้ทุกภาคเป็น 'has' ก่อน
    region.contractStatus = 'has'; 

    region.rows.forEach((row) => {
      row.touched = false;
      row.errorMessage = '';
      // รีเซ็ตค่าทั้งหมด
      row.unit = 0;
      row.value = 0;
      row.valueDisplay = '0.00';
      row.area = 0;
      row.areaDisplay = '0.00';
    });
  });

  // เรียก loadContractData หลังจากรีเซ็ตฟอร์มแล้ว
  loadContractData(month);
};

const saveContractData = async () => {
  // (!!!) 1. เรียกใช้ฟังก์ชันซิงค์ก่อนเลย
  // นี่คือการ "บังคับ Blur" ให้ทุกช่องก่อนเซฟ
  syncAllDisplayValuesToNumeric();

  // (!!!) 2. (สำคัญมาก) ตรวจสอบความถูกต้องอีกครั้ง
  // หลังจากที่ซิงค์ค่าตัวเลขจริง (row.value, row.area) แล้ว
  if (!isDataValid()) {
      snackbarText.value = 'ข้อมูลไม่ถูกต้อง! กรุณาตรวจสอบข้อผิดพลาด (สีแดง)';
      snackbarColor.value = 'error';
      snackbar.value = true;
      return; // หยุดการทำงาน ห้ามเซฟ
  }

  const userId = localStorage.getItem('user_id');
  console.log('user_id:', userId);

  const payload = {
    user_id: userId,
    buddhist_year: selectedBuddhistYear.value,
    month_number: selectedMonth.value.monthNumber,
    quarter: selectedMonth.value.quarter,
    // (!!!) 3. ตอนนี้ regionsData.value ปลอดภัยแล้ว
    regions: regionsData.value, 
    is_edit: isEditMode.value,
    role: userRole
  };

  // ... (ส่วนที่เหลือของ try/catch เหมือนเดิม)
  try {
    const response = await fetch('https://uat.hba-sales.org/backend/submit_contract.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    console.log('Backend response:', result);

    dialogOpen.value = false;
    snackbarText.value = 'บันทึกข้อมูลสำเร็จ!';
    snackbarColor.value = 'success';
    snackbar.value = true;

    setTimeout(() => {
      window.location.reload();
    }, 2000);
  } catch (error) {
    snackbarText.value = 'เกิดข้อผิดพลาดขณะบันทึกข้อมูล';
    snackbarColor.value = 'error';
    snackbar.value = true;
    console.error(error);
  }
};
const isEditMode = ref(false);

// (!!!) 2. แก้ไข loadContractData
const loadContractData = async (month: MonthItem) => {
  const payload = {
    user_id: Number(userId),
    buddhist_year: selectedBuddhistYear.value,
    month_number: month.monthNumber,
    quarter: month.quarter
  };

  try {
    const response = await fetch('https://uat.hba-sales.org/backend/get_contract.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const result = await response.json();

    // (!!!) ลบเนื้อหาใน if (result.found) และ else ทั้งหมด แล้วแทนที่ด้วยโค้ดใหม่นี้

    if (result.found) {
        isEditMode.value = true;
        const fetched = result.details;

        regionsData.value.forEach((region) => {
            const regionData = fetched[region.name];
            
            // --- START FIX ---
            if (regionData) {
                // ถ้ามีข้อมูล (Data exists for this region)
                region.rows.forEach((row) => {
                    const val = regionData[row.label];
                    if (val) {
                        row.unit = val.unit;
                        row.value = val.value; 
                        row.valueDisplay = val.value.toLocaleString('th-TH', { 
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2
                        });
                        row.area = val.area; 
                        row.areaDisplay = val.area.toLocaleString('th-TH', { 
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2
                        });
                    }
                    // (เราไม่ต้องมี else ที่นี่ เพราะ handleOpenDialog รีเซ็ตเป็น 0 ให้แล้ว)
                });

                // ตรวจสอบข้อมูลที่โหลดมา: ถ้าทุกแถวเป็น 0 หมายความว่า 'ไม่มียอด'
                const allZero = region.rows.every(r => r.unit === 0 && r.value === 0 && r.area === 0);
                region.contractStatus = allZero ? 'none' : 'has';

            } else {
                // (!!!) นี่คือส่วนที่แก้ Bug
                // ถ้า `fetched[region.name]` ไม่มี (undefined)
                // หมายความว่า PHP ไม่เจอข้อมูลของภาคนี้ (เพราะเคยเลือก 'ไม่มียอด')
                region.contractStatus = 'none';
            }
            // --- END FIX ---
        });
    } else {
        isEditMode.value = false;
        // ไม่ต้องทำอะไรที่นี่ เพราะ handleOpenDialog
        // ได้รีเซ็ตฟอร์มเป็น "กรอกใหม่" (status: 'has', values: 0) 
        // ที่ถูกต้องให้เราแล้ว
    }

  } catch (err) {
    console.error('Error loading data:', err);
  }
};


function syncAllDisplayValuesToNumeric() {
  regionsData.value.forEach((region) => {
    region.rows.forEach((row) => {
     
      let numValue = parseFloat(String(row.valueDisplay).replace(/,/g, ''));
      row.value = isNaN(numValue) ? 0 : numValue;
      
      // ซิงค์ 'area'
      let numArea = parseFloat(String(row.areaDisplay).replace(/,/g, ''));
      row.area = isNaN(numArea) ? 0 : numArea;
    });
  });
}


// (!!!) เขียนทับ handleFocus ของเดิมด้วยอันนี้
async function handleFocus(event: any, row: any, field: string) {
    const displayField = `${field}Display`;
    const numericValue = row[field]; // อ่านจากตัวเลขจริง (e.g., 1234.56)
    
    if (numericValue === 0) {
        row[displayField] = ''; 
    } else {
        const hasRealDecimal = numericValue % 1 !== 0;

        row[displayField] = numericValue.toLocaleString('th-TH', {
             minimumFractionDigits: hasRealDecimal ? 2 : 0,
             maximumFractionDigits: hasRealDecimal ? 2 : 0
        });
    }

    await nextTick();
    
    if (event.target && typeof event.target.select === 'function') {
        event.target.select();
    }
}

function handleBlur(row: any, field: string) {
  const displayField = `${field}Display`; // เช่น 'valueDisplay'
  
  // 1. อ่านค่าจากช่องที่พิมพ์ (String)
  let numericValue = parseFloat(row[displayField].replace(/,/g, ''));
  if (isNaN(numericValue)) {
    numericValue = 0;
  }

  // 2. อัปเดตค่าตัวเลขจริง (Number)
  row[field] = numericValue; // เช่น row.value = 1000

  // 3. อัปเดตค่าที่แสดงผล (String) ให้กลับเป็นแบบมีทศนิยม
  row[displayField] = numericValue.toLocaleString('th-TH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  // 4. สั่งให้ Vue ตรวจสอบ Validation อีกครั้ง
  row.touched = true;
}

function isFormCompleted() {
  // ใช้ .every() เพื่อตรวจสอบว่า "ทุก" ภาค
  // เป็นไปตามเงื่อนไขที่เรากำหนด
  return regionsData.value.every((region) => {
    
    // เงื่อนไขที่ 1: 
    // ผู้ใช้เลือก "ไม่มียอดเซ็นสัญญา"
    const hasNoContract = region.contractStatus === 'none';
    
    // เงื่อนไขที่ 2:
    // ผู้ใช้เลือก "มียอดเซ็นสัญญา" *และ* // มีแถวใดแถวหนึ่งที่กรอกข้อมูลครบ (unit, value, และ area มากกว่า 0)
    const hasContractAndData = 
      region.contractStatus === 'has' &&
      region.rows.some(
        (row) => row.unit > 0 && row.value > 0 && row.area > 0
      );

    // ภาคนี้จะถือว่า "สมบูรณ์" 
    // ก็ต่อเมื่อเงื่อนไขที่ 1 (ไม่มียอด) หรือ เงื่อนไขที่ 2 (มียอดและกรอก) เป็นจริง
    return hasNoContract || hasContractAndData;
  });
}


// (!!!) เพิ่มฟังก์ชันใหม่นี้เข้าไป
async function handleInput(event: Event, row: any, field: string) {
    const displayField = `${field}Display`;
    const input = event.target as HTMLInputElement;
    let cursorPosition = input.selectionStart || 0;
    let originalValue = row[displayField]; // ค่าที่มีลูกน้ำอยู่แล้ว
    const originalLength = originalValue.length;

    // 1. กรองเอาเฉพาะตัวเลข และ จุดทศนิยม (ถ้ามี)
    let rawValue = originalValue.replace(/[^0-9.]/g, '');
    const parts = rawValue.split('.');
    
    let integerPart = parts[0];
    let decimalPart = parts[1] ? parts[1] : '';

    // 2. จัดรูปแบบส่วนหน้าจุดทศนิยม (Integer)
    const formattedInteger = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    // 3. ประกอบร่างกลับ
    let newFormattedValue = formattedInteger;
    if (parts.length > 1) { // ถ้าผู้ใช้พิมพ์จุด
        newFormattedValue += '.' + decimalPart.substring(0, 2); // จำกัดทศนิยม 2 ตำแหน่ง
    }
    
    // 4. อัปเดต v-model
    row[displayField] = newFormattedValue;

    // 5. (ส่วนที่ซับซ้อน) พยายามย้ายเคอร์เซอร์ไปตำแหน่งที่ถูกต้อง
    await nextTick(); // รอให้ Vue อัปเดตหน้าจอ

    const newLength = newFormattedValue.length;
    // คำนวณตำแหน่งเคอร์เซอร์ใหม่ โดยบวก/ลบ ตามจำนวนลูกน้ำที่เพิ่มขึ้น/ลดลง
    const newCursorPosition = cursorPosition + (newLength - originalLength);
    
    // ตั้งค่าตำแหน่งเคอร์เซอร์
    input.setSelectionRange(newCursorPosition, newCursorPosition);
}

</script>

<template>
  <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000" location="top right">
    {{ snackbarText }}
    <template v-slot:actions>
      <v-btn color="white" variant="text" @click="snackbar = false">
        ปิด
      </v-btn>
    </template>
  </v-snackbar>
  <v-row>
    <v-card elevation="10">
      <v-card-text>
        <div class="v-row">
          <div class="v-col-md-6 v-col-12">
            <div class="d-flex align-center">
              <div>
                <h3 class="card-title mb-1">บันทึกข้อมูลยอดเซ็นสัญญา ประจำปี {{ selectedBuddhistYear }}
                </h3>
                <h5 class="card-subtitle" style="text-align: left;">ข้อมูลแสดงเป็นรายเดือน</h5>
              </div>
            </div>
          </div>
          <div class="v-col-md-6 v-col-12 text-right">
            <div class="d-flex justify-end v-col-md-12 v-col-lg-12 v-col-12 ">
              <v-select v-model="selectedBuddhistYear" label="เลือกปี" :items="yearOptions" class="mr-4"></v-select>
            </div>
          </div>
        </div>
        <div class="v-col-md-12"></div>
        <div v-for="quarter in [1, 2, 3, 4]" :key="quarter" class="mb-6">
          <h3 class="text-h6 mb-2" style="font-weight: 500; color: #1b84ff"
            v-if="selectedBuddhistYear < currentYear || quarter <= Math.ceil(currentMonthNumber / 3)">
            ยอดเซ็นสัญญาไตรมาส {{ quarter }}
          </h3>
          <v-expansion-panels accordion :model-value="getPanelModel(quarter)[0]"
            @update:model-value="(val: unknown) => updatePanelModel(quarter, val)">
            <v-expansion-panel v-for="month in monthsList.filter((m) => m.quarter === quarter)" :key="month.index"
              :hide-actions="month.monthNumber === currentMonthNumber && selectedBuddhistYear === currentYear">
              <v-expansion-panel-title>
                <div class="d-flex justify-space-between align-center w-100">
                  <span>ยอดเซ็นสัญญาประจำเดือน {{ month.label }}</span>
                  <template v-if="
                    month.monthNumber === currentMonthNumber &&
                    selectedBuddhistYear === currentYear &&
                    userRole !== 'admin' && userRole !== 'master'
                  ">
                    <v-dialog v-model="dialogOpen" width="80%">
                      <template #activator="{ props }">
                        <v-btn v-bind="props" color="primary" text="เพิ่มข้อมูลยอดเซ็นสัญญา" variant="flat" size="small"
                          @click="handleOpenDialog(month)" />
                      </template>
                      <template #default="{ isActive }">
                        <v-card>
                          <template #title>
                            <div class="text-h6">
                              เพิ่มข้อมูลยอดเซ็นสัญญา - เดือน
                              <strong style="color: #f8285a">{{ selectedMonth?.label ||
                                '-' }}</strong>
                              | ไตรมาส
                              <strong style="color: #f8285a">{{ selectedMonth?.quarter ||
                                '-' }}</strong>
                            </div>
                          </template>
                          <template #append>
                            <v-btn icon="mdi-close" variant="text" color="grey-darken-1"
                              @click="isActive.value = false" />
                          </template>
                          <v-card-text>
                            <v-tabs v-model="tab" fixed-tabs color="primary" class="mt-5 d-flex ga-3" height="100">
                              <v-tab v-for="(region, index) in regionsData" :key="index" :value="index">
                                <Icon icon="solar:user-circle-outline" height="25" />{{
                                  region.name }}
                              </v-tab>
                            </v-tabs>
                            <v-window v-model="tab" class="mt-12">
                              <v-window-item v-for="(region, index) in regionsData" :key="region.name" :value="index">
                                <v-row>
                                  <v-col cols="12">
                                    <v-radio-group v-model="region.contractStatus" inline color="#1B84FF">
                                      <v-radio label="มียอดเซ็นสัญญา" value="has" />
                                      <v-radio label="ไม่มียอดเซ็นสัญญา" value="none" />
                                    </v-radio-group>
                                    <v-table>
                                      <thead style="background-color: #2cabe3; color: #ffffff">
                                        <tr>
                                          <th>ประเภท</th>
                                          <th>จำนวนหลัง</th>
                                          <th>มูลค่ารวม</th>
                                          <th>พื้นที่ใช้สอย</th>
                                          <th>ราคาเฉลี่ย</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        <tr v-for="row in region.rows" :key="row.label">
                                          <td class="py-2">{{ row.label }}
                                          </td>

                                          <td class="py-2">
                                            <v-text-field v-model.number="row.unit" type="number" min="0" max="99"
                                              density="compact" hide-details="auto" persistent-hint
                                              :error-messages="validateUnit(row)"
                                              :disabled="region.contractStatus === 'none'" @input="
                                                () => {
                                                  // (!!!) รีเซ็ตทั้ง value และ area
                                                  row.value = 0;
                                                  row.valueDisplay = '0.00';
                                                  row.area = 0;
                                                  row.areaDisplay = '0.00';
                                                }
                                              " @blur="row.touched = true" />
                                          </td>

                                         <td class="py-2">
    <v-text-field
        v-model="row.valueDisplay"
        type="text"
        density="compact"
        hide-details="auto"
        persistent-hint
        :error-messages="validateRowValue(row)"
        :disabled="region.contractStatus === 'none' ||
        row.unit === 0
        "
        @input="handleInput($event, row, 'value')" @focus="handleFocus($event, row, 'value')"
        @blur="handleBlur(row, 'value')" />
</td>
                                         <td class="py-2">
    <v-text-field
        v-model="row.areaDisplay"
        type="text"
        density="compact"
        hide-details="auto"
        persistent-hint
        :disabled="region.contractStatus === 'none' ||
        row.value === 0
        "
        :error-messages="validateArea(row)"
        @input="handleInput($event, row, 'area')" @focus="handleFocus($event, row, 'area')"
        @blur="handleBlur(row, 'area')" />
</td>

                                          <td class="py-2">
                                            {{
                                              row.area > 0 && row.value > 0
                                                ? (row.value /
                                                  row.area).toFixed(2)
                                                : '0.00'
                                            }}
                                          </td>
                                        </tr>

                                        <tr>
                                          <th>รวม</th>
                                          <th>
                                            {{
                                              region.rows
                                                .reduce((a, b) => a + b.unit, 0)
                                                .toLocaleString()
                                            }}
                                          </th>
                                          <th>
                                            {{
                                              region.rows
                                                .reduce((a, b) => a + b.value,
                                                  0)
                                                .toLocaleString('th-TH', {
                                                  minimumFractionDigits: 2,
                                                  maximumFractionDigits: 2
                                                }) }}
                                          </th>
                                          <th>
                                            {{
                                              region.rows
                                                .reduce((a, b) => a + b.area, 0)
                                                .toLocaleString('th-TH', {
                                                  minimumFractionDigits: 2,
                                                  maximumFractionDigits: 2
                                                }) }}
                                          </th>
                                          <th>
                                            {{
                                              region.rows
                                                .reduce((a, b) => {
                                                  const averagePrice =
                                                    b.value && b.area
                                                      ? b.value / b.area
                                                      : 0;
                                                  return a + averagePrice;
                                                }, 0)
                                                .toFixed(2)
                                            }}
                                          </th>
                                        </tr>
                                      </tbody>
                                    </v-table>
                                  </v-col>
                                </v-row>
                              </v-window-item>
                            </v-window>
                          </v-card-text>

                          <v-card-actions class="justify-end">
                          <v-btn color="primary" @click="saveContractData"
    :disabled="(!isEditMode && !isFormCompleted()) || !isDataValid()">
    {{ isEditMode ? 'อัปเดตข้อมูล' : 'ยืนยันบันทึกข้อมูล' }}
</v-btn>
                          </v-card-actions>
                        </v-card>
                      </template>
                    </v-dialog>
                  </template>
                </div>
              </v-expansion-panel-title>

              <v-expansion-panel-text>
                <MonthContractTable :monthName="month.label" :quarter="month.quarter" :regionsData="regionsData" />
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </div>
      </v-card-text>
    </v-card>
  </v-row>

  <v-row>
  </v-row>
</template>

<style scoped>
.text-h6 {
  font-size: 18px;
}
</style>