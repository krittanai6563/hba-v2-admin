<script setup lang="ts">
import { ref, watch } from 'vue';
import MonthContractTable from '@/components/shared/MonthContractTable.vue';
import { Icon } from '@iconify/vue';
import * as XLSX from 'xlsx';

const props = defineProps<{
  monthName: string
  quarter: number
}>()

const tab = ref(0);

const userId = localStorage.getItem('user_id');
const userRole = localStorage.getItem('user_role') || 'user';

const dialogOpen = ref(false);
const step = ref(0);

const currentYear = new Date().getFullYear() + 543;
const selectedBuddhistYear = ref(currentYear);
const yearOptions = Array.from({ length: 7 }, (_, i) => currentYear - i);

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

const regionsData = ref([
    {
        name: 'กรุงเทพปริมณฑล',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคเหนือ',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคตะวันออกเฉียงเหนือ',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคกลาง',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคตะวันออก',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคใต้',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    },
    {
        name: 'ภาคตะวันตก',
        contractStatus: 'has',
        rows: [
            { label: 'ไม่เกิน 2.50 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '2.51 - 5 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '5.01 - 10 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '10.01 - 20 ล้านบาท', unit: 0, value: 0, area: 0, errorMessage: '', touched: false },
            { label: '20.01 ล้านขึ้นไป', unit: 0, value: 0, area: 0, errorMessage: '', touched: false }
        ]
    }
]);

function validateRowValue(row: any) {
    const messages: string[] = [];

    if (row.touched && row.value !== undefined && row.value !== null && row.value !== 0) {
        let min = 0; // ตั้งค่า min เป็น 0 ก่อน
        let max = 0;
        const unit = row.unit;

        // กำหนด min และ max ตามช่วงประเภท
        if (row.label.includes('ไม่เกิน 2.50 ล้านบาท')) {
            min = 1000000; // ไม่มีค่าขั้นต่ำ
            max = unit * 2500000;
        } else if (row.label.includes('2.51 - 5 ล้านบาท')) {
            min = 2.51 * 1000000; // กำหนดให้ min = 2.51 ล้าน
            max = unit * 5000000;
        } else if (row.label.includes('5.01 - 10 ล้านบาท')) {
            min = 5.01 * 1000000; // กำหนดให้ min = 5.01 ล้าน
            max = unit * 10000000;
        } else if (row.label.includes('10.01 - 20 ล้านบาท')) {
            min = 10.01 * 1000000; // กำหนดให้ min = 10.01 ล้าน
            max = unit * 20000000;
        } else if (row.label.includes('20.01 ล้านขึ้นไป')) {
            min = 20.01 * 1000000; // กำหนดให้ min = 20.01 ล้าน
            max = unit * 999999999; // ไม่มีค่าขอบเขตสูงสุด
        }

        // ตรวจสอบค่ามูลค่ารวม
        if (row.value < min) {
            messages.push(`มูลค่ารวมต้องไม่ต่ำกว่า ${min.toLocaleString()} THB `);
        }

        if (row.value > max) {
            messages.push(`มูลค่ารวมไม่สามารถเกิน ${max.toLocaleString()} THB `);
        }
    }

    // ตรวจสอบข้อผิดพลาดในช่องจำนวนหลัง
    if (row.touched && row.unit > 50) {
        messages.push('จำนวนหลังไม่เกิน 50 หลัง');
    }

    // ตรวจสอบข้อผิดพลาดในช่องพื้นที่ใช้สอย
    if (row.touched && row.area !== null && row.area !== undefined && row.area !== 0 && (row.area < 10000 || row.area > 50000)) {
        messages.push('พื้นที่ใช้สอยต้องอยู่ระหว่าง 10,000 - 50,000 ตร.ม.');
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

    if (row.touched && row.area !== null && row.area !== undefined && row.area !== 0 && (row.area < 10000 || row.area > 50000)) {
        messages.push('โปรดตรวจสอบข้อมูลอีกครั้ง');
    }

    return messages;
}

function isFormCompleted() {
    return regionsData.value.every((region) => {
        return region.contractStatus === 'none' || region.rows.some((row) => row.unit > 0 && row.value > 0 && row.area > 0);
    });
}

const selectedMonth = ref<MonthItem>({ label: '', quarter: 0, monthNumber: 0, index: 0 });

const handleOpenDialog = (month: MonthItem) => {
    selectedMonth.value = month;
    step.value = 0;
    dialogOpen.value = true;

    regionsData.value.forEach((region) => {
        region.rows.forEach((row) => {
            row.touched = false;
            row.errorMessage = '';
        });
    });

    loadContractData(month);
};

const saveContractData = async () => {
    const userId = localStorage.getItem('user_id');

    console.log('user_id:', userId);

    const payload = {
        user_id: userId,
        buddhist_year: selectedBuddhistYear.value,
        month_number: selectedMonth.value.monthNumber,
        quarter: selectedMonth.value.quarter,
        regions: regionsData.value,
        is_edit: isEditMode.value,
        role: userRole
    };

    try {
        const response = await fetch('https://d2e03fa78899.ngrok-free.app/package//backend/submit_contract.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        console.log('Backend response:', result);

        alert('บันทึกข้อมูลสำเร็จ!');
        dialogOpen.value = false;

        // ทำการรีเฟรชหน้าเว็บไซต์เพื่อแสดงข้อมูลที่อัปเดต
        window.location.reload(); // รีเฟรชหน้า
    } catch (error) {
        alert('เกิดข้อผิดพลาดขณะบันทึกข้อมูล');
        console.error(error);
    }
};

const isEditMode = ref(false);

const loadContractData = async (month: MonthItem) => {
    const payload = {
        user_id: Number(userId),
        buddhist_year: selectedBuddhistYear.value,
        month_number: month.monthNumber,
        quarter: month.quarter
    };

    try {
        const response = await fetch('https://d2e03fa78899.ngrok-free.app/package/backend/get_contract.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.found) {
            isEditMode.value = true;
            const fetched = result.details;

            regionsData.value.forEach((region) => {
                const regionData = fetched[region.name];
                if (regionData) {
                    region.contractStatus = regionData.contractStatus || 'has';
                    region.rows.forEach((row) => {
                        const val = regionData[row.label];
                        if (val) {
                            row.unit = val.unit;
                            row.value = val.value;
                            row.area = val.area;

                            // ตั้งค่า formattedValue จากค่าจริงที่ดึงมา
                            formattedValue.value = val.value.toLocaleString(); // ใช้ค่า value ที่ดึงมาจากฐานข้อมูล
                        } else {
                            row.unit = 0;
                            row.value = 0;
                            row.area = 0;

                    
                
                        }
                    });
                }
            });
        } else {
            isEditMode.value = false;
            regionsData.value.forEach((region) => {
                region.contractStatus = 'has';
                region.rows.forEach((row) => {
                    row.unit = 0;
                    row.value = 0;
                    row.area = 0;
                });
            });
        }
    } catch (err) {
        console.error('Error loading data:', err);
    }
};

// ฟังก์ชันที่ใช้ในการเก็บค่าที่เป็นสตริง พร้อมการแสดงเครื่องหมายคั่น
const formattedValue = ref(''); // ตัวแปรเก็บค่าที่แสดงในรูปแบบมีเครื่องหมายคั่น

// ฟังก์ชันสำหรับกรอกข้อมูล
// ฟังก์ชันสำหรับกรอกข้อมูล
// ฟังก์ชันสำหรับกรอกข้อมูล (แปลงค่าให้เป็นตัวเลข)
function onInput(event: any, row: any, field: string) {
    let inputValue = event.target.value.replace(/,/g, ''); // ลบเครื่องหมายคั่น
    const numericValue = parseFloat(inputValue); // แปลงเป็นตัวเลขจริง

    // เช็คว่าค่าที่กรอกเป็นตัวเลขหรือไม่
    if (!isNaN(numericValue)) {
        row[field] = numericValue; // ตั้งค่าผลลัพธ์ลงใน `row`
    } else {
        row[field] = 0; // ถ้าค่าไม่ใช่ตัวเลข กำหนดค่าเป็น 0
    }
}

function formatNumberOnBlur(event: any, row: any, field: string) {
    let inputValue = event.target.value.replace(/,/g, '');
    const numericValue = parseFloat(inputValue);
    if (!isNaN(numericValue)) {
        row[field] = numericValue;
        event.target.value = numericValue.toLocaleString(); 
    }
}


function clearComma(event: any) {
    event.target.value = event.target.value.replace(/,/g, '');
}


// ดึงข้อมูล รายงานยอดเซ็นสัญญา มาแสดง



// // ประกาศ priceRanges และ dataTypes ใน setup()
// const priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];
// const dataTypes = ['จำนวนหลัง', 'มูลค่ารวม', 'พื้นที่ใช้สอย', 'ราคาเฉลี่ย/ตร.ม.'] as const;

// const typeMap: Record<(typeof dataTypes)[number], 'unit' | 'value' | 'area' | 'price_per_sqm'> = {
//   'จำนวนหลัง': 'unit',
//   'มูลค่ารวม': 'value',
//   'พื้นที่ใช้สอย': 'area',
//   'ราคาเฉลี่ย/ตร.ม.': 'price_per_sqm'
// };

// // ฟังก์ชัน exportToExcel
// const exportToExcel = () => {
//   const wb = XLSX.utils.book_new();

//   // สร้างหัวตาราง
//   const headers = ['ประเภท', 'กรุงเทพปริมณฑล', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคกลาง', 'ภาคตะวันตก', 'ภาคใต้', 'ภาคตะวันตก', 'รวม'];
//   const aoa: (string | number)[][] = [headers];

//   // ฟังก์ชันสำหรับการสร้างแผ่นงานตามเดือน
//   monthsList.value.forEach((month) => {
//     const monthData = regionsData.value;

//     // สร้างแผ่นงานใหม่สำหรับเดือน
//     let sheetName = `${month.label} ไตรมาส ${month.quarter} ปี ${selectedBuddhistYear.value}`;

//     // ตัดชื่อแผ่นงานให้ไม่เกิน 31 ตัวอักษร
//     sheetName = sheetName.length > 31 ? sheetName.substring(0, 31) : sheetName;

//     const monthSheet: (string | number)[][] = [];

//     // เพิ่มหัวตารางสำหรับแผ่นงาน
//     monthSheet.push(headers);

//     // เพิ่มหมวดหมู่และข้อมูลแต่ละหมวดหมู่ในเดือนนั้น
//     priceRanges.forEach((priceRange) => {
//       monthSheet.push([`${priceRange}`, ...Array(headers.length - 2).fill('')]); // ผสานเซลล์หัวข้อหมวดหมู่

//       // เพิ่มข้อมูลสำหรับแต่ละประเภท (จำนวนหลัง, มูลค่ารวม, พื้นที่ใช้สอย, ราคาเฉลี่ย)
//       dataTypes.forEach((type) => {
//         const row = [type];
//         monthData.forEach((region) => {
//           const val = region.rows.find((row) => row.label === priceRange);
//           row.push(val ? val[typeMap[type]] : 0);
//         });
//         row.push(row.slice(1).reduce((sum, val) => sum + (val || 0), 0)); // รวมคอลัมน์
//         monthSheet.push(row);
//       });

//       monthSheet.push([]); 
//     });

//     // สร้างแผ่นงาน
//     const sheet = XLSX.utils.aoa_to_sheet(monthSheet);

//     // กำหนดการผสานเซลล์
//     sheet['!merges'] = [
//       { s: { r: 1, c: 0 }, e: { r: 1, c: headers.length - 1 } } // ผสานเซลล์หมวดหมู่
//     ];

  
//     XLSX.utils.book_append_sheet(wb, sheet, sheetName);
//   });

//   XLSX.writeFile(wb, `ContractData_${selectedBuddhistYear.value}.xlsx`);
// };
 
 
</script>


<template>
    <v-row>
        <v-card elevation="10">
            <v-card-text>
                <!-- <div class="v-row">
                    <div class="v-col-md-0 v-col-lg-8 v-col-2">
                        <div class="v-col-md-0 text-left">
                            <h3 class="card-title">บันทึกข้อมูลยอดเซ็นสัญญา</h3>
                            <h5 class="card-subtitle">ข้อมูลแสดงเป็นรายเดือน</h5>
                        </div>
                    </div>
                    <div class="v-col-md-2 v-col-lg-4 v-col-2 text-right">
                        <v-select label="เลือกปี" :items="yearOptions" v-model="selectedBuddhistYear" />
                    </div>
                </div> -->

                 <div class="v-row">
            <div class="v-col-md-6 v-col-12">
              <div class="d-flex align-center">
                <div>
                  <h3 class="card-title mb-1">บันทึกข้อมูลยอดเซ็นสัญญา ประจำปี {{ selectedBuddhistYear }}</h3>
                  <h5 class="card-subtitle" style="text-align: left;">ข้อมูลแสดงเป็นรายเดือน</h5>
                </div>
              </div>
            </div>

            <div class="v-col-md-6 v-col-12 text-right">
              <div class="d-flex  justify-end v-col-md-12 v-col-lg-12 v-col-12 ">
                <v-select v-model="selectedBuddhistYear" label="เลือกปี" :items="yearOptions" class="mr-4"></v-select>
                <!-- <v-btn class=" text-primary   v-btn--size-large ">
                  <div class="text-none font-weight-regular muted">Export to CSV</div>
                </v-btn> -->
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
                        @update:model-value="(val) => updatePanelModel(quarter, val)">
                        <v-expansion-panel v-for="month in monthsList.filter((m) => m.quarter === quarter)"
                            :key="month.index"
                            :hide-actions="month.monthNumber === currentMonthNumber && selectedBuddhistYear === currentYear">
                            <v-expansion-panel-title>
                                <div class="d-flex justify-space-between align-center w-100">
                                    <span>ยอดเซ็นสัญญาประจำเดือน {{ month.label }}</span>
                                    <template v-if="
                                        month.monthNumber === currentMonthNumber &&
                                        selectedBuddhistYear === currentYear &&
                                        userRole !== 'admin' &&  userRole  !== 'master'
                                    ">
                                        <v-dialog v-model="dialogOpen" width="80%">
                                            <template #activator="{ props }">
                                                <v-btn v-bind="props" color="primary" text="เพิ่มข้อมูลยอดเซ็นสัญญา"
                                                    variant="flat" size="small" @click="handleOpenDialog(month)" />
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
                                                        <!-- Tabs for selecting region -->
                                                        <v-tabs v-model="tab" fixed-tabs color="primary"
                                                            class="mt-5 d-flex ga-3" height="100">
                                                            <v-tab v-for="(region, index) in regionsData" :key="index"
                                                                :value="index">
                                                                <Icon icon="solar:user-circle-outline" height="25" />{{
                                                                region.name }}
                                                            </v-tab>
                                                        </v-tabs>

                                                        <!-- Window for tab content -->
                                                        <v-window v-model="tab" class="mt-12">
                                                            <v-window-item v-for="(region, index) in regionsData"
                                                                :key="region.name" :value="index">
                                                                <v-row>
                                                                    <v-col cols="12">
                                                                        <!-- Radio buttons for contract status -->
                                                                        <v-radio-group v-model="region.contractStatus"
                                                                            inline color="#1B84FF">
                                                                            <v-radio label="มียอดเซ็นสัญญา"
                                                                                value="has" />
                                                                            <v-radio label="ไม่มียอดเซ็นสัญญา"
                                                                                value="none" />
                                                                        </v-radio-group>

                                                                        <!-- Table for region data -->
                                                                        <v-table>
                                                                            <thead
                                                                                style="background-color: #2cabe3; color: #ffffff">
                                                                                <tr>
                                                                                    <th>ประเภท</th>
                                                                                    <th>จำนวนหลัง</th>
                                                                                    <th>มูลค่ารวม</th>
                                                                                    <th>พื้นที่ใช้สอย</th>
                                                                                    <th>ราคาเฉลี่ย</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                <tr v-for="row in region.rows"
                                                                                    :key="row.label">
                                                                                    <td class="py-2">{{ row.label }}
                                                                                    </td>

                                                                                    <!-- จำนวนหลัง -->
                                                                                    <td class="py-2">
                                                                                        <v-text-field
                                                                                            v-model.number="row.unit"
                                                                                            type="number" min="0"
                                                                                            max="99" density="compact"
                                                                                            hide-details="auto"
                                                                                            persistent-hint
                                                                                            :error-messages="validateUnit(row)"
                                                                                            :disabled="region.contractStatus === 'none'"
                                                                                            @input="
                                                                                                () => {
                                                                                                    row.value = 0;
                                                                                                    row.area = 0;
                                                                                                }
                                                                                            "
                                                                                            @blur="row.touched = true" />
                                                                                    </td>

                                                                                    <!-- มูลค่ารวม -->
                                                                                    <td class="py-2">
                                                                                        <v-text-field
                                                                                            v-model="row.value"
                                                                                            :value="row.value.toLocaleString()"
                                                                                            type="text"
                                                                                            density="compact"
                                                                                            hide-details="auto"
                                                                                            persistent-hint
                                                                                            :error-messages="validateRowValue(row)"
                                                                                            :disabled="region.contractStatus === 'none' ||
                                                                                                row.unit === 0
                                                                                                "
                                                                                            @input="onInput($event, row, 'value')"
                                                                                            @blur="formatNumberOnBlur($event, row, 'value')"
                                                                                            @focus="clearComma($event)" />
                                                                                    </td>

                                                                                    <!-- พื้นที่ใช้สอย -->
                                                                                    <td class="py-2">
                                                                                        <v-text-field v-model="row.area"
                                                                                            :value="row.area ? row.area.toLocaleString() : ''
                                                                                                " type="text"
                                                                                            density="compact"
                                                                                            hide-details="auto"
                                                                                            persistent-hint :disabled="region.contractStatus === 'none' ||
                                                                                                row.value === 0
                                                                                                "
                                                                                            :error-messages="validateArea(row)"
                                                                                            @input="onInput($event, row, 'area')"
                                                                                            @blur="formatNumberOnBlur($event, row, 'area')"
                                                                                            @focus="clearComma($event)" />
                                                                                    </td>

                                                                                    <!-- ราคาเฉลี่ย -->
                                                                                    <td class="py-2">
                                                                                        {{
                                                                                            row.area > 0 && row.value > 0
                                                                                                ? (row.value /
                                                                                                    row.area).toFixed(2)
                                                                                                : '0.00'
                                                                                        }}
                                                                                    </td>
                                                                                </tr>

                                                                                <!-- รวม -->
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
                                                                                                .toLocaleString()
                                                                                        }}
                                                                                    </th>
                                                                                    <th>
                                                                                        {{
                                                                                            region.rows
                                                                                                .reduce((a, b) => a + b.area, 0)
                                                                                                .toLocaleString()
                                                                                        }}
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
                                                        <!-- <v-btn :disabled="step === 0" text="ก่อนหน้า" @click="step--"></v-btn>
                                                        <v-btn
                                                            :disabled="step === regionsData.length - 1"
                                                            text="ถัดไป"
                                                            @click="step++"
                                                        ></v-btn> -->
                                                        <v-btn color="primary" @click="saveContractData"
                                                            :disabled="!isEditMode && !isFormCompleted()">
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
                                <MonthContractTable :monthName="month.label" :quarter="month.quarter"
                                    :regionsData="regionsData" />
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



